<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Engagement;
use App\Terrain\RealScores;
use App\Terrain\SportFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

/** Fil vertical façon TikTok + j'aime, commentaires, abonnements (appels JSON). */
class FeedController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    public function index(Request $request): View
    {
        // Plus de filtre par sport en haut du fil : c'est l'algorithme qui choisit (Admin > Algorithme)
        $sport = 'Tous';
        $items = app(\App\Terrain\Growth\Algorithm::class)->orderFeed($this->data->feed($sport));
        app(\App\Terrain\Growth\LiveFinder::class)->maybeSync();

        // Lien partagé vers une vidéo précise : elle passe en premier.
        if ($v = $request->query('v')) {
            usort($items, fn ($x, $y) => ($y['video']['id'] === $v) <=> ($x['video']['id'] === $v));
        }
        // Le score en haut du fil est celui que le visiteur a choisi (automatique, son équipe, une compétition, un match, aucun)
        $scores = app(\App\Terrain\Growth\ScoreChoice::class);
        $live = $scores->pick();

        $page = app(\App\Terrain\Growth\Ads::class)->inject(array_slice($items, 0, self::PAGE), 0, $this->data->user(), $sport);
        $streams = app(\App\Terrain\Growth\Live::class)->onAir();
        // Match en direct au milieu des vidéos (après la 2e)
        if ($streams && app(\App\Terrain\Growth\Algorithm::class)->get('live_in_feed') && count($page) > 2) {
            array_splice($page, 2, 0, [['live' => $streams[0]]]);
        }

        return view('web.feed', [
            'items' => $page, 'onAir' => $streams, 'scoreChoice' => $scores->get(), 'scoreOptions' => $scores->options(), 'sport' => $sport, 'sports' => SportFields::sports(), 'live' => $live,
            'following' => $this->data->following(), 'challenge' => app(\App\Terrain\Growth\Algorithm::class)->get('defi_in_feed') ? app(\App\Terrain\Growth\Challenges::class)->current() : null, 'next' => $this->nextUrl($sport, 1, count($items)),
        ]);
    }

    /** Vidéos suivantes du fil (défilement infini), 20 par 20. */
    public function more(Request $request): Response
    {
        $sport = 'Tous';
        $page = max(2, (int) $request->query('page', 2));
        $items = app(\App\Terrain\Growth\Algorithm::class)->orderFeed($this->data->feed($sport));
        $html = view('web.partials.feed-items', [
            'items' => app(\App\Terrain\Growth\Ads::class)->inject(array_slice($items, ($page - 1) * self::PAGE, self::PAGE), ($page - 1) * self::PAGE, $this->data->user(), $sport),
            'following' => $this->data->following(), 'me' => $this->data->user(),
        ])->render();

        return response($html)->header('X-Next-Page', (string) $this->nextUrl($sport, $page, count($items)));
    }

    private const PAGE = 20;

    private function nextUrl(string $sport, int $page, int $total): ?string
    {
        return $page * self::PAGE < $total ? route('feed.more', array_filter(['sport' => $sport === 'Tous' ? null : $sport, 'page' => $page + 1])) : null;
    }

    public function like(string $video): JsonResponse
    {
        return response()->json($this->data->toggleLike($video));
    }

    private function eng(): Engagement
    {
        return app(Engagement::class);
    }

    /** Commentaires d'une vidéo : réponses, j'aime, épinglé, droits de l'utilisateur. */
    public function comments(Request $request, string $video): JsonResponse
    {
        $sort = $request->query('tri') === 'recent' ? 'recent' : 'top';
        try {
            $list = $this->eng()->present($this->data->comments($video), $this->data->videoOwner($video), $this->data->user(), $sort);
        } catch (Throwable $e) {
            report($e);
            $list = [];
        }
        $count = array_sum(array_map(fn ($c) => 1 + $c['reply_count'], $list));

        return response()->json(['comments' => $list, 'count' => $count, 'sort' => $sort]);
    }

    public function comment(Request $request, string $video): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'string', 'max:64'],
        ]);
        $me = $this->data->user();
        if ($why = app(\App\Terrain\Growth\ContentGuard::class)->refusal(['text' => $data['text']], 'comment', $me['id'] ?? null)) {
            return response()->json(['error' => $why], 422);
        }
        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = $this->data->comment($data['parent_id']);
            abort_unless($parent && $parent['video_id'] === $video, 422);
            // On répond toujours au commentaire principal (un seul niveau de réponses)
            if ($parent['parent_id']) {
                $parent = $this->data->comment($parent['parent_id']) ?? $parent;
            }
        }
        $row = $this->data->addComment($video, trim($data['text']), $parent['id'] ?? null);
        $owner = $this->data->videoOwner($video);

        // Notifications : l'auteur de la vidéo et la personne à qui on répond
        try {
            $n = app(\App\Terrain\Growth\Notifier::class);
            $url = route('feed', ['v' => $video, 'commentaires' => 1]);
            $short = \Illuminate\Support\Str::limit(trim($data['text']), 80);
            if ($owner && $owner !== ($me['athlete_id'] ?? null)) {
                $n->send($owner, 'comment', $me['name'].' a commenté ta vidéo', $short, $url);
            }
            if ($parent && $parent['user_key'] !== $me['id']) {
                $n->send($parent['user_key'], 'comment', $me['name'].' a répondu à ton commentaire', $short, $url);
            }
        } catch (Throwable $e) {
            report($e);
        }

        // Mis en forme seul (une réponse est présentée comme un commentaire puis rattachée à son parent)
        $list = $this->eng()->present([['parent_id' => null] + $row], $owner, $me);
        $out = $list[0] ?? null;
        if ($out) {
            $out['parent_id'] = $row['parent_id'] ?? null;
            $out['can_pin'] = $out['can_pin'] && ! $out['parent_id'];
        }

        return response()->json(['comment' => $out]);
    }

    public function likeComment(string $comment): JsonResponse
    {
        abort_unless($this->data->comment($comment), 404);

        return response()->json($this->eng()->toggleCommentLike($comment, $this->data->user()['id']));
    }

    /** Supprimer : son propre commentaire, ou n'importe quel commentaire sous sa propre vidéo (il est alors masqué). */
    public function deleteComment(string $comment): JsonResponse
    {
        $c = $this->data->comment($comment);
        abort_unless($c, 404);
        $me = $this->data->user();
        if ($c['user_key'] === $me['id']) {
            $this->data->deleteComment($comment);
        } elseif ($this->data->videoOwner($c['video_id']) === ($me['athlete_id'] ?? '')) {
            $this->eng()->hide($c['video_id'], $comment);
        } else {
            abort(403);
        }

        return response()->json(['ok' => true]);
    }

    public function pinComment(Request $request, string $comment): JsonResponse
    {
        $c = $this->data->comment($comment);
        abort_unless($c && ! $c['parent_id'], 404);
        abort_unless($this->data->videoOwner($c['video_id']) === ($this->data->user()['athlete_id'] ?? ''), 403);
        $on = $request->boolean('on', true);
        $this->eng()->setPinned($c['video_id'], $comment, $on);

        return response()->json(['pinned' => $on]);
    }

    /** Partage réel (WhatsApp, partage du téléphone, lien copié) : compté une fois par jour et par canal. */
    public function share(Request $request, string $video): JsonResponse
    {
        $n = $this->eng()->share($video, Engagement::viewerKey($this->data->user()), (string) $request->input('channel', 'lien'));

        return response()->json(['shares' => $n]);
    }

    public function follow(string $athlete): JsonResponse
    {
        $before = $this->eng()->followersOf($athlete);
        $on = $this->data->toggleFollow($athlete);
        // Copie locale pour prévenir les abonnés des nouvelles vidéos et compter les abonnés
        try {
            $key = $this->data->user()['id'];
            if ($on) {
                \Illuminate\Support\Facades\DB::table('follows_local')->insertOrIgnore(['follower_key' => $key, 'athlete_id' => $athlete, 'created_at' => now()]);
            } else {
                \Illuminate\Support\Facades\DB::table('follows_local')->where('follower_key', $key)->where('athlete_id', $athlete)->delete();
            }
            $this->eng()->reset();
            $after = $this->eng()->followersOf($athlete);
            $min = Engagement::starThreshold();
            if ($on && $before < $min && $after >= $min) {
                app(\App\Terrain\Growth\Notifier::class)->send($athlete, 'star', 'Tu as obtenu l’étoile bleue ★',
                    'Ton profil a atteint '.number_format($min, 0, ',', ' ').' abonnés. L’étoile apparaît maintenant à côté de ton nom.', route('athletes.show', $athlete));
            }
        } catch (\Throwable) {
            $after = null;
        }

        return response()->json(['following' => $on, 'followers' => $after]);
    }
}
