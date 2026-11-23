<?php

namespace App\Terrain\Growth;

use App\Terrain\DataSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Coach IA : un coach personnel qui discute avec le joueur (Claude, d'Anthropic).
 * Il connaît le profil du joueur (poste, pied fort, âge, club) et son dernier diagnostic du coaching gratuit,
 * construit des séances adaptées et suit ses progrès au fil de la conversation.
 *
 * Réglages (.env) : ANTHROPIC_API_KEY, AI_COACH_MODEL, AI_COACH_FREE_PER_DAY (3 par défaut).
 * Gratuit : quelques messages par jour. Premium : illimité (avec un garde-fou anti-abus).
 */
class AiCoach
{
    private const API = 'https://api.anthropic.com/v1/messages';

    /** Nombre de messages précédents renvoyés à l'IA pour garder le fil. */
    private const HISTORY = 24;

    public function __construct(private DataSource $data, private Members $members)
    {
    }

    public static function ready(): bool
    {
        return (string) config('terrain.ai_coach.key') !== '' || self::fake();
    }

    private static function fake(): bool
    {
        return (bool) config('terrain.ai_coach.fake') && ! app()->isProduction();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    public function history(string $userKey, int $limit = 60)
    {
        try {
            return DB::table('ai_coach_messages')->where('user_key', $userKey)->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        } catch (Throwable) {
            return collect();
        }
    }

    public function usedToday(string $userKey): int
    {
        try {
            return DB::table('ai_coach_messages')->where('user_key', $userKey)->where('role', 'user')->where('created_at', '>=', now()->startOfDay())->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public function premium(string $userKey): bool
    {
        return $this->members->isPremium($userKey);
    }

    public function limit(string $userKey): int
    {
        return $this->premium($userKey) ? (int) config('terrain.ai_coach.premium_per_day', 60) : (int) config('terrain.ai_coach.free_per_day', 3);
    }

    public function remaining(string $userKey): int
    {
        return max(0, $this->limit($userKey) - $this->usedToday($userKey));
    }

    public function reset(string $userKey): void
    {
        DB::table('ai_coach_messages')->where('user_key', $userKey)->delete();
    }

    /** Envoie le message du joueur et enregistre la réponse du coach. */
    public function ask(array $me, string $message): string
    {
        $key = $me['id'];
        $past = $this->history($key, self::HISTORY)->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->all();
        $messages = array_merge($past, [['role' => 'user', 'content' => $message]]);
        // L'API veut une conversation qui commence par le joueur et alterne les rôles
        while ($messages && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }
        $messages = $this->alternate($messages);

        [$reply, $tokens] = self::fake() ? [$this->fakeReply($message), 0] : $this->call($this->system($me), $messages);

        $now = now();
        DB::table('ai_coach_messages')->insert([
            ['user_key' => $key, 'role' => 'user', 'content' => $message, 'tokens' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['user_key' => $key, 'role' => 'assistant', 'content' => $reply, 'tokens' => $tokens, 'created_at' => $now, 'updated_at' => $now],
        ]);

        return $reply;
    }

    /** @return array{0: string, 1: int} */
    private function call(string $system, array $messages): array
    {
        try {
            $r = Http::timeout(60)->acceptJson()->withHeaders([
                'x-api-key' => (string) config('terrain.ai_coach.key'),
                'anthropic-version' => '2023-06-01',
            ])->post(self::API, [
                'model' => (string) config('terrain.ai_coach.model', 'claude-sonnet-5-5'),
                'max_tokens' => 1200,
                'system' => $system,
                'messages' => $messages,
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new RuntimeException(__('Le coach ne répond pas pour le moment. Réessaie dans un instant.'));
        }
        if (! $r->successful()) {
            Log::warning('Coach IA (Anthropic) '.$r->status().' : '.$r->body());
            throw new RuntimeException($r->status() === 429 || $r->status() === 529
                ? __('Le coach est très demandé en ce moment. Réessaie dans une minute.')
                : __('Le coach ne répond pas pour le moment. Réessaie dans un instant.'));
        }
        $text = collect($r->json('content', []))->where('type', 'text')->pluck('text')->implode("\n");
        if (trim($text) === '') {
            throw new RuntimeException(__('Le coach ne répond pas pour le moment. Réessaie dans un instant.'));
        }

        return [trim($text), (int) $r->json('usage.input_tokens', 0) + (int) $r->json('usage.output_tokens', 0)];
    }

    /** Fusionne deux messages consécutifs du même rôle (ex. après une erreur). */
    private function alternate(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($out && end($out)['role'] === $m['role']) {
                $out[count($out) - 1]['content'] .= "\n\n".$m['content'];
            } else {
                $out[] = $m;
            }
        }

        return $out;
    }

    /** Consignes du coach + ce que l'on sait du joueur. */
    private function system(array $me): string
    {
        $app = (string) config('terrain.app_name', 'FootProWorld');
        $lang = app()->getLocale() === 'en' ? 'English' : 'français';
        $profile = $this->profile($me);

        return <<<TXT
Tu es « Coach IA », le coach personnel de football de {$app}, le réseau social du foot. Tu entraînes un joueur ou une joueuse comme un vrai préparateur : exigeant, motivant, bienveillant et concret.

Ta mission :
- Faire progresser le joueur : technique, physique (vitesse, endurance, force, explosivité), tactique selon son poste, mental, récupération, nutrition simple et sommeil.
- Au début, si tu ne le sais pas encore, pose 2 ou 3 questions courtes : poste, âge, niveau, objectif, matériel et terrain disponibles, nombre de séances possibles par semaine. Ne pose pas toutes les questions d'un coup.
- Donne des séances précises et réalisables : échauffement, exercices avec séries, répétitions ou durée, temps de repos, retour au calme. Adapte au matériel du joueur : souvent un ballon, quelques plots ou bouteilles, un mur, un terrain en terre ou la plage, parfois sous la chaleur.
- Suis ses progrès : demande comment s'est passée la séance ou le match, ajuste la charge, félicite les efforts.
- Donne aussi des conseils pour ses vidéos de recrutement sur {$app} (actions à filmer, durée, cadrage) quand c'est utile.

Sécurité, obligatoire :
- Tu n'es pas médecin. Douleur forte, blessure, malaise, douleur à la poitrine, essoufflement anormal : dis d'arrêter et de consulter un médecin ou un kiné. Ne pose pas de diagnostic.
- Jamais de dopage, de produits interdits ni de régime extrême. Rappelle de boire de l'eau, surtout sous la chaleur.
- Pour un joueur de moins de 16 ans : charges légères, pas de musculation avec charges lourdes, priorité au jeu, à la technique et au plaisir.
- Reste sur le football et la vie du sportif. Si on te demande autre chose, ramène poliment la conversation à l'entraînement.

Style :
- Réponds en {$lang}, avec le tutoiement, des phrases simples et courtes : beaucoup de joueurs lisent sur un petit téléphone.
- Réponses courtes (150 à 250 mots), sauf pour un programme complet. Utilise des listes avec « - » et mets les mots importants en **gras**. Pas de tableaux.
- Termine souvent par une question ou un défi pour la prochaine séance.

Ce que l'on sait du joueur :
{$profile}
TXT;
    }

    private function profile(array $me): string
    {
        $lines = ['- Prénom ou nom : '.($me['name'] ?? 'inconnu')];
        $roles = ['athlete' => 'joueur', 'fan' => 'supporter', 'recruiter' => 'recruteur / club / agent'];
        if (! empty($me['role'])) {
            $lines[] = '- Type de compte : '.($roles[$me['role']] ?? $me['role']);
        }
        if (! empty($me['athlete_id'])) {
            try {
                $a = $this->data->athlete($me['athlete_id']);
                foreach (['age' => 'Âge', 'city' => 'Ville', 'country' => 'Pays', 'club' => 'Club'] as $k => $label) {
                    if (! empty($a[$k])) {
                        $lines[] = "- {$label} : ".(is_array($a[$k]) ? implode(', ', $a[$k]) : $a[$k]);
                    }
                }
                foreach ((array) ($a['fields'] ?? []) as $label => $v) {
                    if ($v !== null && $v !== '' && ! is_array($v)) {
                        $lines[] = "- {$label} : {$v}";
                    }
                }
            } catch (Throwable) {
            }
        }
        $last = app(Coaching::class)->lastStats($me['id']);
        if ($last) {
            $stats = json_decode((string) $last->stats, true) ?: [];
            if (! empty($stats['_objectif'])) {
                $lines[] = '- Objectif choisi : '.$stats['_objectif'];
            }
            if (! empty($stats['_notes'])) {
                $lines[] = '- Auto-évaluation (1 à 5) : '.collect($stats['_notes'])->map(fn ($v, $k) => (Coaching::QUALITIES[$k] ?? $k).' '.$v)->implode(', ');
            }
            $nums = array_filter($stats, fn ($v, $k) => ! str_starts_with((string) $k, '_') && ! is_array($v), ARRAY_FILTER_USE_BOTH);
            if ($nums) {
                $lines[] = '- Chiffres de la saison : '.collect($nums)->map(fn ($v, $k) => "{$k} {$v}")->implode(', ');
            }
            $plan = json_decode((string) $last->plan, true) ?: [];
            if (! empty($plan['findings'])) {
                $lines[] = '- Diagnostic du programme gratuit : '.implode(' ', array_slice((array) $plan['findings'], 0, 4));
            }
            if (! empty($plan['priorities'])) {
                $lines[] = '- Priorités du programme gratuit : '.collect($plan['priorities'])->pluck('title')->filter()->implode(', ');
            }
        }

        return implode("\n", $lines);
    }

    private function fakeReply(string $message): string
    {
        return "Bien reçu ! (réponse simulée, mode test)\n\nVoici une séance de **30 minutes** pour commencer :\n- **Échauffement** : 5 min de course légère + mobilité\n- **Conduite de balle** : 4 × 30 m entre des plots, pied droit puis pied gauche\n- **Frappes** : 3 séries de 8 tirs après un contrôle orienté\n- **Retour au calme** : étirements, et bois de l'eau\n\nDis-moi : sur quel poste joues-tu, et combien de séances peux-tu faire par semaine ?";
    }

    /** Petit rendu sûr du texte du coach : **gras**, listes « - », paragraphes. */
    public static function html(string $text): string
    {
        $out = [];
        $list = false;
        foreach (preg_split('/\r?\n/', trim($text)) as $line) {
            $t = trim($line);
            $e = e($t);
            $e = preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', $e);
            $e = preg_replace('/^#{1,4}\s*(.+)$/u', '<b>$1</b>', $e);
            if (preg_match('/^(?:[-•*]|\d+[.)])\s+(.*)$/u', $e, $m)) {
                if (! $list) {
                    $out[] = '<ul>';
                    $list = true;
                }
                $out[] = '<li>'.$m[1].'</li>';
                continue;
            }
            if ($list) {
                $out[] = '</ul>';
                $list = false;
            }
            if ($t !== '') {
                $out[] = '<p>'.$e.'</p>';
            }
        }
        if ($list) {
            $out[] = '</ul>';
        }

        return implode('', $out);
    }
}
