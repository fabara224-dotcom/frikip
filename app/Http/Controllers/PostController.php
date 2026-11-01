<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\ContentGuard;
use App\Terrain\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Actu : le côté « Facebook » de Frikip. Les membres publient un texte et/ou une photo,
 * les autres aiment et commentent. Le fil vidéo vertical (côté « TikTok ») reste sur /fil.
 */
class PostController extends Controller
{
    /** Signalements à partir desquels une publication est masquée en attendant l'admin. */
    private const HIDE_AFTER_REPORTS = 3;

    public function __construct(private DataSource $data)
    {
    }

    public function index(Request $request): View
    {
        $tab = $request->query('onglet') === 'pays' ? 'pays' : 'tout';
        $country = Region::viewer();
        $posts = DB::table('posts')->where('hidden', false)
            ->when($tab === 'pays', fn ($q) => $q->where('country', $country))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('web.posts', $this->extras($posts->items()) + ['posts' => $posts, 'tab' => $tab, 'country' => $country]);
    }

    public function show(int $id): View
    {
        $post = DB::table('posts')->where('id', $id)->where('hidden', false)->first();
        abort_unless($post, 404);
        $comments = DB::table('post_comments')->where('post_id', $id)->orderBy('id')->get();

        return view('web.post', $this->extras([$post]) + ['post' => $post, 'allComments' => $comments]);
    }

    public function store(Request $request, ContentGuard $guard): RedirectResponse
    {
        $me = $this->data->user();
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
        ], ['photo.max' => 'Photo trop lourde (8 Mo maximum).', 'photo.mimetypes' => 'Format de photo non pris en charge (JPG, PNG ou WebP).']);
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && ! $request->hasFile('photo')) {
            return back()->withErrors(['body' => 'Écris quelque chose ou ajoute une photo.']);
        }
        $guard->enforce(['body' => $body], 'post', $me['id']);

        $photo = null;
        if ($file = $request->file('photo')) {
            $dir = public_path('uploads/posts');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = Str::uuid().'.'.($file->guessExtension() ?: 'jpg');
            $file->move($dir, $name);
            $path = $dir.'/'.$name;
            // Photo à caractère sexuel : refusée (analyse Sightengine si elle est configurée)
            $score = $guard->nudityScore($path);
            if ($score !== null && $score >= 0.6) {
                @unlink($path);

                return back()->withInput()->withErrors(['photo' => 'Cette photo n’est pas autorisée sur '.config('terrain.app_name').'.']);
            }
            $photo = 'uploads/posts/'.$name;
        }

        DB::table('posts')->insert([
            'user_key' => $me['id'], 'author_name' => $me['name'] ?? 'Membre', 'author_role' => $me['role'] ?? null,
            'athlete_id' => $me['athlete_id'] ?? null, 'body' => $body !== '' ? $body : null, 'photo' => $photo,
            'country' => Region::viewer(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('posts')->with('status', 'Publication partagée.');
    }

    public function like(int $id): JsonResponse
    {
        $me = $this->data->user();
        abort_unless(DB::table('posts')->where('id', $id)->exists(), 404);
        $liked = DB::transaction(function () use ($id, $me) {
            $gone = DB::table('post_likes')->where('post_id', $id)->where('user_key', $me['id'])->delete();
            if (! $gone) {
                DB::table('post_likes')->insert(['post_id' => $id, 'user_key' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('posts')->where('id', $id)->update(['likes' => DB::table('post_likes')->where('post_id', $id)->count()]);

            return ! $gone;
        });

        return response()->json(['liked' => $liked, 'likes' => (int) DB::table('posts')->where('id', $id)->value('likes')]);
    }

    public function comment(Request $request, int $id, ContentGuard $guard): RedirectResponse
    {
        $me = $this->data->user();
        abort_unless(DB::table('posts')->where('id', $id)->where('hidden', false)->exists(), 404);
        $body = trim((string) $request->validate(['body' => ['required', 'string', 'max:1000']], ['body.required' => 'Écris ton commentaire.'])['body']);
        $guard->enforce(['body' => $body], 'post_comment', $me['id']);
        DB::table('post_comments')->insert(['post_id' => $id, 'user_key' => $me['id'], 'author_name' => $me['name'] ?? 'Membre', 'body' => $body, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('posts')->where('id', $id)->update(['comments' => DB::table('post_comments')->where('post_id', $id)->count()]);

        return redirect()->to(url()->previous().'#post-'.$id);
    }

    public function destroy(int $id): RedirectResponse
    {
        $me = $this->data->user();
        $post = DB::table('posts')->where('id', $id)->first();
        abort_unless($post && $post->user_key === $me['id'], 403);
        if ($post->photo && str_starts_with($post->photo, 'uploads/posts/')) {
            @unlink(public_path($post->photo));
        }
        DB::table('post_comments')->where('post_id', $id)->delete();
        DB::table('post_likes')->where('post_id', $id)->delete();
        DB::table('post_reports')->where('post_id', $id)->delete();
        DB::table('posts')->where('id', $id)->delete();

        return redirect()->route('posts')->with('status', 'Publication supprimée.');
    }

    public function report(int $id): RedirectResponse
    {
        $me = $this->data->user();
        abort_unless(DB::table('posts')->where('id', $id)->exists(), 404);
        DB::table('post_reports')->insertOrIgnore(['post_id' => $id, 'user_key' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
        $n = DB::table('post_reports')->where('post_id', $id)->count();
        DB::table('posts')->where('id', $id)->update(['reports' => $n, 'hidden' => $n >= self::HIDE_AFTER_REPORTS]);

        return back()->with('status', 'Merci, la publication a été signalée.');
    }

    /** Ce dont les cartes ont besoin : mes j'aime et les derniers commentaires de chaque publication. */
    private function extras(array $posts): array
    {
        $ids = array_map(fn ($p) => $p->id, $posts);
        $me = $this->data->user();
        $liked = $me && $ids ? DB::table('post_likes')->whereIn('post_id', $ids)->where('user_key', $me['id'])->pluck('post_id')->all() : [];
        $comments = [];
        if ($ids) {
            foreach (DB::table('post_comments')->whereIn('post_id', $ids)->orderByDesc('id')->get() as $c) {
                if (count($comments[$c->post_id] ?? []) < 2) {
                    $comments[$c->post_id][] = $c;
                }
            }
        }

        return ['liked' => array_flip($liked), 'comments' => array_map('array_reverse', $comments)];
    }
}
