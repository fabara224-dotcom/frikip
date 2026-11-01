<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin > Actu : publications, commentaires et stories des membres.
 * « À vérifier » = publications et stories signalées ou masquées automatiquement (3 signalements).
 */
class AdminActuController extends Controller
{
    public const TABS = ['a_verifier' => 'À vérifier', 'publications' => 'Publications', 'commentaires' => 'Commentaires', 'stories' => 'Stories'];

    /** Nombre d'éléments à vérifier (pastille du menu admin). */
    public static function todo(): int
    {
        try {
            return DB::table('posts')->where(fn ($q) => $q->where('hidden', true)->orWhere('reports', '>', 0))->whereNull('reviewed_at')->count()
                + DB::table('fk_stories')->where('expires_at', '>', now())->where(fn ($q) => $q->where('hidden', true)->orWhere('reports', '>', 0))->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->query('onglet'), self::TABS) ? $request->query('onglet') : 'a_verifier';
        $q = trim((string) $request->query('q'));
        $search = fn ($query, array $cols) => $q === '' ? $query : $query->where(function ($w) use ($cols, $q) {
            foreach ($cols as $c) {
                $w->orWhere($c, 'like', '%'.$q.'%');
            }
        });
        $flagged = fn ($query) => $query->where(fn ($w) => $w->where('hidden', true)->orWhere('reports', '>', 0));

        $posts = $stories = $comments = collect();
        if ($tab === 'a_verifier') {
            $posts = $search($flagged(DB::table('posts'))->whereNull('reviewed_at'), ['body', 'author_name'])->orderByDesc('reports')->orderByDesc('id')->limit(200)->get();
            $stories = $search($flagged(DB::table('fk_stories'))->where('expires_at', '>', now()), ['caption', 'author_name'])->orderByDesc('reports')->limit(200)->get();
        } elseif ($tab === 'publications') {
            $posts = $search(DB::table('posts'), ['body', 'author_name'])->orderByDesc('id')->limit(200)->get();
        } elseif ($tab === 'commentaires') {
            $comments = $search(DB::table('post_comments'), ['body', 'author_name'])->orderByDesc('id')->limit(200)->get();
        } else {
            $stories = $search(DB::table('fk_stories')->where('expires_at', '>', now()), ['caption', 'author_name'])->orderByDesc('id')->limit(200)->get();
        }

        return view('admin.actu', ['tab' => $tab, 'q' => $q, 'posts' => $posts, 'stories' => $stories, 'comments' => $comments,
            'counts' => ['a_verifier' => self::todo(), 'publications' => DB::table('posts')->count(), 'commentaires' => DB::table('post_comments')->count(),
                'stories' => DB::table('fk_stories')->where('expires_at', '>', now())->count()]]);
    }

    /** Publication : garder (remise en ligne, signalements effacés), masquer, ou supprimer. */
    public function post(Request $request, int $id): RedirectResponse
    {
        $post = DB::table('posts')->where('id', $id)->first();
        abort_unless($post, 404);
        switch ($request->input('do')) {
            case 'garder':
                DB::table('post_reports')->where('post_id', $id)->delete();
                DB::table('posts')->where('id', $id)->update(['hidden' => false, 'reports' => 0, 'reviewed_at' => now()]);

                return back()->with('saved', 'Publication gardée et remise en ligne.');
            case 'masquer':
                DB::table('posts')->where('id', $id)->update(['hidden' => true, 'reviewed_at' => now()]);

                return back()->with('saved', 'Publication masquée.');
            case 'supprimer':
                if ($post->photo && str_starts_with($post->photo, 'uploads/posts/')) {
                    @unlink(public_path($post->photo));
                }
                foreach (['post_comments', 'post_likes', 'post_reports'] as $t) {
                    DB::table($t)->where('post_id', $id)->delete();
                }
                DB::table('posts')->where('id', $id)->delete();

                return back()->with('saved', 'Publication supprimée.');
        }
        abort(422);
    }

    public function deleteComment(int $id): RedirectResponse
    {
        $c = DB::table('post_comments')->where('id', $id)->first();
        abort_unless($c, 404);
        DB::table('post_comments')->where('id', $id)->delete();
        DB::table('posts')->where('id', $c->post_id)->update(['comments' => DB::table('post_comments')->where('post_id', $c->post_id)->count()]);

        return back()->with('saved', 'Commentaire supprimé.');
    }

    /** Story : garder (signalements effacés) ou supprimer. */
    public function story(Request $request, int $id): RedirectResponse
    {
        $s = DB::table('fk_stories')->where('id', $id)->first();
        abort_unless($s, 404);
        if ($request->input('do') === 'garder') {
            DB::table('fk_story_reports')->where('story_id', $id)->delete();
            DB::table('fk_stories')->where('id', $id)->update(['hidden' => false, 'reports' => 0]);

            return back()->with('saved', 'Story gardée et remise en ligne.');
        }
        StoryController::remove($s);

        return back()->with('saved', 'Story supprimée.');
    }
}
