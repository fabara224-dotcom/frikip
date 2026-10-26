<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * La partie « protection » de l'algorithme : ce qui est refusé, masqué ou bloqué automatiquement.
 * Les seuils et les listes de mots se règlent dans Admin > Algorithme (classe Algorithm).
 *
 *  - mots interdits : refusés dès l'envoi (titres, commentaires, messages, profils, offres) ;
 *  - analyse des images des vidéos (Sightengine, facultative) : la nudité est bloquée avant publication ;
 *  - signalements : une vidéo signalée par N personnes est retirée en attendant la décision d'un admin ;
 *  - avertissements : suspension puis blocage définitif ; nudité et mise en danger de mineurs = blocage immédiat.
 */
class ContentGuard
{
    public function __construct(private Algorithm $algo, private Notifier $notifier, private Moderation $moderation)
    {
    }

    // ------------------------------------------------------------------ mots interdits

    /** Texte simplifié : minuscules, sans accents, chiffres « déguisés » remplacés (p0rn0 → porno). */
    public static function normalize(string $text): string
    {
        $t = Str::lower(Str::ascii($text));
        $t = strtr($t, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '€' => 'e', '’' => "'"]);
        $t = preg_replace("/[^a-z0-9'.\/ ]+/", ' ', $t);

        return ' '.preg_replace('/\s+/', ' ', trim($t)).' ';
    }

    /** @return array{rule: string, word: string}|null la première règle enfreinte */
    public function check(?string ...$texts): ?array
    {
        $all = self::normalize(implode(' ', array_filter($texts, fn ($t) => is_string($t) && $t !== '')));
        if (trim($all) === '') {
            return null;
        }
        foreach (['nudite', 'haine', 'violence', 'arnaque', 'spam'] as $rule) {
            foreach (preg_split('/\R/u', (string) $this->algo->get("words_{$rule}")) ?: [] as $word) {
                $w = trim(self::normalize($word));
                if ($w === '') {
                    continue;
                }
                // mot ou expression entière (« porno » bloque « porno » mais pas « opportunité »)
                if (preg_match('/(?<![a-z])'.str_replace('\ ', '\s+', preg_quote($w, '/')).'(?![a-z])/', $all)) {
                    return ['rule' => $rule, 'word' => $word];
                }
            }
        }

        return null;
    }

    /**
     * Refuse l'envoi si un texte contient un mot interdit, et garde une trace pour l'admin.
     *
     * @param  array<string, ?string>  $fields  champ => texte
     *
     * @throws ValidationException
     */
    public function enforce(array $fields, string $targetType, ?string $userKey = null): void
    {
        foreach ($fields as $field => $text) {
            $hit = $this->check($text);
            if (! $hit) {
                continue;
            }
            $this->event('texte_refuse', $hit['rule'], $userKey, $targetType, null, Str::limit((string) $text, 280), null, 'approuve');
            throw ValidationException::withMessages([$field => __('Ce texte n’est pas autorisé sur :app (règle : :rule). Modifie-le pour continuer.', [
                'app' => config('terrain.app_name'), 'rule' => __(Algorithm::RULES[$hit['rule']]),
            ])]);
        }
    }

    /** Comme enforce(), mais renvoie le message d'erreur au lieu de lever une exception (réponses JSON). */
    public function refusal(array $fields, string $targetType, ?string $userKey = null): ?string
    {
        try {
            $this->enforce($fields, $targetType, $userKey);
        } catch (ValidationException $e) {
            return (string) collect($e->errors())->flatten()->first();
        }

        return null;
    }

    // ------------------------------------------------------------------ analyse des images

    public static function imageCheckConfigured(): bool
    {
        return (string) config('terrain.sightengine.user') !== '' && (string) config('terrain.sightengine.secret') !== '';
    }

    /** Probabilité de contenu sexuel d'une image (0 à 1), ou null si l'analyse n'est pas possible. */
    public function nudityScore(string $imagePath): ?float
    {
        if (! self::imageCheckConfigured() || ! is_file($imagePath)) {
            return null;
        }
        try {
            $r = Http::timeout(30)->attach('media', file_get_contents($imagePath), basename($imagePath))
                ->post('https://api.sightengine.com/1.0/check.json', [
                    'models' => 'nudity-2.1', 'api_user' => config('terrain.sightengine.user'), 'api_secret' => config('terrain.sightengine.secret'),
                ]);
            if (! $r->successful() || $r->json('status') !== 'success') {
                Log::warning('Analyse d’image impossible : '.Str::limit($r->body(), 200));

                return null;
            }

            // Seules les classes explicites comptent (les tenues de sport, maillots de bain et torses nus ne sont pas visés)
            return (float) max($r->json('nudity.sexual_activity', 0), $r->json('nudity.sexual_display', 0), $r->json('nudity.erotica', 0));
        } catch (Throwable $e) {
            Log::warning('Analyse d’image impossible : '.$e->getMessage());

            return null;
        }
    }

    /** Analyse 3 images prises dans la vidéo (début, milieu, fin) ; renvoie le score le plus élevé. */
    public function videoNudityScore(string $videoPath): ?float
    {
        if (! self::imageCheckConfigured() || ! is_file($videoPath) || ! $this->ffmpeg()) {
            return null;
        }
        $dir = storage_path('app/analyse-'.Str::random(8));
        @mkdir($dir, 0755, true);
        $best = null;
        try {
            $dur = (float) trim((string) @shell_exec($this->ffmpeg('ffprobe').' -v error -show_entries format=duration -of csv=p=0 '.escapeshellarg($videoPath).' 2>/dev/null')) ?: 10;
            foreach ([0.15, 0.5, 0.85] as $i => $at) {
                $img = "{$dir}/f{$i}.jpg";
                @shell_exec($this->ffmpeg().' -v error -y -ss '.round($dur * $at, 2).' -i '.escapeshellarg($videoPath).' -frames:v 1 -vf scale=640:-2 '.escapeshellarg($img).' 2>/dev/null');
                $s = $this->nudityScore($img);
                if ($s !== null) {
                    $best = max($best ?? 0, $s);
                }
            }
        } finally {
            foreach (glob($dir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }

        return $best;
    }

    private function ffmpeg(string $bin = 'ffmpeg'): ?string
    {
        foreach (["/opt/homebrew/bin/{$bin}", "/usr/local/bin/{$bin}", "/usr/bin/{$bin}"] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * À appeler juste après la publication d'une vidéo : validation des nouveaux comptes, puis analyse d'image.
     *
     * @return string|null message pour le sportif si la vidéo n'est pas publique tout de suite
     */
    public function afterPublish(string $videoId, ?string $userKey, ?string $athleteId, ?string $file): ?string
    {
        // Analyse d'image : la nudité est bloquée avant d'être vue
        if ($file && $this->algo->get('image_check')) {
            $score = $this->videoNudityScore($file);
            if ($score !== null && $score >= (float) $this->algo->get('nudity_threshold')) {
                $this->moderation->apply('hide_video', $videoId, 'Analyse automatique : nudité ('.round($score, 2).')', 'nudite');
                $this->event('image', 'nudite', $userKey, 'video', $videoId, null, $score, 'a_traiter');
                if ($userKey) {
                    $this->strike($userKey, 'nudite', 'image', 'video', $videoId, 'Analyse automatique, score '.round($score, 2));
                }

                return __('Ta vidéo a été bloquée : elle ne respecte pas les règles de :app.', ['app' => config('terrain.app_name')]);
            }
        }
        // Vidéos des nouveaux comptes : en attente de l'accord d'un admin (si ce réglage est activé)
        if ($this->algo->get('premoderate_new') && $userKey && $this->isNewAccount($userKey)) {
            $this->moderation->apply('pending_video', $videoId, 'Nouveau compte : vidéo à valider', null);
            $this->event('validation', null, $userKey, 'video', $videoId, null, null, 'a_traiter');

            return __('Merci ! Ta vidéo sera visible après une vérification rapide par l’équipe.');
        }

        return null;
    }

    private function isNewAccount(string $userKey): bool
    {
        try {
            $created = DB::table('members')->where('user_key', $userKey)->value('created_at')
                ?? DB::table('accounts')->where('id', $userKey)->value('created_at');

            return ! $created || strtotime($created) > time() - (int) $this->algo->get('new_account_days') * 86400;
        } catch (Throwable) {
            return true;
        }
    }

    // ------------------------------------------------------------------ signalements

    /** Après un signalement : une vidéo signalée par assez de personnes différentes est retirée en attendant l'admin. */
    public function onReport(string $targetType, ?string $targetId): void
    {
        if ($targetType !== 'video' || ! $targetId) {
            return;
        }
        try {
            $n = DB::table('reports')->where('target_type', 'video')->where('target_id', $targetId)->where('status', 'nouveau')
                ->whereNotNull('reporter_key')->distinct()->count('reporter_key');
            if ($n >= max(1, (int) $this->algo->get('report_threshold')) && ! $this->moderation->isVideoHidden($targetId)) {
                $this->moderation->apply('hide_video', $targetId, "Retirée automatiquement après {$n} signalements", 'signalements');
                $owner = $this->videoOwner($targetId);
                $this->event('signalements', 'signalements', $owner, 'video', $targetId, null, (float) $n, 'a_traiter');
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function videoOwner(string $videoId): ?string
    {
        try {
            $athlete = DB::table('local_videos')->where('id', $videoId)->value('athlete_id');

            return $athlete ? (DB::table('members')->where('athlete_id', $athlete)->value('user_key') ?? 'u-'.$athlete) : null;
        } catch (Throwable) {
            return null;
        }
    }

    // ------------------------------------------------------------------ avertissements et sanctions

    /**
     * Donne un avertissement et applique la sanction prévue par les réglages.
     *
     * @return string la sanction appliquée : avertissement, suspension ou blocage
     */
    public function strike(string $userKey, string $rule, string $source, ?string $targetType = null, ?string $targetId = null, ?string $note = null): string
    {
        DB::table('member_strikes')->insert([
            'user_key' => $userKey, 'rule' => $rule, 'source' => $source, 'target_type' => $targetType, 'target_id' => $targetId,
            'note' => $note ? Str::limit($note, 480) : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $count = DB::table('member_strikes')->where('user_key', $userKey)->whereNull('revoked_at')->count();
        $label = __(Algorithm::RULES[$rule] ?? $rule);
        if (in_array($rule, Algorithm::ZERO_TOLERANCE, true) || $count >= max(1, (int) $this->algo->get('warnings_before_ban'))) {
            $this->ban($userKey, $rule);

            return 'blocage';
        }
        if ($count >= max(1, (int) $this->algo->get('warnings_before_suspension'))) {
            $days = max(1, (int) $this->algo->get('suspension_days'));
            $this->suspend($userKey, $days, $rule);

            return 'suspension';
        }
        $this->notifier->send($userKey, 'moderation', __('Avertissement : :rule', ['rule' => $label]),
            __('Un de tes contenus enfreint les règles de :app. Au prochain manquement, ton compte sera suspendu.', ['app' => config('terrain.app_name')]), route('terms'));

        return 'avertissement';
    }

    public function suspend(string $userKey, int $days, string $rule): void
    {
        $until = now()->addDays($days);
        foreach ($this->ids($userKey) as $id) {
            DB::table('moderation_actions')->where('type', 'suspend_account')->where('target_id', $id)->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            DB::table('moderation_actions')->insert(['type' => 'suspend_account', 'target_id' => $id, 'reason' => Algorithm::RULES[$rule] ?? $rule,
                'rule' => $rule, 'until' => $until, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->notifier->send($userKey, 'moderation', __('Compte suspendu :n jours', ['n' => $days]),
            __('Règle enfreinte : :rule. Tu pourras te reconnecter le :d. Si c’est une erreur, contacte le support.', ['rule' => __(Algorithm::RULES[$rule] ?? $rule), 'd' => $until->format('d/m/Y')]), route('support'));
    }

    public function ban(string $userKey, string $rule): void
    {
        foreach ($this->ids($userKey) as $id) {
            $this->moderation->apply('block_account', $id, Algorithm::RULES[$rule] ?? $rule, $rule);
        }
        $this->notifier->send($userKey, 'moderation', __('Compte bloqué'),
            __('Règle enfreinte : :rule. Si tu penses que c’est une erreur, contacte le support.', ['rule' => __(Algorithm::RULES[$rule] ?? $rule)]), route('support'));
    }

    /** Le compte et son profil sportif (les sanctions s'appliquent aux deux). */
    private function ids(string $userKey): array
    {
        $ids = [$userKey];
        try {
            $athlete = DB::table('members')->where('user_key', $userKey)->value('athlete_id') ?? DB::table('accounts')->where('id', $userKey)->value('athlete_id');
            if ($athlete) {
                $ids[] = $athlete;
            }
        } catch (Throwable) {
        }

        return array_values(array_unique($ids));
    }

    public function event(string $kind, ?string $rule, ?string $userKey, ?string $targetType, ?string $targetId, ?string $excerpt, ?float $score, string $status = 'a_traiter'): void
    {
        try {
            DB::table('moderation_events')->insert([
                'kind' => $kind, 'rule' => $rule, 'user_key' => $userKey, 'target_type' => $targetType, 'target_id' => $targetId,
                'excerpt' => $excerpt ? Str::limit($excerpt, 280) : null, 'score' => $score, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
