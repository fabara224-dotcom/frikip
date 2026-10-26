<?php

namespace App\Terrain;

use App\Terrain\Growth\Members;
use App\Terrain\Growth\Notifier;
use App\Terrain\Growth\Verifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * VRAIS COMPTES dans la base du site (sans Supabase).
 *
 *  - Inscription par e-mail + mot de passe ou par numéro de téléphone + code SMS ;
 *  - profils sportifs, vidéos, offres, candidatures, messages et appels enregistrés pour tous les visiteurs ;
 *  - les profils d'exemple (« Profil fictif ») restent visibles pour remplir le fil (TERRAIN_EXEMPLES=false pour les cacher)
 *    mais on ne peut pas leur écrire : seuls les vrais comptes reçoivent des messages.
 *
 * Choix du mode dans .env : sans SUPABASE_URL, TERRAIN_COMPTES=reels (par défaut) ou TERRAIN_COMPTES=demo.
 */
class LocalData extends DemoData
{
    public function isDemo(): bool
    {
        return false;
    }

    // ------------------------------------------------------------------ comptes

    private function account(?string $id): ?object
    {
        if (! $id) {
            return null;
        }
        try {
            return DB::table('accounts')->where('id', $id)->first();
        } catch (Throwable) {
            return null;
        }
    }

    /** Fiche « utilisateur » (même format que les autres modes), recalculée à chaque requête. */
    private function toUser(object $a): array
    {
        $m = null;
        try {
            $m = DB::table('members')->where('user_key', $a->id)->first();
        } catch (Throwable) {
        }
        $verif = app(Verifications::class);

        return [
            'id' => $a->id, 'role' => $a->role, 'email' => $a->email, 'phone' => $a->phone, 'name' => $a->name,
            'athlete_id' => $a->athlete_id, 'org_type' => $a->org_type,
            'premium' => $m && $m->premium_until && Carbon::parse($m->premium_until)->isFuture(),
            'verified' => $verif->isVerified($a->id) || ($a->athlete_id && $verif->isVerified($a->athlete_id)),
            'real' => true,
        ];
    }

    private function login(object $a): array
    {
        DB::table('accounts')->where('id', $a->id)->update(['last_login_at' => now()]);
        session()->regenerate();
        session(['terrain.account' => $a->id]);
        session()->forget('terrain.user');
        $this->cache = null;
        $this->memo = [];

        return $this->toUser($a);
    }

    public function signIn(string $email, string $password): array
    {
        $a = DB::table('accounts')->where('email', Str::lower(trim($email)))->first();
        if (! $a || ! $a->password || ! Hash::check($password, $a->password)) {
            throw new RuntimeException('E-mail ou mot de passe incorrect.');
        }

        return $this->login($a);
    }

    /** Connexion après vérification du code SMS (le numéro est déjà prouvé). @return array|null null = numéro inconnu */
    public function signInByPhone(string $phone): ?array
    {
        $a = DB::table('accounts')->where('phone', $phone)->first();

        return $a ? $this->login($a) : null;
    }

    public function demoLogin(string $role): array
    {
        throw new RuntimeException('Connexion de démonstration désactivée : le site utilise de vrais comptes.');
    }

    public function signUp(string $email, string $password, string $role, array $profile): array
    {
        $phone = $profile['phone'] ?? null;
        $email = str_ends_with($email, '@telephone.local') ? null : Str::lower(trim($email));
        if ($email && DB::table('accounts')->where('email', $email)->exists()) {
            throw new RuntimeException('Cet e-mail est déjà utilisé. Connecte-toi ou utilise « Mot de passe oublié ».');
        }
        if ($phone && DB::table('accounts')->where('phone', $phone)->exists()) {
            throw new RuntimeException('Ce numéro a déjà un compte.');
        }
        $name = trim((string) ($profile['name'] ?? '')) ?: 'Membre';
        $now = now();
        $account = DB::transaction(function () use ($email, $password, $role, $profile, $phone, $name, $now) {
            $athleteId = null;
            if ($role === 'athlete') {
                $athleteId = 'n-'.Str::lower(Str::random(10));
                $id = 'u-'.$athleteId;
                DB::table('local_athletes')->insert([
                    'id' => $athleteId, 'account_id' => $id, 'name' => $name, 'sport' => $profile['sport'] ?? 'Football',
                    'age' => (int) ($profile['age'] ?? 18), 'country' => $profile['country'] ?? 'Guinée', 'city' => $profile['city'] ?? null,
                    'fields' => json_encode($profile['fields'] ?? [], JSON_UNESCAPED_UNICODE), 'guardian_phone' => $profile['guardian_phone'] ?? null,
                    'level' => 'Amateur', 'availability' => 'Immédiate', 'created_at' => $now, 'updated_at' => $now,
                ]);
            } else {
                $id = 'r-'.Str::lower(Str::random(10));
            }
            DB::table('accounts')->insert([
                'id' => $id, 'role' => $role, 'name' => $name, 'email' => $email, 'phone' => $phone,
                'password' => $email ? Hash::make($password) : null,   // compte par téléphone : connexion par code SMS
                'athlete_id' => $athleteId, 'org_type' => $profile['org_type'] ?? null, 'created_at' => $now, 'updated_at' => $now,
            ]);

            return DB::table('accounts')->where('id', $id)->first();
        });

        return $this->login($account);
    }

    public function signOut(): void
    {
        session()->forget(['terrain.account', 'terrain.user']);
        $this->cache = null;
    }

    private array $memo = [];

    public function user(): ?array
    {
        $id = session('terrain.account');
        if (! $id) {
            return null;
        }
        if (! array_key_exists($id, $this->memo)) {
            $a = $this->account($id);
            $this->memo[$id] = $a ? $this->toUser($a) : null;
        }
        if ($this->memo[$id] === null) {
            session()->forget('terrain.account');   // compte supprimé
        }

        return $this->memo[$id];
    }

    public function deleteAccount(): void
    {
        $u = $this->user();
        if (! $u) {
            return;
        }
        self::purgeAccount($u['id'], $u['athlete_id'] ?? null);
        session()->forget(['terrain.account', 'terrain.user', 'demo']);
    }

    /** Supprime un compte et tout ce qui lui appartient (utilisé par le membre lui-même et par l'admin). */
    public static function purgeAccount(string $accountId, ?string $athleteId): void
    {
        $contact = $athleteId ?: $accountId;
        DB::transaction(function () use ($accountId, $athleteId, $contact) {
            if ($athleteId) {
                foreach (DB::table('local_videos')->where('athlete_id', $athleteId)->get() as $v) {
                    if (! str_starts_with($v->url, 'http')) {
                        @unlink(public_path($v->url));
                    }
                    if ($v->poster && ! str_starts_with($v->poster, 'http')) {
                        @unlink(public_path($v->poster));
                    }
                }
                DB::table('local_videos')->where('athlete_id', $athleteId)->delete();
                DB::table('local_athletes')->where('id', $athleteId)->delete();
                DB::table('local_applications')->where('athlete_id', $athleteId)->delete();
                DB::table('follows_local')->where('athlete_id', $athleteId)->delete();
                DB::table('challenge_entries')->where('athlete_id', $athleteId)->delete();
            }
            DB::table('local_offers')->where('owner_key', $accountId)->delete();
            DB::table('follows_local')->where('follower_key', $accountId)->delete();
            DB::table('push_subscriptions')->where('user_key', $accountId)->delete();
            DB::table('demo_messages')->where('room', \App\Terrain\Messaging\LocalMessenger::ROOM)
                ->where(fn ($q) => $q->where('sender', $contact)->orWhere('recipient', $contact))->delete();
            DB::table('video_comments_local')->where('user_key', $accountId)->delete();
            DB::table('video_likes_local')->where('user_key', $accountId)->delete();
            DB::table('notifications_feed')->where('user_key', $accountId)->delete();
            DB::table('accounts')->where('id', $accountId)->delete();
            DB::table('members')->where('user_key', $accountId)->delete();
        });
    }

    /** Modification du profil (nom, ville, club, fiche du sport…). */
    public function updateProfile(array $data): void
    {
        $u = $this->user();
        if (! $u) {
            throw new RuntimeException('Connecte-toi.');
        }
        $acc = array_filter(['name' => $data['name'] ?? null, 'org_type' => $data['org_type'] ?? null], fn ($v) => $v !== null);
        if ($acc) {
            DB::table('accounts')->where('id', $u['id'])->update($acc + ['updated_at' => now()]);
        }
        $this->memo = [];
        if ($u['athlete_id']) {
            $keys = ['name', 'age', 'country', 'city', 'height', 'weight', 'level', 'club', 'availability', 'specialty', 'guardian_phone'];
            $row = array_intersect_key($data, array_flip($keys));
            if (isset($data['fields'])) {
                $row['fields'] = json_encode($data['fields'], JSON_UNESCAPED_UNICODE);
            }
            DB::table('local_athletes')->where('id', $u['athlete_id'])->update($row + ['updated_at' => now()]);
        }
        $this->cache = null;
    }

    public function changePassword(string $accountId, string $password): void
    {
        DB::table('accounts')->where('id', $accountId)->update(['password' => Hash::make($password), 'updated_at' => now()]);
    }

    // ------------------------------------------------------------------ sportifs

    /** Vrais profils (les plus récents d'abord) + profils d'exemple si TERRAIN_EXEMPLES n'est pas à false. */
    protected function sourceAthletes(): array
    {
        $real = [];
        try {
            foreach (DB::table('local_athletes')->orderByDesc('created_at')->get() as $r) {
                $fields = json_decode((string) $r->fields, true) ?: [];
                $filled = count(array_filter([$r->city, $r->height, $r->weight, $r->club, $r->specialty])) + count(array_filter($fields, fn ($v) => $v !== '–' && $v !== ''));
                $real[] = [
                    'id' => $r->id, 'name' => $r->name, 'sport' => $r->sport, 'age' => (int) $r->age, 'country' => $r->country,
                    'city' => $r->city ?: '–', 'height' => $r->height ?: '–', 'weight' => $r->weight ?: '–', 'level' => $r->level ?: 'Amateur',
                    'club' => $r->club ?: 'Sans club', 'availability' => $r->availability ?: 'Immédiate', 'specialty' => $r->specialty,
                    'verified' => false, 'score' => min(90, 55 + $filled * 3), 'fields' => $fields, 'videos' => [], 'real' => true,
                    'guardian_phone' => $r->guardian_phone,
                ];
            }
        } catch (Throwable) {
        }

        return $real;   // uniquement les vrais membres : aucun profil d'exemple
    }

    // ------------------------------------------------------------------ matchs saisis sur le site

    /**
     * Matchs locaux = matchs publiés dans « En direct » par les clubs et l'admin (score tenu à jour par l'organisateur).
     * Les grands championnats viennent d'API-Football (App\Terrain\RealScores).
     */
    public function matches(): array
    {
        try {
            $me = $this->user()['id'] ?? null;
            $rows = DB::table('live_streams')->where(fn ($q) => $q->where('approved', true)->when($me, fn ($q2) => $q2->orWhere('owner_key', $me)))
                ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'youtube'))
                ->where('starts_at', '>', now()->subDays(30))->orderByDesc('starts_at')->limit(60)->get();
        } catch (Throwable) {
            return [];
        }
        $votes = $this->s('votes', []);

        return $rows->map(fn ($r) => [
            'id' => 'l-'.$r->id, 'live_id' => $r->id, 'sport' => $r->sport ?: 'Football', 'competition' => $r->competition ?: $r->title,
            'home' => $r->home, 'away' => $r->away ?: '', 'hs' => (int) $r->home_score, 'as' => (int) $r->away_score,
            'status' => ['direct' => 'live', 'termine' => 'finished'][$r->status] ?? 'upcoming',
            'minute' => (int) preg_replace('/\D/', '', (string) $r->minute), 'period' => null,
            'date_label' => $r->starts_at ? \Illuminate\Support\Carbon::parse($r->starts_at)->translatedFormat('D j M · H:i') : '',
            'ts' => $r->starts_at ? \Illuminate\Support\Carbon::parse($r->starts_at)->getTimestamp() : 0,
            'result' => null, 'events' => [], 'stats' => [], 'ratings' => [], 'candidates' => [], 'votes' => [], 'my_vote' => $votes['l-'.$r->id] ?? null,
            'organizer' => $me && $r->owner_key === $me, 'venue' => $r->city ?: '', 'referee' => '',
        ])->all();
    }

    public function standings(): array
    {
        return [];   // les classements réels viennent d'API-Football (onglet de chaque compétition)
    }

    public function organizerScore(string $matchId, string $side, int $points): void
    {
        $id = (int) substr($matchId, 2);
        $col = $side === 'a' ? 'away_score' : 'home_score';
        DB::table('live_streams')->where('id', $id)->where('owner_key', $this->user()['id'] ?? '')->increment($col, max(1, $points), ['updated_at' => now()]);
    }

    public function organizerStatus(string $matchId, string $status): void
    {
        $id = (int) substr($matchId, 2);
        DB::table('live_streams')->where('id', $id)->where('owner_key', $this->user()['id'] ?? '')
            ->update(['status' => ['live' => 'direct', 'finished' => 'termine'][$status] ?? 'prevu', 'updated_at' => now()]);
    }

    /** Les signalements sont enregistrés par MeController dans la file de modération. */
    public function report(string $targetType, string $targetId, string $reason): void
    {
    }

    public function boosted(): bool
    {
        $id = $this->user()['athlete_id'] ?? null;

        return $id && app(Members::class)->isBoosted($id);
    }

    public function isPremium(): bool
    {
        return app(Members::class)->isPremium();
    }

    /** Les droits payés sont gardés dans la table members (Growth\Payments). */
    public function activate(string $product): void
    {
    }

    /** Ce contact est-il un vrai compte (qui peut recevoir des messages) ? */
    public function isRealContact(string $contactId): bool
    {
        try {
            return DB::table('accounts')->where(fn ($q) => $q->where('athlete_id', $contactId)->orWhere(fn ($q) => $q->where('id', $contactId)->where('role', 'recruiter')))->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function person(string $id): array
    {
        try {
            $a = DB::table('accounts')->where('id', $id)->where('role', 'recruiter')->first();
            if ($a) {
                $v = app(Verifications::class)->isVerified($a->id);

                return ['id' => $id, 'name' => $a->name, 'sport' => $a->org_type ?: '', 'verified' => $v, 'minor' => false, 'real' => true];
            }
        } catch (Throwable) {
        }
        $p = parent::person($id);
        $p['real'] = $this->isRealContact($id);

        return $p;
    }

    // ------------------------------------------------------------------ offres (vraies, enregistrées pour tous)

    public function offers(): array
    {
        $u = $this->user();
        $me = $u['athlete_id'] ?? null;
        $verif = app(Verifications::class);
        $out = [];
        try {
            $apps = DB::table('local_applications')->get()->groupBy('offer_id');
            foreach (DB::table('local_offers')->orderByDesc('created_at')->get() as $o) {
                $cands = isset($apps[$o->id]) ? $apps[$o->id]->pluck('athlete_id')->all() : [];
                $out[] = [
                    'id' => $o->id, 'organization' => $o->organization, 'org_type' => $o->org_type, 'sport' => $o->sport, 'title' => $o->title,
                    'place' => $o->place ?: '–', 'deadline' => $o->deadline ?: '–', 'criteria' => $o->criteria ?: 'Ouvert à tous',
                    'verified' => $verif->isVerified($o->owner_key), 'mine' => $u && $o->owner_key === $u['id'] && $u['role'] === 'recruiter',
                    'candidates' => $cands, 'applied' => $me && in_array($me, $cands, true), 'owner_key' => $o->owner_key,
                ];
            }
        } catch (Throwable) {
        }

        return $out;
    }

    public function createOffer(array $data): void
    {
        $u = $this->user();
        DB::table('local_offers')->insert([
            'id' => 'o-'.Str::lower(Str::random(10)), 'owner_key' => $u['id'], 'organization' => $u['name'], 'org_type' => $u['org_type'] ?: 'Club',
            'sport' => $data['sport'], 'title' => $data['title'], 'place' => $data['place'] ?? null, 'deadline' => $data['deadline'] ?? null,
            'criteria' => $data['criteria'] ?? null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function apply(string $offerId, ?string $videoId, string $message): void
    {
        $u = $this->user();
        $o = DB::table('local_offers')->where('id', $offerId)->first();
        if (! $o || empty($u['athlete_id'])) {
            throw new RuntimeException('Offre introuvable.');
        }
        DB::table('local_applications')->updateOrInsert(['offer_id' => $offerId, 'athlete_id' => $u['athlete_id']],
            ['video_id' => $videoId, 'message' => $message, 'created_at' => now(), 'updated_at' => now()]);
        // Le club est prévenu tout de suite
        app(Notifier::class)->send($o->owner_key, 'offer', $u['name'].' a postulé : '.$o->title,
            $message !== '' ? Str::limit($message, 120) : 'Voir son profil et ses vidéos.', route('athletes.show', $u['athlete_id']));
    }
}
