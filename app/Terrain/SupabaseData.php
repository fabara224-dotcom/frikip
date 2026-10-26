<?php

namespace App\Terrain;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * VRAIES DONNÉES : même base Supabase que l'app Flutter.
 * Le site agit au nom de l'utilisateur connecté (son jeton), donc les règles de sécurité
 * de la base (RLS, schema.sql) s'appliquent exactement comme dans l'app.
 * Tables utilisées : voir supabase/schema.sql, confidentialite.sql et web.sql.
 */
class SupabaseData implements DataSource
{
    private string $url;

    private string $key;

    public function __construct()
    {
        $this->url = (string) config('terrain.supabase_url');
        $this->key = (string) config('terrain.supabase_anon_key');
    }

    public function isDemo(): bool
    {
        return false;
    }

    // ------------------------------------------------------------------ HTTP

    private function token(): ?string
    {
        $token = session('terrain.token');
        if ($token && session('terrain.expires', 0) < time() + 60 && session('terrain.refresh')) {
            $r = Http::withHeaders(['apikey' => $this->key])->acceptJson()->timeout(15)
                ->post("{$this->url}/auth/v1/token?grant_type=refresh_token", ['refresh_token' => session('terrain.refresh')]);
            if ($r->successful()) {
                $this->storeSession($r->json());
                $token = $r->json('access_token');
            } else {
                session()->forget(['terrain.user', 'terrain.token', 'terrain.refresh', 'terrain.expires']);

                return null;
            }
        }

        return $token;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders(['apikey' => $this->key])
            ->withToken($this->token() ?? $this->key)
            ->acceptJson()
            ->timeout(20);
    }

    private function get(string $table, array $query = []): array
    {
        $r = $this->http()->get("{$this->url}/rest/v1/{$table}", $query);
        if (! $r->successful()) {
            throw new RuntimeException("Lecture {$table} impossible ({$r->status()})");
        }

        return $r->json() ?? [];
    }

    private function insert(string $table, array $row, bool $ignoreDuplicates = false): void
    {
        $r = $this->http()
            ->withHeaders(['Prefer' => $ignoreDuplicates ? 'resolution=ignore-duplicates,return=minimal' : 'return=minimal'])
            ->post("{$this->url}/rest/v1/{$table}", $row);
        if (! $r->successful()) {
            throw new RuntimeException("Écriture {$table} impossible ({$r->status()}) : ".$r->body());
        }
    }

    /** Insertion qui renvoie la ligne créée (pour connaître son identifiant). */
    private function insertReturning(string $table, array $row): array
    {
        $r = $this->http()->withHeaders(['Prefer' => 'return=representation'])->post("{$this->url}/rest/v1/{$table}", $row);
        if (! $r->successful()) {
            throw new RuntimeException("Écriture {$table} impossible ({$r->status()}) : ".$r->body());
        }

        return $r->json()[0] ?? [];
    }

    private function patch(string $table, array $filter, array $values): void
    {
        $r = $this->http()->withHeaders(['Prefer' => 'return=minimal'])
            ->patch("{$this->url}/rest/v1/{$table}?".http_build_query($filter), $values);
        if (! $r->successful()) {
            throw new RuntimeException("Mise à jour {$table} impossible ({$r->status()})");
        }
    }

    private function delete(string $table, array $filter): void
    {
        $this->http()->delete("{$this->url}/rest/v1/{$table}?".http_build_query($filter));
    }

    /** Journal d'activité (sert aux recommandations) : une erreur ici ne bloque jamais l'action. */
    private function logEvent(array $row): void
    {
        try {
            $this->insert('events', $row);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function uid(): ?string
    {
        return session('terrain.user.id');
    }

    private function storeSession(array $s): void
    {
        session([
            'terrain.token' => $s['access_token'],
            'terrain.refresh' => $s['refresh_token'] ?? session('terrain.refresh'),
            'terrain.expires' => time() + (int) ($s['expires_in'] ?? 3600),
        ]);
    }

    // ------------------------------------------------------------------ comptes

    public function signIn(string $email, string $password): array
    {
        $r = Http::withHeaders(['apikey' => $this->key])->acceptJson()->timeout(15)
            ->post("{$this->url}/auth/v1/token?grant_type=password", compact('email', 'password'));
        if (! $r->successful()) {
            throw new RuntimeException('Identifiants invalides');
        }
        return $this->finishLogin($r->json(), $email);
    }

    /** Après authentification (e-mail ou téléphone) : crée le compte au premier passage et ouvre la session. */
    private function finishLogin(array $auth, ?string $fallbackName = null, bool $allowIncomplete = false): array
    {
        session()->regenerate();
        $this->storeSession($auth);
        $uid = $auth['user']['id'];
        $meta = $auth['user']['user_metadata'] ?? [];
        session(['terrain.user' => ['id' => $uid]]);

        $account = $this->get('accounts', ['id' => "eq.{$uid}", 'select' => '*'])[0] ?? null;
        if (! $account && $allowIncomplete && empty($meta['role'])) {
            // Connexion par téléphone d'un nouveau numéro : le profil sera complété à l'étape suivante.
            session(['terrain.pending_phone_user' => $uid]);

            return ['id' => $uid, 'incomplete' => true];
        }
        if (! $account) {
            $account = ['id' => $uid, 'role' => $meta['role'] ?? 'athlete', 'display_name' => $meta['name'] ?? $fallbackName ?? 'Membre', 'verified' => false, 'premium_until' => null];
            $this->insert('accounts', ['id' => $uid, 'role' => $account['role'], 'display_name' => $account['display_name']]);
        }
        $athlete = $this->get('athletes', ['user_id' => "eq.{$uid}", 'select' => 'id'])[0] ?? null;
        if (! $athlete && $account['role'] === 'athlete' && ! empty($meta['profile'])) {
            $this->insert('athletes', ['user_id' => $uid] + $meta['profile']);
            $athlete = $this->get('athletes', ['user_id' => "eq.{$uid}", 'select' => 'id'])[0] ?? null;
        }

        $user = [
            'id' => $uid, 'role' => $account['role'], 'email' => $auth['user']['email'] ?? null, 'phone' => $auth['user']['phone'] ?? null,
            'name' => $account['display_name'], 'athlete_id' => $athlete['id'] ?? null,
            'premium' => ! empty($account['premium_until']) && strtotime($account['premium_until']) > time(),
            'verified' => (bool) $account['verified'],
        ];
        session(['terrain.user' => $user]);

        return $user;
    }

    // ---- Connexion par téléphone (Supabase envoie le code par SMS : Authentication > Providers > Phone)

    public function phoneOtp(string $phone): void
    {
        $r = Http::withHeaders(['apikey' => $this->key])->acceptJson()->timeout(15)->post("{$this->url}/auth/v1/otp", ['phone' => $phone, 'create_user' => true]);
        if (! $r->successful()) {
            throw new RuntimeException((string) ($r->json('msg') ?? $r->json('error_description') ?? 'Envoi du SMS impossible'));
        }
    }

    /** @return array utilisateur, ou ['incomplete' => true] pour un nouveau numéro */
    public function phoneVerify(string $phone, string $code): array
    {
        $r = Http::withHeaders(['apikey' => $this->key])->acceptJson()->timeout(15)->post("{$this->url}/auth/v1/verify", ['type' => 'sms', 'phone' => $phone, 'token' => $code]);
        if (! $r->successful() || ! $r->json('access_token')) {
            throw new RuntimeException('Code incorrect ou expiré');
        }

        return $this->finishLogin($r->json(), $phone, true);
    }

    /** Termine l'inscription d'un nouveau numéro (rôle, nom, profil sportif). */
    public function phoneComplete(string $role, array $profile): array
    {
        $uid = session('terrain.pending_phone_user');
        if (! $uid || ! $this->token()) {
            throw new RuntimeException('Session expirée');
        }
        $this->insert('accounts', ['id' => $uid, 'role' => $role, 'display_name' => $profile['name']]);
        if ($role === 'athlete') {
            $this->insert('athletes', ['user_id' => $uid, 'full_name' => $profile['name'], 'sport' => $profile['sport'], 'age' => (int) $profile['age'],
                'country' => $profile['country'], 'city' => $profile['city'] ?? null, 'guardian_phone' => $profile['guardian_phone'] ?? null, 'sport_fields' => (object) []]);
        }
        session()->forget('terrain.pending_phone_user');
        $athlete = $role === 'athlete' ? ($this->get('athletes', ['user_id' => "eq.{$uid}", 'select' => 'id'])[0] ?? null) : null;
        $user = ['id' => $uid, 'role' => $role, 'email' => null, 'phone' => $profile['phone'] ?? null, 'name' => $profile['name'],
            'athlete_id' => $athlete['id'] ?? null, 'premium' => false, 'verified' => false];
        session(['terrain.user' => $user]);

        return $user;
    }

    public function signUp(string $email, string $password, string $role, array $profile): array
    {
        $athleteRow = null;
        if ($role === 'athlete') {
            $athleteRow = [
                'full_name' => $profile['name'], 'sport' => $profile['sport'], 'age' => (int) $profile['age'],
                'country' => $profile['country'], 'city' => $profile['city'] ?? null,
                'guardian_phone' => $profile['guardian_phone'] ?? null, 'sport_fields' => $profile['fields'] ?? (object) [],
            ];
        }
        $r = Http::withHeaders(['apikey' => $this->key])->acceptJson()->timeout(15)
            ->post("{$this->url}/auth/v1/signup", [
                'email' => $email, 'password' => $password,
                'data' => ['role' => $role, 'name' => $profile['name'], 'profile' => $athleteRow],
            ]);
        if (! $r->successful()) {
            throw new RuntimeException((string) ($r->json('msg') ?? $r->json('error_description') ?? 'Inscription impossible'));
        }
        if (! $r->json('access_token')) {
            // Confirmation de l'e-mail activée dans Supabase : le profil sera créé à la première connexion.
            throw new ConfirmEmailException();
        }

        return $this->signIn($email, $password);
    }

    public function signOut(): void
    {
        if ($t = session('terrain.token')) {
            Http::withHeaders(['apikey' => $this->key])->withToken($t)->timeout(10)->post("{$this->url}/auth/v1/logout");
        }
        session()->forget(['terrain.user', 'terrain.token', 'terrain.refresh', 'terrain.expires']);
    }

    public function user(): ?array
    {
        $u = session('terrain.user');

        return ($u && isset($u['role']) && $this->token()) ? $u : null;
    }

    public function deleteAccount(): void
    {
        $r = $this->http()->timeout(60)->post("{$this->url}/functions/v1/delete-account", ['source' => 'web']);
        if (! $r->successful()) {
            throw new RuntimeException('Suppression impossible');
        }
        session()->forget(['terrain.user', 'terrain.token', 'terrain.refresh', 'terrain.expires']);
    }

    // ------------------------------------------------------------------ sportifs et vidéos

    private function mapAthlete(array $r, array $myLikes): array
    {
        $videos = array_map(function ($v) use ($myLikes) {
            return [
                'id' => $v['id'], 'athlete_id' => $v['athlete_id'], 'title' => $v['title'],
                'caption' => $v['caption'] ?? $v['title'], 'duration' => $v['duration'] ?? '', 'url' => $v['url'],
                'poster' => $v['poster'] ?? null, 'views' => (int) ($v['views'] ?? 0), 'likes' => (int) ($v['video_likes'][0]['count'] ?? 0),
                'liked' => in_array($v['id'], $myLikes, true), 'comments' => (int) ($v['video_comments'][0]['count'] ?? 0),
                'created_at' => $v['created_at'] ?? '',
            ];
        }, $r['videos'] ?? []);
        usort($videos, fn ($x, $y) => $y['created_at'] <=> $x['created_at']);

        return [
            'id' => $r['id'], 'contact_id' => $r['user_id'], 'name' => $r['full_name'], 'sport' => $r['sport'], 'age' => (int) $r['age'],
            'country' => $r['country'] ?? '', 'city' => $r['city'] ?? '', 'height' => $r['height'] ?? '–', 'weight' => $r['weight'] ?? '–',
            'level' => $r['level'] ?? 'Amateur', 'club' => $r['club'] ?? 'Sans club', 'availability' => $r['availability'] ?? '',
            'verified' => (bool) ($r['verified'] ?? false), 'score' => (int) ($r['score'] ?? 60),
            'specialty' => $r['specialty'] ?? '', 'boosted' => (! empty($r['boosted_until']) && strtotime($r['boosted_until']) > time()) || app(\App\Terrain\Growth\Members::class)->isBoosted($r['id']),
            'fields' => array_map('strval', (array) ($r['sport_fields'] ?? [])), 'videos' => $videos,
        ];
    }

    private function myLikes(): array
    {
        if (! $this->uid()) {
            return [];
        }

        return array_column($this->get('video_likes', ['user_id' => 'eq.'.$this->uid(), 'select' => 'video_id']), 'video_id');
    }

    private function allAthletes(array $query = []): array
    {
        $rows = $this->get('athletes', $query + ['select' => '*,videos(*,video_likes(count),video_comments(count))', 'order' => 'score.desc', 'limit' => 300]);
        $likes = $this->myLikes();

        $list = array_map(fn ($r) => $this->mapAthlete($r, $likes), $rows);

        // Vrais chiffres complétés par le site (partages, vues sur le site), langue, sous-titres, abonnés et étoile bleue
        $eng = app(\App\Terrain\Growth\Engagement::class);
        $list = $eng->decorateAthletes($eng->decorateVideos($list, false, $this->uid()), $this->followerCounts());

        // Décisions du site : badges validés par l'admin, comptes bloqués et vidéos masquées
        return app(\App\Terrain\Growth\Moderation::class)->filterAthletes(app(\App\Terrain\Growth\Verifications::class)->applyToAthletes($list));
    }

    public function athletes(array $filters = []): array
    {
        return Filters::apply($this->allAthletes(), $filters);
    }

    public function athlete(string $id): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->allAthletes(['id' => "eq.{$id}"])[0] ?? null;
    }

    public function feed(string $sport = 'Tous'): array
    {
        $items = [];
        foreach ($this->allAthletes($sport !== 'Tous' ? ['sport' => "eq.{$sport}"] : []) as $a) {
            foreach ($a['videos'] as $v) {
                $fresh = $v['created_at'] && strtotime($v['created_at']) > time() - 3 * 86400 ? 50 : 0; // coup de pouce aux nouvelles vidéos
                $items[] = ['athlete' => $a, 'video' => $v, 'rank' => ($a['boosted'] ? 1e9 : 0) + $fresh + $v['likes'] * ($a['verified'] ? 1.2 : 1)];
            }
        }
        usort($items, fn ($x, $y) => $y['rank'] <=> $x['rank']);

        return $items;
    }

    public function toggleLike(string $videoId): array
    {
        $uid = $this->uid();
        $exists = $this->get('video_likes', ['user_id' => "eq.{$uid}", 'video_id' => "eq.{$videoId}", 'select' => 'video_id']);
        if ($exists) {
            $this->delete('video_likes', ['user_id' => "eq.{$uid}", 'video_id' => "eq.{$videoId}"]);
        } else {
            $this->insert('video_likes', ['user_id' => $uid, 'video_id' => $videoId], true);
            $this->logEvent(['user_id' => $uid, 'type' => 'like', 'video_id' => $videoId]);
        }
        $count = $this->get('videos', ['id' => "eq.{$videoId}", 'select' => 'video_likes(count)'])[0]['video_likes'][0]['count'] ?? 0;

        return ['liked' => ! $exists, 'likes' => (int) $count];
    }

    /**
     * Abonnés par sportif : vue « follower_counts » (supabase/engagement.sql).
     * Sans cette vue, on se rabat sur les abonnements faits depuis le site.
     */
    private function followerCounts(): ?array
    {
        static $counts = false;
        if ($counts !== false) {
            return $counts;
        }
        try {
            $rows = $this->get('follower_counts', ['select' => 'athlete_id,followers']);

            return $counts = array_column(array_map(fn ($r) => [$r['athlete_id'], (int) $r['followers']], $rows), 1, 0);
        } catch (\Throwable) {
            return $counts = null;
        }
    }

    private function mapComment(array $c): array
    {
        return [
            'id' => (string) $c['id'], 'parent_id' => $c['parent_id'] ?? null, 'user_key' => $c['user_id'], 'author' => $c['author_name'],
            'role' => null, 'athlete_id' => null, 'text' => $c['body'], 'created_at' => $c['created_at'], 'video_id' => $c['video_id'] ?? null,
        ];
    }

    public function comments(string $videoId): array
    {
        $q = ['video_id' => "eq.{$videoId}", 'order' => 'created_at.asc', 'limit' => 500];
        try {
            $rows = $this->get('video_comments', $q + ['select' => 'id,parent_id,user_id,author_name,body,created_at']);
        } catch (\Throwable) {
            // colonne parent_id absente (supabase/engagement.sql pas encore exécuté) : pas de réponses
            $rows = $this->get('video_comments', $q + ['select' => 'id,user_id,author_name,body,created_at']);
        }

        return array_map(fn ($c) => $this->mapComment($c), $rows);
    }

    public function addComment(string $videoId, string $text, ?string $parentId = null): array
    {
        $u = $this->user();
        $row = ['video_id' => $videoId, 'user_id' => $u['id'], 'author_name' => $u['name'], 'verified' => (bool) $u['verified'], 'body' => $text];
        try {
            $c = $this->insertReturning('video_comments', $row + ($parentId ? ['parent_id' => $parentId] : []));
        } catch (\Throwable $e) {
            if (! $parentId) {
                throw $e;
            }
            $c = $this->insertReturning('video_comments', $row); // sans la colonne parent_id
        }

        return $this->mapComment($c + $row) + ['role' => $u['role'], 'athlete_id' => $u['athlete_id']];
    }

    public function comment(string $commentId): ?array
    {
        if (! Str::isUuid($commentId)) {
            return null;
        }
        $c = $this->get('video_comments', ['id' => "eq.{$commentId}", 'select' => 'id,video_id,user_id'])[0] ?? null;

        return $c ? ['id' => $c['id'], 'video_id' => $c['video_id'], 'user_key' => $c['user_id'], 'parent_id' => null] : null;
    }

    public function deleteComment(string $commentId): bool
    {
        $c = $this->comment($commentId);
        if (! $c || $c['user_key'] !== $this->uid()) {
            return false;
        }
        $this->delete('video_comments', ['id' => "eq.{$commentId}", 'user_id' => 'eq.'.$this->uid()]);

        return true;
    }

    public function videoOwner(string $videoId): ?string
    {
        if (! Str::isUuid($videoId)) {
            return null;
        }

        return $this->get('videos', ['id' => "eq.{$videoId}", 'select' => 'athlete_id'])[0]['athlete_id'] ?? null;
    }

    public function toggleFollow(string $athleteId): bool
    {
        $uid = $this->uid();
        $exists = $this->get('follows', ['user_id' => "eq.{$uid}", 'athlete_id' => "eq.{$athleteId}", 'select' => 'athlete_id']);
        if ($exists) {
            $this->delete('follows', ['user_id' => "eq.{$uid}", 'athlete_id' => "eq.{$athleteId}"]);

            return false;
        }
        $this->insert('follows', ['user_id' => $uid, 'athlete_id' => $athleteId], true);

        return true;
    }

    public function following(): array
    {
        if (! $this->uid()) {
            return [];
        }

        return array_column($this->get('follows', ['user_id' => 'eq.'.$this->uid(), 'select' => 'athlete_id']), 'athlete_id');
    }

    public function publishVideo(array $meta, ?UploadedFile $file): ?string
    {
        $athleteId = $this->user()['athlete_id'] ?? null;
        if (! $athleteId || ! $file) {
            throw new RuntimeException('Profil sportif ou fichier manquant');
        }
        $path = $athleteId.'/'.time().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$file->getClientOriginalExtension();
        $r = Http::withHeaders(['apikey' => $this->key, 'x-upsert' => 'false'])
            ->withToken($this->token())
            ->timeout(300)
            ->withBody(\GuzzleHttp\Psr7\Utils::streamFor(fopen($file->getRealPath(), 'r')), $file->getMimeType() ?: 'video/mp4')
            ->post("{$this->url}/storage/v1/object/videos/{$path}");
        if (! $r->successful()) {
            throw new RuntimeException('Envoi de la vidéo impossible ('.$r->status().')');
        }
        $row = $this->insertReturning('videos', [
            'athlete_id' => $athleteId, 'title' => $meta['title'], 'caption' => $meta['caption'] ?: null,
            'competition' => $meta['competition'] ?? null, 'duration' => $meta['duration'] ?? null,
            'url' => "{$this->url}/storage/v1/object/public/videos/{$path}",
        ]);

        return $row['id'] ?? null;
    }

    // ------------------------------------------------------------------ matchs

    public function matches(): array
    {
        $rows = $this->get('matches', ['select' => '*,match_events(*),player_ratings(*),match_votes(candidate,user_id)', 'order' => 'starts_at.asc.nullslast', 'limit' => 200]);
        $uid = $this->uid();

        $list = array_map(function ($m) use ($uid) {
            $candidates = (array) ($m['vote_candidates'] ?? []);
            $votes = array_fill(0, count($candidates), 0);
            $mine = null;
            foreach ($m['match_votes'] ?? [] as $v) {
                if (isset($votes[$v['candidate']])) {
                    $votes[$v['candidate']]++;
                }
                if ($uid && $v['user_id'] === $uid) {
                    $mine = (int) $v['candidate'];
                }
            }
            $events = array_map(fn ($e) => ['minute' => (int) $e['minute'], 'side' => $e['side'] === 'home' ? 'h' : 'a', 'type' => $e['type'], 'player' => $e['player_name'], 'athlete_id' => $e['athlete_id']], $m['match_events'] ?? []);
            usort($events, fn ($x, $y) => $x['minute'] <=> $y['minute']);

            return [
                'id' => $m['id'], 'sport' => $m['sport'], 'competition' => $m['competition'], 'home' => $m['home_team'], 'away' => $m['away_team'],
                'hs' => (int) $m['home_score'], 'as' => (int) $m['away_score'], 'status' => $m['status'], 'minute' => (int) $m['minute'],
                'date_label' => $m['date_label'] ?? '', 'result' => $m['result_text'], 'events' => $events,
                'stats' => (array) ($m['stats'] ?? []),
                'ratings' => array_map(fn ($r) => ['athlete_id' => $r['athlete_id'], 'note' => (float) $r['note'], 'detail' => $r['detail'] ?? ''], $m['player_ratings'] ?? []),
                'candidates' => $candidates, 'votes' => $votes, 'my_vote' => $mine,
                'organizer' => $uid && ($m['organizer_id'] ?? null) === $uid,
            ];
        }, $rows);

        return $list;
    }

    public function match(string $id): ?array
    {
        foreach ($this->matches() as $m) {
            if ($m['id'] === $id) {
                return $m;
            }
        }

        return null;
    }

    public function vote(string $matchId, int $index): void
    {
        $this->insert('match_votes', ['match_id' => $matchId, 'user_id' => $this->uid(), 'candidate' => $index], true);
    }

    public function standings(): array
    {
        return Standings::fromMatches($this->matches());
    }

    public function organizerScore(string $matchId, string $side, int $points): void
    {
        $m = $this->get('matches', ['id' => "eq.{$matchId}", 'select' => 'home_score,away_score,status'])[0] ?? null;
        if (! $m) {
            return;
        }
        $col = $side === 'h' ? 'home_score' : 'away_score';
        $values = [$col => (int) $m[$col] + $points];
        if ($m['status'] === 'upcoming') {
            $values += ['status' => 'live', 'minute' => 1];
        }
        $this->patch('matches', ['id' => "eq.{$matchId}"], $values);
    }

    public function organizerStatus(string $matchId, string $status): void
    {
        $this->patch('matches', ['id' => "eq.{$matchId}"], ['status' => $status] + ($status === 'live' ? ['minute' => 1] : []));
    }

    // ------------------------------------------------------------------ offres

    public function offers(): array
    {
        $rows = $this->get('offers', ['select' => '*,applications(athlete_id)', 'order' => 'created_at.desc', 'limit' => 200]);
        $uid = $this->uid();
        $me = $this->user()['athlete_id'] ?? null;

        return array_map(function ($o) use ($uid, $me) {
            $cands = array_column($o['applications'] ?? [], 'athlete_id');

            return [
                'id' => $o['id'], 'organization' => $o['organization'], 'org_type' => $o['org_type'], 'sport' => $o['sport'],
                'title' => $o['title'], 'place' => $o['place'] ?? '', 'deadline' => $o['deadline_label'] ?? '', 'criteria' => $o['criteria'] ?? '',
                'verified' => (bool) $o['verified'], 'mine' => $uid && $o['recruiter_id'] === $uid, 'candidates' => $cands,
                'applied' => $me && in_array($me, $cands, true),
            ];
        }, $rows);
    }

    public function createOffer(array $data): void
    {
        $u = $this->user();
        $this->insert('offers', [
            'recruiter_id' => $u['id'], 'organization' => $u['name'], 'org_type' => 'Club', 'sport' => $data['sport'],
            'title' => $data['title'], 'place' => $data['place'] ?: null, 'deadline_label' => $data['deadline'] ?: null,
            'criteria' => $data['criteria'] ?: null, 'verified' => $u['verified'],
        ]);
    }

    public function apply(string $offerId, ?string $videoId, string $message): void
    {
        $this->insert('applications', [
            'offer_id' => $offerId, 'athlete_id' => $this->user()['athlete_id'], 'video_id' => $videoId, 'message' => $message,
        ], true);
        $this->logEvent(['user_id' => $this->uid(), 'type' => 'candidature', 'meta' => ['offer_id' => $offerId]]);
    }

    // ------------------------------------------------------------------ messages

    private function people(array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $in = 'in.('.implode(',', $ids).')';
        $accounts = collect($this->get('public_accounts', ['id' => $in, 'select' => 'id,display_name,verified']))->keyBy('id');
        $athletes = collect($this->get('athletes', ['user_id' => $in, 'select' => 'user_id,full_name,sport,age']))->keyBy('user_id');
        $out = [];
        foreach ($ids as $id) {
            $a = $athletes[$id] ?? null;
            $acc = $accounts[$id] ?? null;
            $out[$id] = [
                'id' => $id, 'name' => $a['full_name'] ?? $acc['display_name'] ?? 'Utilisateur', 'sport' => $a['sport'] ?? '',
                'verified' => (bool) ($acc['verified'] ?? false), 'minor' => $a && (int) $a['age'] < 18,
            ];
        }

        return $out;
    }

    public function person(string $id): array
    {
        return $this->people([$id])[$id] ?? ['id' => $id, 'name' => 'Utilisateur', 'sport' => '', 'verified' => false, 'minor' => false];
    }

    /** @return array<string, array> fiches courtes de plusieurs personnes */
    public function persons(array $ids): array
    {
        return $this->people(array_values(array_unique(array_filter($ids, fn ($i) => Str::isUuid($i)))));
    }

    /** Requêtes REST authentifiées pour la messagerie (même jeton que le reste du site). */
    public function rest(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http();
    }

    public function baseUrl(): string
    {
        return $this->url;
    }

    public function anonKey(): string
    {
        return $this->key;
    }

    // ------------------------------------------------------------------ compte

    public function activate(string $product): void
    {
        // Le paiement réel (CinetPay, PayDunya…) doit valider l'achat côté serveur avant d'activer quoi que ce soit.
        throw new RuntimeException('Le paiement en ligne n\'est pas encore branché.');
    }

    public function report(string $targetType, string $targetId, string $reason): void
    {
        $this->insert('reports', ['reporter_id' => $this->uid(), 'target_type' => $targetType, 'target_id' => Str::isUuid($targetId) ? $targetId : $this->uid(), 'reason' => $reason]);
    }
}
