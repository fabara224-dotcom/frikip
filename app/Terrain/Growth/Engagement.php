<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Chiffres réels des vidéos (j'aime, commentaires, partages, vues), abonnés et étoile bleue,
 * commentaires avec réponses. Tout est compté dans la base du site : aucun chiffre inventé.
 */
class Engagement
{
    /** Cache pour la durée de la requête */
    private ?array $counts = null;

    private ?array $followers = null;

    private array $metaCache = [];

    public function reset(): void
    {
        $this->counts = null;
        $this->followers = null;
        $this->metaCache = [];
    }

    // ------------------------------------------------------------------ compteurs

    /** @return array<string, array{likes:int, comments:int, shares:int, views:int}> */
    private function counts(): array
    {
        if ($this->counts !== null) {
            return $this->counts;
        }
        $out = [];
        $add = function (string $field, iterable $rows) use (&$out) {
            foreach ($rows as $vid => $n) {
                $out[$vid] ??= ['likes' => 0, 'comments' => 0, 'shares' => 0, 'views' => 0];
                $out[$vid][$field] = (int) $n;
            }
        };
        try {
            $add('likes', DB::table('video_likes_local')->selectRaw('video_id, count(*) as n')->groupBy('video_id')->pluck('n', 'video_id'));
            $hidden = DB::table('comment_meta')->where('hidden', true)->pluck('comment_id')->all();
            $add('comments', DB::table('video_comments_local')->when($hidden, fn ($q) => $q->whereNotIn('id', $hidden))
                ->selectRaw('video_id, count(*) as n')->groupBy('video_id')->pluck('n', 'video_id'));
            $add('shares', DB::table('video_shares')->selectRaw('video_id, count(*) as n')->groupBy('video_id')->pluck('n', 'video_id'));
            $add('views', DB::table('video_views')->selectRaw('video_id, count(*) as n')->groupBy('video_id')->pluck('n', 'video_id'));
        } catch (Throwable) {
            // tables pas encore créées (php artisan migrate) : tout reste à 0
        }

        return $this->counts = $out;
    }

    public function of(string $videoId): array
    {
        return $this->counts()[$videoId] ?? ['likes' => 0, 'comments' => 0, 'shares' => 0, 'views' => 0];
    }

    /** Vidéos aimées par un utilisateur (mode démo). */
    public function likedBy(?string $userKey): array
    {
        if (! $userKey) {
            return [];
        }
        try {
            return DB::table('video_likes_local')->where('user_key', $userKey)->pluck('video_id')->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function toggleLike(string $videoId, string $userKey): bool
    {
        $q = DB::table('video_likes_local')->where('video_id', $videoId)->where('user_key', $userKey);
        if ($q->exists()) {
            $q->delete();
            $on = false;
        } else {
            DB::table('video_likes_local')->insertOrIgnore(['video_id' => $videoId, 'user_key' => $userKey, 'created_at' => now()]);
            $on = true;
        }
        $this->reset();

        return $on;
    }

    /** Un partage compte une fois par personne, par vidéo et par canal chaque jour. */
    public function share(string $videoId, string $viewerKey, string $channel): int
    {
        $channel = in_array($channel, ['whatsapp', 'natif', 'lien', 'facebook'], true) ? $channel : 'lien';
        $dup = DB::table('video_shares')->where('video_id', $videoId)->where('user_key', $viewerKey)->where('channel', $channel)
            ->where('created_at', '>', now()->subDay())->exists();
        if (! $dup) {
            DB::table('video_shares')->insert(['video_id' => $videoId, 'user_key' => $viewerKey, 'channel' => $channel, 'created_at' => now()]);
        }
        $this->reset();

        return $this->of($videoId)['shares'];
    }

    /** Une vue compte une fois par personne et par vidéo toutes les 30 minutes (après 2 secondes de lecture). */
    public function view(string $videoId, string $athleteId, string $viewerKey): void
    {
        try {
            $dup = DB::table('video_views')->where('video_id', $videoId)->where('viewer_key', $viewerKey)
                ->where('created_at', '>', now()->subMinutes(30))->exists();
            if (! $dup) {
                DB::table('video_views')->insert(['video_id' => $videoId, 'athlete_id' => $athleteId, 'viewer_key' => $viewerKey, 'created_at' => now()]);
            }
        } catch (Throwable) {
        }
    }

    /** Identifiant du visiteur : son compte, sinon une empreinte anonyme (IP + navigateur). */
    public static function viewerKey(?array $user): string
    {
        return $user['id'] ?? ('anon:'.substr(sha1(request()->ip().'|'.request()->userAgent()), 0, 16));
    }

    // ------------------------------------------------------------------ abonnés et étoile bleue

    public static function starThreshold(): int
    {
        return max(1, (int) config('terrain.badge_followers', 1000));
    }

    /** @return array<string, int> abonnés par sportif (copie locale des abonnements) */
    public function followers(): array
    {
        if ($this->followers !== null) {
            return $this->followers;
        }
        try {
            return $this->followers = DB::table('follows_local')->selectRaw('athlete_id, count(*) as n')->groupBy('athlete_id')->pluck('n', 'athlete_id')->map(fn ($n) => (int) $n)->all();
        } catch (Throwable) {
            return $this->followers = [];
        }
    }

    public function followersOf(string $athleteId): int
    {
        return $this->followers()[$athleteId] ?? 0;
    }

    public function hasStar(string $athleteId): bool
    {
        return $this->followersOf($athleteId) >= self::starThreshold();
    }

    /** Ajoute « followers » et « star » (étoile bleue) à chaque sportif. Les nombres d'abonnés externes (Supabase) peuvent être passés. */
    public function decorateAthletes(array $list, ?array $external = null): array
    {
        $f = $external ?? $this->followers();
        $min = self::starThreshold();
        foreach ($list as &$a) {
            $a['followers'] = (int) ($f[$a['id']] ?? 0);
            $a['star'] = $a['followers'] >= $min;
        }

        return $list;
    }

    // ------------------------------------------------------------------ langue et son des vidéos

    /** @return array<string, object> */
    public function meta(array $videoIds): array
    {
        $missing = array_values(array_diff($videoIds, array_keys($this->metaCache)));
        if ($missing) {
            try {
                foreach (array_chunk($missing, 500) as $chunk) {
                    foreach (DB::table('video_meta')->whereIn('video_id', $chunk)->get() as $m) {
                        $this->metaCache[$m->video_id] = $m;
                    }
                }
            } catch (Throwable) {
            }
            foreach ($missing as $id) {
                $this->metaCache[$id] ??= null;
            }
        }

        return array_filter(array_intersect_key($this->metaCache, array_flip($videoIds)));
    }

    public function saveMeta(string $videoId, array $values): void
    {
        DB::table('video_meta')->updateOrInsert(['video_id' => $videoId], $values + ['updated_at' => now(), 'created_at' => now()]);
        unset($this->metaCache[$videoId]);
    }

    /**
     * Ajoute aux vidéos les vrais chiffres, la langue parlée, le son, les titres traduits et les sous-titres.
     * $local = true : j'aime et commentaires comptés dans la base du site (mode démo).
     */
    public function decorateVideos(array $list, bool $local, ?string $userKey): array
    {
        $ids = [];
        foreach ($list as $a) {
            foreach ($a['videos'] as $v) {
                $ids[] = $v['id'];
            }
        }
        $meta = $this->meta($ids);
        $tracks = app(Subtitles::class)->available($ids);
        $liked = $local ? $this->likedBy($userKey) : [];
        foreach ($list as &$a) {
            foreach ($a['videos'] as &$v) {
                $c = $this->of($v['id']);
                if ($local) {
                    $v['likes'] = $c['likes'];
                    $v['comments'] = $c['comments'];
                    $v['views'] = $c['views'];
                    $v['liked'] = in_array($v['id'], $liked, true);
                } else {
                    $v['views'] = (int) ($v['views'] ?? 0) + $c['views'];
                }
                $v['shares'] = $c['shares'];
                if ($m = $meta[$v['id']] ?? null) {
                    $v['lang'] = $m->lang;
                    $v['has_audio'] = (bool) $m->has_audio;
                    $v['subtitle_status'] = $m->subtitle_status;
                    $v['i18n'] = [
                        'title' => array_filter(['fr' => $m->title_fr, 'en' => $m->title_en]),
                        'caption' => array_filter(['fr' => $m->caption_fr, 'en' => $m->caption_en]),
                    ];
                }
                $v['lang'] ??= 'none';
                $v['has_audio'] ??= true;
                $v['tracks'] = $tracks[$v['id']] ?? [];
            }
            unset($v);
        }
        unset($a);

        return $list;
    }

    // ------------------------------------------------------------------ commentaires (stockage local, mode démo)

    public function localComments(string $videoId): array
    {
        return DB::table('video_comments_local')->where('video_id', $videoId)->orderBy('id')->limit(500)->get()
            ->map(fn ($c) => [
                'id' => (string) $c->id, 'parent_id' => $c->parent_id ? (string) $c->parent_id : null, 'user_key' => $c->user_key,
                'author' => $c->author_name, 'role' => $c->author_role, 'athlete_id' => $c->author_athlete_id, 'text' => $c->body, 'created_at' => $c->created_at,
            ])->all();
    }

    public function addLocalComment(string $videoId, array $user, string $text, ?string $parentId): array
    {
        $id = DB::table('video_comments_local')->insertGetId([
            'video_id' => $videoId, 'parent_id' => $parentId ? (int) $parentId : null, 'user_key' => $user['id'], 'author_name' => $user['name'],
            'author_role' => $user['role'] ?? 'athlete', 'author_athlete_id' => $user['athlete_id'] ?? null, 'body' => $text,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->reset();

        return [
            'id' => (string) $id, 'parent_id' => $parentId, 'user_key' => $user['id'], 'author' => $user['name'],
            'role' => $user['role'] ?? 'athlete', 'athlete_id' => $user['athlete_id'] ?? null, 'text' => $text, 'created_at' => now()->toDateTimeString(),
        ];
    }

    public function findLocalComment(string $id): ?object
    {
        return ctype_digit($id) ? DB::table('video_comments_local')->find((int) $id) : null;
    }

    public function deleteLocalComment(string $id): void
    {
        DB::table('video_comments_local')->where('parent_id', (int) $id)->delete();
        DB::table('video_comments_local')->where('id', (int) $id)->delete();
        $this->reset();
    }

    // ------------------------------------------------------------------ actions sur les commentaires (deux modes)

    public function toggleCommentLike(string $commentId, string $userKey): array
    {
        $q = DB::table('comment_likes')->where('comment_id', $commentId)->where('user_key', $userKey);
        if ($q->exists()) {
            $q->delete();
            $on = false;
        } else {
            DB::table('comment_likes')->insertOrIgnore(['comment_id' => $commentId, 'user_key' => $userKey, 'created_at' => now()]);
            $on = true;
        }

        return ['liked' => $on, 'likes' => DB::table('comment_likes')->where('comment_id', $commentId)->count()];
    }

    public function setPinned(string $videoId, string $commentId, bool $on): void
    {
        if ($on) {
            DB::table('comment_meta')->where('video_id', $videoId)->update(['pinned' => false]); // un seul commentaire épinglé
        }
        DB::table('comment_meta')->updateOrInsert(['comment_id' => $commentId], ['video_id' => $videoId, 'pinned' => $on, 'updated_at' => now(), 'created_at' => now()]);
    }

    public function hide(string $videoId, string $commentId): void
    {
        DB::table('comment_meta')->updateOrInsert(['comment_id' => $commentId], ['video_id' => $videoId, 'hidden' => true, 'pinned' => false, 'updated_at' => now(), 'created_at' => now()]);
        $this->reset();
    }

    /**
     * Met en forme les commentaires pour l'affichage : réponses rangées sous leur commentaire,
     * j'aime, droits (supprimer, épingler), étoile bleue et rôle de l'auteur.
     *
     * @param  list<array>  $rows  commentaires bruts (id, parent_id, user_key, author, role, athlete_id, text, created_at)
     */
    public function present(array $rows, ?string $ownerAthleteId, ?array $me, string $sort = 'top'): array
    {
        $ids = array_column($rows, 'id');
        $meta = $ids ? DB::table('comment_meta')->whereIn('comment_id', $ids)->get()->keyBy('comment_id') : collect();
        $likes = $ids ? DB::table('comment_likes')->whereIn('comment_id', $ids)->selectRaw('comment_id, count(*) as n')->groupBy('comment_id')->pluck('n', 'comment_id')->all() : [];
        $mine = ($ids && $me) ? DB::table('comment_likes')->whereIn('comment_id', $ids)->where('user_key', $me['id'])->pluck('comment_id')->all() : [];
        $iAmOwner = $me && $ownerAthleteId && ($me['athlete_id'] ?? null) === $ownerAthleteId;
        $members = app(Members::class);

        $byId = [];
        foreach ($rows as $r) {
            $m = $meta[$r['id']] ?? null;
            if ($m && $m->hidden) {
                continue;
            }
            if (($r['role'] ?? null) === null || ($r['athlete_id'] ?? null) === null) {
                $mem = $members->find($r['user_key']);
                $r['role'] ??= $mem->role ?? 'athlete';
                $r['athlete_id'] ??= $mem->athlete_id ?? null;
            }
            $isMine = $me && $me['id'] === $r['user_key'];
            $byId[$r['id']] = [
                'id' => $r['id'], 'parent_id' => $r['parent_id'], 'author' => $r['author'],
                'profile' => $r['athlete_id'] ? route('athletes.show', $r['athlete_id']) : null,
                'star' => $r['athlete_id'] ? $this->hasStar($r['athlete_id']) : false,
                'recruiter' => ($r['role'] ?? '') === 'recruiter',
                'is_owner' => $ownerAthleteId && $r['athlete_id'] === $ownerAthleteId,
                'text' => $r['text'], 'ts' => strtotime((string) $r['created_at']) ?: time(),
                'when' => Carbon::parse($r['created_at'])->diffForHumans(),
                'likes' => (int) ($likes[$r['id']] ?? 0), 'liked' => in_array($r['id'], $mine, true),
                'mine' => $isMine, 'can_delete' => $isMine || $iAmOwner, 'can_pin' => $iAmOwner && ! $r['parent_id'],
                'pinned' => (bool) ($m->pinned ?? false), 'replies' => [],
            ];
        }
        $top = [];
        foreach ($byId as $id => $c) {
            if ($c['parent_id'] && isset($byId[$c['parent_id']])) {
                $byId[$c['parent_id']]['replies'][] = $c;
            } elseif (! $c['parent_id']) {
                $top[] = $id;
            }
        }
        $list = array_map(fn ($id) => $byId[$id], $top);
        foreach ($list as &$c) {
            usort($c['replies'], fn ($x, $y) => $x['ts'] <=> $y['ts']);
            $c['reply_count'] = count($c['replies']);
        }
        unset($c);
        usort($list, function ($x, $y) use ($sort) {
            if ($x['pinned'] !== $y['pinned']) {
                return $y['pinned'] <=> $x['pinned'];
            }
            if ($sort === 'top') {
                $sx = $x['likes'] * 3 + $x['reply_count'] * 2 + ($x['recruiter'] ? 2 : 0);
                $sy = $y['likes'] * 3 + $y['reply_count'] * 2 + ($y['recruiter'] ? 2 : 0);
                if ($sx !== $sy) {
                    return $sy <=> $sx;
                }
            }

            return $y['ts'] <=> $x['ts'];
        });

        return $list;
    }
}
