<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Engagement;
use App\Terrain\Messaging\Messenger;
use App\Terrain\RealScores;
use App\Terrain\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * API JSON du design Frikip (/api/v1/...), utilisée par l'interface public/frikip-app.
 *
 * Tout vient des vraies données du site : comptes (joueurs, acteurs du foot, supporters, clubs),
 * vidéos, j'aime, commentaires, favoris, abonnements, messagerie, notifications, lives, vidéos Découvrir,
 * scores API-Football. Aucune donnée inventée.
 *
 * Identifiants : un membre qui a une page (joueur, entraîneur, arbitre…) = local_athletes.id ;
 * un supporter, un club ou un recruteur = accounts.id.
 */
class ApiController extends Controller
{
    private const PAGE = 20;
    private const COLORS = ['#16A34A', '#B45309', '#1D4ED8', '#DB2777', '#0891B2', '#7C3AED', '#CA8A04', '#0D9488', '#EA580C', '#475569'];

    public function __construct(private DataSource $data)
    {
    }

    // ================================================================= formats

    private function color(string $id): string
    {
        return self::COLORS[crc32($id) % count(self::COLORS)];
    }

    private function handle(string $name, string $id): string
    {
        return (Str::slug($name, '.') ?: 'membre').'.'.substr(preg_replace('/[^a-z0-9]/', '', strtolower($id)), -4);
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : asset($path);
    }

    private function safe(callable $fn, $fallback)
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return $fallback;
        }
    }

    /** @var array<string, object>|null comptes par identifiant de page */
    private ?array $accountsByAthlete = null;

    private function accountOf(string $athleteId): ?object
    {
        if ($this->accountsByAthlete === null) {
            $this->accountsByAthlete = $this->safe(fn () => DB::table('accounts')->whereNotNull('athlete_id')->get()->keyBy('athlete_id')->all(), []);
        }

        return $this->accountsByAthlete[$athleteId] ?? null;
    }

    private ?array $followingCounts = null;

    private function followingCountOf(?string $accountId): int
    {
        if (! $accountId) {
            return 0;
        }
        $this->followingCounts ??= $this->safe(fn () => DB::table('follows_local')->select('follower_key', DB::raw('count(*) as n'))->groupBy('follower_key')->pluck('n', 'follower_key')->all(), []);

        return (int) ($this->followingCounts[$accountId] ?? 0);
    }

    private ?array $myFollowing = null;

    private function isFollowing(string $id): bool
    {
        $this->myFollowing ??= array_flip($this->data->following());

        return isset($this->myFollowing[$id]);
    }

    /** Libellé du type de profil : Joueur, Entraîneur, Arbitre, Agent de football… */
    private function typeOf(array $a): string
    {
        if (($a['kind'] ?? 'joueur') === 'pro' && ! empty($a['profession'])) {
            return __($a['profession']);
        }

        return __('Joueur');
    }

    /** Membre qui a une page (joueur ou acteur du foot) au format de l'interface. */
    private function userFromAthlete(array $a): array
    {
        $me = $this->data->user();
        $acc = $this->accountOf($a['id']);
        $isPlayer = ($a['kind'] ?? 'joueur') === 'joueur';
        $f = $a['fields'] ?? [];
        $club = ($a['club'] ?? '') && $a['club'] !== 'Sans club' ? $a['club'] : null;

        return [
            'id' => $a['id'], 'accountId' => $acc->id ?? null, 'name' => $a['name'], 'handle' => $this->handle($a['name'], $a['id']),
            'type' => $this->typeOf($a), 'kind' => $a['kind'] ?? 'joueur', 'role' => 'athlete',
            'city' => ($a['city'] ?? '–') !== '–' ? $a['city'] : '', 'country' => $a['country'] ?? '',
            'color' => $this->color($a['id']), 'verified' => (bool) ($a['verified'] ?? false), 'star' => (bool) ($a['star'] ?? false),
            'bio' => trim(implode(' · ', array_filter([$a['specialty'] ?? null, $club]))),
            'managedBy' => ! empty($a['managed_by']) ? trim($a['managed_by'].' '.($a['manager_name'] ?? '')) : null,
            'followers' => (int) ($a['followers'] ?? 0), 'following' => $this->followingCountOf($acc->id ?? null),
            'isMe' => ($me['athlete_id'] ?? null) === $a['id'], 'isFollowing' => $this->isFollowing($a['id']), 'canFollow' => true,
            'club' => $club ? ['name' => $club, 'short' => $this->short($club), 'colors' => $this->clubColors($club)] : null,
            'player' => $isPlayer ? [
                'position' => $f['Poste'] ?? '–', 'foot' => $f['Pied fort'] ?? '–', 'age' => (int) ($a['age'] ?? 0),
                'height' => ($a['height'] ?? '–') ?: '–', 'goals' => $this->num($f['Buts cette saison'] ?? null),
                'assists' => $this->num($f['Passes décisives'] ?? null), 'matches' => $this->num($f['Matchs joués'] ?? null), 'minutes' => null,
            ] : null,
            'photo' => $this->url($acc->avatar_url ?? null), 'cover' => $this->url($acc->cover_url ?? null),
            'profileUrl' => route('athletes.show', $a['id']),
        ];
    }

    /** Supporter, club ou recruteur (sans page de vidéos). */
    private function userFromAccount(object $acc): array
    {
        $u = ['role' => $acc->role ?? null, 'profession' => $acc->profession ?? null, 'org_type' => $acc->org_type ?? null];

        return [
            'id' => $acc->id, 'accountId' => $acc->id, 'name' => $acc->name, 'handle' => $this->handle($acc->name, $acc->id),
            'type' => Roles::labelFor($u), 'kind' => $acc->role ?? 'fan', 'role' => $acc->role ?? 'fan',
            'city' => '', 'country' => '', 'color' => $this->color($acc->id),
            'verified' => app(\App\Terrain\Growth\Verifications::class)->isVerified($acc->id), 'star' => false,
            'bio' => trim(implode(' · ', array_filter([$acc->organization ?? null, ! empty($acc->favorite_club) ? __('Club préféré : :c', ['c' => $acc->favorite_club]) : null]))),
            'managedBy' => null, 'followers' => 0, 'following' => $this->followingCountOf($acc->id),
            'isMe' => ($this->data->user()['id'] ?? null) === $acc->id, 'isFollowing' => false, 'canFollow' => false,
            'club' => null, 'player' => null,
            'photo' => $this->url($acc->avatar_url ?? null), 'cover' => $this->url($acc->cover_url ?? null), 'profileUrl' => null,
        ];
    }

    private function userById(string $id): ?array
    {
        if ($a = $this->data->athlete($id)) {
            return $this->userFromAthlete($a);
        }
        $acc = $this->safe(fn () => DB::table('accounts')->where('id', $id)->first(), null);
        if ($acc) {
            return $acc->athlete_id && ($a = $this->data->athlete($acc->athlete_id)) ? $this->userFromAthlete($a) : $this->userFromAccount($acc);
        }

        return null;
    }

    private function num($v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private function seconds(?string $d): int
    {
        if (! $d) {
            return 0;
        }
        $p = array_map('intval', explode(':', $d));

        return count($p) === 2 ? $p[0] * 60 + $p[1] : (int) $p[0];
    }

    private ?array $mySaves = null;

    private function saveCounts(array $ids): array
    {
        return $this->safe(fn () => DB::table('video_saves')->whereIn('video_id', $ids)->select('video_id', DB::raw('count(*) as n'))->groupBy('video_id')->pluck('n', 'video_id')->all(), []);
    }

    private function isSaved(string $videoId): bool
    {
        if ($this->mySaves === null) {
            $key = $this->data->user()['id'] ?? null;
            $this->mySaves = $key ? array_flip($this->safe(fn () => DB::table('video_saves')->where('user_key', $key)->pluck('video_id')->all(), [])) : [];
        }

        return isset($this->mySaves[$videoId]);
    }

    /** Une vidéo de membre au format de l'interface. */
    private function video(array $a, array $v, array $saves = [], ?array $author = null): array
    {
        $title = trim((string) ($v['title'] ?? ''));
        $extra = trim((string) ($v['caption'] ?? ''));
        $caption = $extra !== '' && $extra !== $title && ! str_contains($title, $extra) ? $title.' '.$extra : $title;

        return [
            'id' => $v['id'], 'source' => 'frikip', 'caption' => $caption, 'sound' => 'Son original · '.$a['name'],
            'url' => $v['url'] ?? null, 'poster' => $v['poster'] ?? null, 'duration' => $this->seconds($v['duration'] ?? null),
            'likes' => (int) ($v['likes'] ?? 0), 'comments' => (int) ($v['comments'] ?? 0), 'shares' => (int) ($v['shares'] ?? 0),
            'views' => (int) ($v['views'] ?? 0), 'saves' => (int) ($saves[$v['id']] ?? 0),
            'liked' => (bool) ($v['liked'] ?? false), 'saved' => $this->isSaved($v['id']),
            'createdAt' => ! empty($v['created_at']) ? Carbon::parse($v['created_at'])->toIso8601String() : now()->toIso8601String(),
            'scene' => 'pitch', 'hue' => $this->color($a['id']),
            'author' => $author ?? $this->userFromAthlete($a),
        ];
    }

    private function videos(array $items): array
    {
        $saves = $this->saveCounts(array_map(fn ($i) => $i['video']['id'], $items));
        $authors = [];

        return array_map(function ($i) use ($saves, &$authors) {
            $authors[$i['athlete']['id']] ??= $this->userFromAthlete($i['athlete']);

            return $this->video($i['athlete'], $i['video'], $saves, $authors[$i['athlete']['id']]);
        }, $items);
    }

    /** Vidéo « Découvrir » (YouTube, chaîne qui autorise l'intégration). */
    private function discoverItem(object $d): array
    {
        $channel = (string) ($d->channel ?: 'YouTube');

        return [
            'id' => 'd-'.$d->id, 'dbId' => (int) $d->id, 'source' => 'youtube', 'youtube' => $d->youtube_id,
            'caption' => (string) $d->title, 'sound' => $channel.' · YouTube', 'poster' => $d->thumbnail ?: 'https://i.ytimg.com/vi/'.$d->youtube_id.'/hqdefault.jpg',
            'url' => null, 'duration' => (int) $d->duration, 'topic' => \App\Terrain\Growth\Discover::topicLabel((string) $d->topic),
            'likes' => 0, 'comments' => 0, 'shares' => 0, 'views' => (int) $d->plays, 'saves' => 0, 'liked' => false, 'saved' => false,
            'createdAt' => Carbon::parse($d->published_at ?? $d->created_at ?? now())->toIso8601String(), 'scene' => 'pitch', 'hue' => '#FF0033',
            'author' => ['id' => 'yt-'.($d->channel_id ?: $d->id), 'name' => $channel, 'handle' => Str::slug($channel, '.'), 'type' => __('Chaîne YouTube'),
                'color' => '#B91C1C', 'verified' => false, 'isMe' => false, 'isFollowing' => false, 'canFollow' => false, 'external' => true,
                'url' => $d->channel_id ? 'https://www.youtube.com/channel/'.$d->channel_id : null],
        ];
    }

    private function short(string $name): string
    {
        $words = array_values(array_filter(preg_split('/[\s\-’\']+/u', $name), fn ($w) => mb_strlen($w) > 2));
        $s = '';
        foreach (array_slice($words ?: [$name], 0, 3) as $w) {
            $s .= mb_substr($w, 0, 1);
        }

        return mb_strtoupper(Str::ascii($s)) ?: 'FC';
    }

    private function clubColors(string $name): array
    {
        return [self::COLORS[crc32($name) % count(self::COLORS)], '#0B0F0D'];
    }

    // ================================================================= compte

    /** Stories de 24 h (photos et vidéos), regroupées par auteur, avec la photo de profil. */
    public function stories(): JsonResponse
    {
        $me = $this->data->user();
        $groups = StoryController::groups($me);
        $keys = array_column($groups, 'key');
        $avatars = [];
        try {
            $avatars = DB::table('accounts')->whereIn('id', $keys)->pluck('avatar_url', 'id')->all();
        } catch (\Throwable) {
        }
        $out = array_map(fn ($g) => [
            'id' => $g['key'], 'name' => $g['name'], 'initials' => $g['initials'], 'mine' => $g['mine'], 'seen' => $g['seen'],
            'avatar' => ! empty($avatars[$g['key']]) ? asset($avatars[$g['key']]) : null, 'profile' => $g['profile'],
            'items' => array_map(fn ($i) => ['id' => $i['id'], 'type' => $i['type'], 'src' => $i['src'], 'caption' => $i['caption'],
                'when' => $i['when'], 'seen' => $i['seen'], 'views' => $g['mine'] ? $i['views'] : null], $g['items']),
        ], $groups);

        return response()->json(['groups' => $out, 'canPost' => (bool) $me]);
    }

    public function me(Messenger $messenger): JsonResponse
    {
        $u = $this->data->user();
        if (! $u) {
            return response()->json(['guest' => true, 'loginUrl' => route('login'), 'registerUrl' => route('register')]);
        }
        $acc = DB::table('accounts')->where('id', $u['id'])->first();
        $me = ! empty($u['athlete_id']) && ($a = $this->data->athlete($u['athlete_id']))
            ? $this->userFromAthlete($a)
            : $this->userFromAccount($acc ?: (object) ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role'], 'org_type' => $u['org_type'] ?? null]);
        $me['isMe'] = true;
        $me['role'] = $u['role'];
        $me['premium'] = (bool) ($u['premium'] ?? false);
        $me['canPublish'] = $u['role'] === 'athlete';
        $me['liveRefusal'] = app(\App\Terrain\Growth\UserLive::class)->refusal($u);
        $me['unread_messages'] = $this->safe(fn () => $messenger->unreadTotal(), 0);
        $me['unread_notifications'] = $this->safe(fn () => DB::table('notifications_feed')->whereIn('user_key', $this->myKeys())->whereNull('read_at')->count(), 0);

        return response()->json($me);
    }

    private function myKeys(): array
    {
        $u = $this->data->user();

        return array_values(array_filter([$u['id'] ?? null, $u['athlete_id'] ?? null]));
    }

    /** Photo de profil (kind=photo) ou de couverture (kind=cover) du compte connecté. */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $u = $this->data->user();
        $data = $request->validate([
            'kind' => ['required', 'in:photo,cover'],
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], ['image.max' => 'Photo trop lourde (8 Mo maximum).', 'image.image' => 'Choisis une photo (JPG, PNG ou WebP).', 'image.mimes' => 'Choisis une photo (JPG, PNG ou WebP).']);
        $dir = public_path('uploads/profils');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = Str::slug($u['id']).'-'.$data['kind'].'-'.Str::lower(Str::random(6)).'.'.$request->file('image')->extension();
        $request->file('image')->move($dir, $name);
        $rel = 'uploads/profils/'.$name;
        $col = $data['kind'] === 'photo' ? 'avatar_url' : 'cover_url';
        $old = DB::table('accounts')->where('id', $u['id'])->value($col);
        DB::table('accounts')->where('id', $u['id'])->update([$col => $rel, 'updated_at' => now()]);
        if ($old && str_starts_with($old, 'uploads/profils/')) {
            @unlink(public_path($old));
        }
        session()->forget('terrain.user');

        return response()->json(['url' => asset($rel)]);
    }

    // ================================================================= fil

    /** Fil : « pour-toi » (algorithme + vidéos Découvrir glissées entre les vidéos) ou « abonnements ». */
    public function feed(Request $request): JsonResponse
    {
        $tab = $request->query('tab') === 'abonnements' ? 'abonnements' : 'pour-toi';
        $page = max(1, (int) $request->query('page', 1));
        $all = $this->data->feed('Tous');
        if ($tab === 'abonnements') {
            $following = array_flip($this->data->following());
            $all = array_values(array_filter($all, fn ($i) => isset($following[$i['athlete']['id']])));
            usort($all, fn ($x, $y) => strcmp((string) ($y['video']['created_at'] ?? ''), (string) ($x['video']['created_at'] ?? '')));
        } else {
            $all = app(\App\Terrain\Growth\Algorithm::class)->orderFeed($all);
        }
        if ($v = (string) $request->query('v', '')) {
            usort($all, fn ($x, $y) => ($y['video']['id'] === $v) <=> ($x['video']['id'] === $v));
        }
        $items = $this->videos(array_slice($all, ($page - 1) * self::PAGE, self::PAGE));

        // Vidéos « Découvrir » (YouTube) glissées dans « Pour toi », une toutes les 3 vidéos
        if ($tab === 'pour-toi') {
            $disc = array_map(fn ($d) => $this->discoverItem($d), $this->safe(fn () => app(\App\Terrain\Growth\Discover::class)->sample(max(4, intdiv(count($items), 3) + 1)), []));
            $out = [];
            foreach ($items as $i => $it) {
                $out[] = $it;
                if (($i + 1) % 3 === 0 && $disc) {
                    $out[] = array_shift($disc);
                }
            }
            $items = $out ?: array_slice($disc, 0, 8);
        }

        return response()->json(['items' => $items, 'next' => $page * self::PAGE < count($all) ? $page + 1 : null]);
    }

    public function like(string $video): JsonResponse
    {
        return response()->json($this->data->toggleLike($video));
    }

    public function save(string $video): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9\-_]{2,64}$/', $video), 404);
        $key = $this->data->user()['id'];
        $q = DB::table('video_saves')->where('user_key', $key)->where('video_id', $video);
        if ($q->exists()) {
            $q->delete();
            $on = false;
        } else {
            DB::table('video_saves')->insertOrIgnore(['user_key' => $key, 'video_id' => $video, 'created_at' => now()]);
            $on = true;
        }

        return response()->json(['saved' => $on, 'saves' => DB::table('video_saves')->where('video_id', $video)->count()]);
    }

    public function share(Request $request, string $video): JsonResponse
    {
        $n = app(Engagement::class)->share($video, Engagement::viewerKey($this->data->user()), (string) $request->input('channel', 'lien'));

        return response()->json(['shares' => $n]);
    }

    private function comment(array $c): array
    {
        $aid = $c['profile'] ? basename(parse_url($c['profile'], PHP_URL_PATH)) : null;

        return [
            'id' => $c['id'], 'text' => $c['text'], 'likes' => (int) $c['likes'], 'liked' => (bool) $c['liked'], 'pinned' => (bool) ($c['pinned'] ?? false),
            'createdAt' => Carbon::createFromTimestamp($c['ts'])->toIso8601String(), 'mine' => (bool) ($c['mine'] ?? false), 'canDelete' => (bool) ($c['can_delete'] ?? false),
            'author' => ['id' => $aid ?: 'x', 'name' => $c['author'], 'color' => $this->color($aid ?: $c['author']), 'verified' => (bool) ($c['star'] ?? false),
                'type' => $c['recruiter'] ? __('Recruteur') : '', 'photo' => $aid && ($acc = $this->accountOf($aid)) ? $this->url($acc->avatar_url ?? null) : null],
        ];
    }

    public function comments(Request $request, string $video): JsonResponse
    {
        $res = app(FeedController::class)->comments($request, $video)->getData(true);
        $out = [];
        foreach ($res['comments'] ?? [] as $c) {
            $out[] = $this->comment($c);
            foreach ($c['replies'] ?? [] as $r) {
                $out[] = $this->comment($r);
            }
        }

        return response()->json($out);
    }

    public function addComment(Request $request, string $video): JsonResponse
    {
        $res = app(FeedController::class)->comment($request, $video);
        $d = $res->getData(true);
        if ($res->getStatusCode() >= 400 || empty($d['comment'])) {
            return response()->json(['message' => $d['error'] ?? 'Commentaire refusé.'], $res->getStatusCode() >= 400 ? $res->getStatusCode() : 422);
        }

        return response()->json($this->comment($d['comment']));
    }

    public function likeComment(string $comment): JsonResponse
    {
        return app(FeedController::class)->likeComment($comment);
    }

    public function follow(string $user): JsonResponse
    {
        abort_unless($this->data->athlete($user), 404);
        abort_if(($this->data->user()['athlete_id'] ?? null) === $user, 422);

        return app(FeedController::class)->follow($user);
    }

    public function user(string $user): JsonResponse
    {
        $u = $this->userById($user);
        abort_unless($u, 404);

        return response()->json($u);
    }

    public function userVideos(string $user): JsonResponse
    {
        $a = $this->data->athlete($user);
        if (! $a) {
            return response()->json([]);
        }
        $vids = $a['videos'];
        usort($vids, fn ($x, $y) => strcmp((string) ($y['created_at'] ?? ''), (string) ($x['created_at'] ?? '')));

        return response()->json($this->videos(array_map(fn ($v) => ['athlete' => $a, 'video' => $v], $vids)));
    }

    /** Vidéos enregistrées (favoris) du compte connecté. */
    public function saved(): JsonResponse
    {
        $ids = DB::table('video_saves')->where('user_key', $this->data->user()['id'])->orderByDesc('created_at')->pluck('video_id')->all();
        $pos = array_flip($ids);
        $items = [];
        foreach ($this->data->athletes(['kind' => 'tous']) as $a) {
            foreach ($a['videos'] as $v) {
                if (isset($pos[$v['id']])) {
                    $items[$pos[$v['id']]] = ['athlete' => $a, 'video' => $v];
                }
            }
        }
        ksort($items);

        return response()->json($this->videos(array_values($items)));
    }

    // ================================================================= explorer

    public function explore(): JsonResponse
    {
        $all = $this->data->athletes(['kind' => 'tous']);
        $count = [];
        $items = [];
        foreach ($all as $a) {
            foreach ($a['videos'] as $v) {
                $items[] = ['athlete' => $a, 'video' => $v];
                foreach (ExploreController::tags(($v['title'] ?? '').' '.($v['caption'] ?? '')) as $t) {
                    $count[$t] = ($count[$t] ?? 0) + 1;
                }
            }
        }
        arsort($count);
        usort($items, fn ($x, $y) => ($y['video']['views'] ?? 0) <=> ($x['video']['views'] ?? 0));
        $mine = $this->data->user()['athlete_id'] ?? null;
        $suggested = array_values(array_filter($all, fn ($a) => count($a['videos']) && $a['id'] !== $mine && ! $this->isFollowing($a['id'])));
        usort($suggested, fn ($x, $y) => ($y['followers'] ?? 0) <=> ($x['followers'] ?? 0));
        $top = array_slice($count, 0, 8, true);

        return response()->json([
            'trends' => array_map(fn ($t, $n) => ['tag' => '#'.$t, 'posts' => $n], array_keys($top), $top),
            'popular' => $this->videos(array_slice($items, 0, 8)),
            'suggested' => array_map(fn ($a) => $this->userFromAthlete($a), array_slice($suggested, 0, 6)),
            'discover' => array_map(fn ($d) => $this->discoverItem($d), $this->safe(fn () => app(\App\Terrain\Growth\Discover::class)->page(null, 1, 8)->all(), [])),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = Str::lower(trim(Str::limit((string) $request->query('q', ''), 80, '')));
        $filter = in_array($request->query('filter'), ['tout', 'joueurs', 'acteurs', 'comptes', 'clubs', 'videos'], true) ? $request->query('filter') : 'tout';
        $tag = str_starts_with($q, '#');
        $needle = ltrim($q, '#');
        $users = $videos = $clubs = [];
        $mine = $this->data->user()['athlete_id'] ?? null;
        foreach ($this->data->athletes(['kind' => 'tous']) as $a) {
            $hay = Str::lower($a['name'].' '.$a['city'].' '.$a['club'].' '.$this->typeOf($a).' '.implode(' ', $a['fields'] ?? []));
            $kindOk = match ($filter) { 'joueurs' => ($a['kind'] ?? 'joueur') === 'joueur', 'acteurs' => ($a['kind'] ?? '') === 'pro', default => true };
            if (! $tag && $kindOk && ($needle === '' || str_contains($hay, $needle)) && $a['id'] !== $mine) {
                $users[] = $a;
            }
            foreach ($a['videos'] as $v) {
                $text = Str::lower(($v['title'] ?? '').' '.($v['caption'] ?? ''));
                if ($needle !== '' && ($tag ? in_array($needle, ExploreController::tags($text), true) : str_contains($text, $needle))) {
                    $videos[] = ['athlete' => $a, 'video' => $v];
                }
            }
        }
        if (! $tag && in_array($filter, ['tout', 'clubs'], true)) {
            $clubs = $this->safe(fn () => DB::table('organizations')->when($needle !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%'.$needle.'%')->orWhere('city', 'like', '%'.$needle.'%')))
                ->orderByDesc('verified')->orderBy('name')->limit(20)->get()->map(fn ($o) => [
                    'id' => (string) $o->slug, 'name' => $o->name, 'short' => $this->short($o->name), 'city' => (string) $o->city,
                    'colors' => $this->clubColors($o->name), 'verified' => (bool) $o->verified, 'followers' => 0, 'url' => route('clubs.show', $o->slug),
                ])->all(), []);
        }
        usort($users, fn ($x, $y) => ($y['followers'] ?? 0) <=> ($x['followers'] ?? 0));

        return response()->json([
            'users' => in_array($filter, ['tout', 'joueurs', 'acteurs', 'comptes'], true) ? array_map(fn ($a) => $this->userFromAthlete($a), array_slice($users, 0, 30)) : [],
            'clubs' => $clubs,
            'videos' => in_array($filter, ['tout', 'videos'], true) ? $this->videos(array_slice($videos, 0, 30)) : [],
        ]);
    }

    /** Vidéos « Découvrir » : thèmes + une page de vidéos. */
    public function discover(Request $request): JsonResponse
    {
        $d = app(\App\Terrain\Growth\Discover::class);
        $topics = $this->safe(fn () => $d->topics(), []);
        $topic = (string) $request->query('topic', '');
        $topic = isset($topics[$topic]) ? $topic : null;

        return response()->json([
            'topics' => array_map(fn ($t, $n) => ['key' => $t, 'label' => \App\Terrain\Growth\Discover::topicLabel((string) $t), 'count' => $n], array_keys($topics), $topics),
            'items' => array_map(fn ($x) => $this->discoverItem($x), $this->safe(fn () => $d->page($topic, max(1, (int) $request->query('page', 1)), 24)->all(), [])),
        ]);
    }

    // ================================================================= matchs et lives

    private function team(string $name, string $logo = ''): array
    {
        return ['name' => $name, 'short' => $this->short($name), 'colors' => $this->clubColors($name), 'logo' => $logo ?: null];
    }

    private function match(array $m): array
    {
        $status = in_array($m['status'] ?? '', ['live', 'finished'], true) ? $m['status'] : 'upcoming';
        [$comp, $round] = array_pad(explode(' · ', (string) ($m['competition'] ?? ''), 2), 2, '');
        $label = (string) ($m['date_label'] ?? '');
        $kick = preg_match('/(\d{1,2}:\d{2})/', $label, $mm) ? $mm[1] : '';
        $day = trim(preg_replace('/\s*·?\s*\d{1,2}:\d{2}\s*$/', '', $label));
        $events = array_map(fn ($e) => ['m' => $e['label'] ?? $e['minute'] ?? '', 't' => ($e['type'] ?? '') === 'but' ? 'but' : (($e['type'] ?? '') === 'rouge' ? 'rouge' : 'carton'), 'side' => $e['side'] ?? 'h', 'who' => $e['player'] ?? ''], $m['events'] ?? []);
        $id = (string) $m['id'];

        return [
            'id' => $id, 'comp' => $comp ?: 'Match', 'round' => $round, 'status' => $status,
            'homeClub' => $this->team((string) $m['home'], (string) ($m['home_logo'] ?? '')), 'awayClub' => $this->team((string) $m['away'], (string) ($m['away_logo'] ?? '')),
            'hs' => $status === 'upcoming' ? null : (int) ($m['hs'] ?? 0), 'as' => $status === 'upcoming' ? null : (int) ($m['as'] ?? 0),
            'minute' => (int) ($m['minute'] ?? 0), 'kickoff' => $kick, 'day' => $day ?: $label, 'venue' => (string) ($m['venue'] ?? ''),
            'events' => $events, 'stats' => ! empty($m['stats']) && ! array_is_list($m['stats']) ? $m['stats'] : null, 'ts' => (int) ($m['ts'] ?? 0),
            'room' => 'm-'.preg_replace('/[^A-Za-z0-9\-]/', '', $id), 'url' => route('matches.show', $id),
        ];
    }

    public function matches(Request $request, RealScores $real): JsonResponse
    {
        $tab = (string) $request->query('tab', 'tout');
        $list = array_merge($this->safe(fn () => $real->enabled() ? $real->today() : [], []), $this->safe(fn () => $this->data->matches(), []));
        $out = array_map(fn ($m) => $this->match($m), $list);
        $out = array_values(array_filter($out, fn ($m) => match ($tab) {
            'direct' => $m['status'] === 'live', 'avenir' => $m['status'] === 'upcoming', 'resultats' => $m['status'] === 'finished', default => true,
        }));
        usort($out, fn ($a, $b) => [$a['status'] !== 'live', $tab === 'resultats' ? -$a['ts'] : $a['ts']] <=> [$b['status'] !== 'live', $tab === 'resultats' ? -$b['ts'] : $b['ts']]);

        return response()->json(array_slice($out, 0, 60));
    }

    public function matchShow(string $id, RealScores $real): JsonResponse
    {
        $m = str_starts_with($id, 'api-') ? $this->safe(fn () => $real->match($id), null) : $this->safe(fn () => $this->data->match($id), null);
        abort_unless($m, 404);

        return response()->json($this->match($m));
    }

    private function stream(object $s): array
    {
        $live = app(\App\Terrain\Growth\Live::class);
        $member = ($s->kind ?? null) === 'membre';
        $owner = $member ? $this->userById((string) $s->owner_key) : null;

        return [
            'id' => (int) $s->id, 'kind' => $member ? 'membre' : 'match', 'status' => $s->status,
            'title' => \App\Terrain\Growth\Live::label($s), 'competition' => (string) ($s->competition ?? ''),
            'home' => (string) ($s->home ?? ''), 'away' => (string) ($s->away ?? ''),
            'hasScore' => \App\Terrain\Growth\Live::hasScore($s), 'hs' => (int) ($s->home_score ?? 0), 'as' => (int) ($s->away_score ?? 0), 'minute' => (string) ($s->minute ?? ''),
            'city' => (string) ($s->city ?? ''), 'viewers' => $this->safe(fn () => $live->viewers((int) $s->id), 0),
            'startsAt' => $s->starts_at ? Carbon::parse($s->starts_at)->toIso8601String() : null,
            'owner' => $owner, 'room' => 'l-'.$s->id, 'url' => route('live.show', $s->id),
        ];
    }

    /** Lives : lives des membres, matchs diffusés par les clubs, programme, grands matchs du jour, diffuseur partenaire. */
    public function lives(): JsonResponse
    {
        $this->safe(fn () => app(\App\Terrain\Growth\UserLive::class)->sweep(), null);
        $base = fn () => DB::table('live_streams')->where('approved', true);
        $onAir = $this->safe(fn () => $base()->where('status', 'direct')->orderByDesc('peak')->limit(30)->get()->all(), []);
        $u = $this->data->user();
        $big = [];
        $real = app(RealScores::class);
        if ($real->enabled()) {
            $bc = app(\App\Terrain\Growth\Broadcasters::class);
            foreach ($this->safe(fn () => $real->today(), []) as $m) {
                if (in_array((int) ($m['league_id'] ?? 0), \App\Terrain\Growth\Broadcasters::BIG, true) && ($m['status'] ?? '') !== 'finished') {
                    $big[] = $this->match($m) + ['tv' => $this->safe(fn () => $bc->for((int) $m['league_id'])[0]->name ?? null, null)];
                }
            }
        }
        $partner = $this->safe(fn () => app(\App\Terrain\Growth\PartnerLink::class)->get(), null);

        return response()->json([
            'members' => array_map(fn ($s) => $this->stream($s), array_values(array_filter($onAir, fn ($s) => ($s->kind ?? null) === 'membre'))),
            'matches' => array_map(fn ($s) => $this->stream($s), array_values(array_filter($onAir, fn ($s) => ($s->kind ?? null) !== 'membre'))),
            'soon' => array_map(fn ($s) => $this->stream($s), $this->safe(fn () => $base()->where('status', 'prevu')->orderBy('starts_at')->limit(10)->get()->all(), [])),
            'big' => array_slice($big, 0, 12),
            'partner' => $partner ? ['name' => $partner->name, 'url' => route('live.partner')] : null,
            'canLive' => $u !== null, 'liveRefusal' => $u ? app(\App\Terrain\Growth\UserLive::class)->refusal($u) : __('Connecte-toi pour lancer un live.'),
            'canBroadcast' => ($u['role'] ?? null) === 'recruiter',
            'links' => ['go' => route('live.go'), 'broadcast' => route('live.create'), 'all' => route('live')],
        ]);
    }

    // ================================================================= messagerie

    private function person(array $p): array
    {
        $u = $this->userById((string) $p['id']);

        return $u ?? ['id' => (string) $p['id'], 'name' => $p['name'] ?? 'Membre', 'handle' => '', 'type' => '', 'color' => $this->color((string) $p['id']), 'verified' => (bool) ($p['verified'] ?? false)];
    }

    private function message(array $m, string $partnerId): array
    {
        return ['id' => $m['id'], 'from' => $m['mine'] ? 'me' : $partnerId, 'text' => $m['text'], 'at' => $m['ts'], 'kind' => $m['kind'], 'media' => $m['media_url'] ?? null, 'read' => (bool) ($m['read'] ?? false)];
    }

    public function conversations(Messenger $messenger): JsonResponse
    {
        $out = [];
        foreach ($messenger->threads() as $t) {
            $pid = (string) $t['partner']['id'];
            $out[] = ['id' => $pid, 'withId' => $pid, 'with' => $this->person($t['partner']), 'unread' => (int) $t['unread'], 'last' => $this->message($t['last'], $pid)];
        }

        return response()->json($out);
    }

    public function conversation(string $partner, Messenger $messenger): JsonResponse
    {
        $c = $messenger->conversation($partner);

        return response()->json([
            'id' => $partner, 'withId' => $partner, 'with' => $this->person($c['partner']), 'unread' => 0,
            'messages' => array_map(fn ($m) => $this->message($m, $partner), $c['messages']),
            'canCall' => $this->safe(fn () => (bool) ($messenger->canCall($partner)[0] ?? false), false),
            'callReason' => $this->safe(fn () => $messenger->canCall($partner)[1] ?? null, null),
            'pageUrl' => route('messages.show', $partner),
        ]);
    }

    public function openConversation(Request $request, Messenger $messenger): JsonResponse
    {
        $partner = (string) $request->validate(['user_id' => ['required', 'string', 'max:64']])['user_id'];

        return $this->conversation($partner, $messenger);
    }

    public function sendMessage(Request $request, string $partner): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');
        $res = app(MessageController::class)->store($request, $partner);
        $d = $res->getData(true);
        if ($res->getStatusCode() >= 400) {
            $msg = ($d['error'] ?? '') === 'premium' ? 'Passe en Premium pour écrire à de nouveaux joueurs.' : ($d['error'] ?? 'Message impossible à envoyer.');

            return response()->json(['message' => $msg], $res->getStatusCode());
        }

        return response()->json($this->message($d['message'], $partner));
    }

    // ================================================================= notifications

    public function notifications(): JsonResponse
    {
        $kind = fn ($t) => match (true) {
            str_contains($t, 'like') => 'like', str_contains($t, 'follow') || $t === 'star' => 'follow',
            str_contains($t, 'comment') => 'comment', str_contains($t, 'live') || str_contains($t, 'match') || str_contains($t, 'goal') => 'live',
            str_contains($t, 'defi') || str_contains($t, 'challenge') => 'challenge', default => 'comment',
        };
        $rows = $this->safe(fn () => DB::table('notifications_feed')->whereIn('user_key', $this->myKeys())->orderByDesc('id')->limit(60)->get(), collect());

        return response()->json($rows->map(fn ($r) => [
            'id' => (string) $r->id, 'kind' => $kind((string) $r->type), 'text' => trim($r->title.($r->body ? ' — '.$r->body : '')),
            'at' => Carbon::parse($r->created_at)->toIso8601String(), 'read' => (bool) $r->read_at, 'url' => $r->url, 'actor' => null,
        ])->values()->all());
    }

    public function notificationsRead(): JsonResponse
    {
        $this->safe(fn () => DB::table('notifications_feed')->whereIn('user_key', $this->myKeys())->whereNull('read_at')->update(['read_at' => now()]), 0);

        return response()->json(['ok' => true]);
    }
}
