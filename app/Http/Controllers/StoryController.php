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
use Illuminate\Validation\ValidationException;

/**
 * Stories : une photo ou une courte vidéo visible 24 h, en ronds en haut de l'Actu.
 * Les photos sont réduites dans le navigateur avant l'envoi ; les vidéos passent par l'envoi par morceaux
 * de la publication (aucune limite de taille de PHP à régler sur le serveur).
 */
class StoryController extends Controller
{
    public const HOURS = 24;
    public const VIDEO_MAX_MB = 60;
    private const HIDE_AFTER_REPORTS = 3;

    public function __construct(private DataSource $data)
    {
    }

    /**
     * Stories visibles, regroupées par auteur (les miennes d'abord, puis celles pas encore vues).
     *
     * @return array<int, array{key: string, name: string, initials: string, seen: bool, mine: bool, items: array}>
     */
    public static function groups(?array $me): array
    {
        try {
            $rows = DB::table('fk_stories')->where('hidden', false)->where('expires_at', '>', now())->orderBy('id')->get();
        } catch (\Throwable) {
            return [];
        }
        $seen = $me && $rows->isNotEmpty()
            ? array_flip(DB::table('fk_story_views')->where('user_key', $me['id'])->whereIn('story_id', $rows->pluck('id'))->pluck('story_id')->all()) : [];
        $groups = [];
        foreach ($rows as $s) {
            $g = &$groups[$s->user_key];
            $g ??= ['key' => $s->user_key, 'name' => $s->author_name, 'mine' => $me && $me['id'] === $s->user_key, 'seen' => true, 'last' => 0, 'items' => [],
                'initials' => collect(preg_split('/\s+/', trim($s->author_name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''),
                'profile' => $s->athlete_id ? route('athletes.show', $s->athlete_id) : null];
            $g['items'][] = ['id' => $s->id, 'type' => $s->media_type, 'src' => asset($s->media), 'caption' => $s->caption,
                'seen' => ($me && $me['id'] === $s->user_key) || isset($seen[$s->id]), 'when' => \Illuminate\Support\Carbon::parse($s->created_at)->locale(app()->getLocale())->diffForHumans(), 'views' => $s->views];
            $g['seen'] = $g['seen'] && isset($seen[$s->id]);
            $g['last'] = max($g['last'], $s->id);
            unset($g);
        }
        usort($groups, fn ($a, $b) => [$b['mine'], ! $b['seen'], $b['last']] <=> [$a['mine'], ! $a['seen'], $a['last']]);

        return $groups;
    }

    public function store(Request $request, ContentGuard $guard): RedirectResponse|JsonResponse
    {
        $me = $this->data->user();
        $chunked = $request->filled('upload_id');
        $request->validate([
            'caption' => ['nullable', 'string', 'max:200'],
            'media' => [$chunked ? 'nullable' : 'required', 'file', 'max:8192', 'mimetypes:image/jpeg,image/png,image/webp'],
            'upload_id' => ['nullable', 'string', 'max:40'],
            'file_name' => ['nullable', 'string', 'max:200'],
        ], ['media.required' => 'Choisis une photo ou une vidéo.', 'media.max' => 'Photo trop lourde (8 Mo maximum).', 'media.mimetypes' => 'Format non pris en charge (JPG, PNG ou WebP).']);
        $caption = trim((string) $request->input('caption', ''));
        $guard->enforce(['caption' => $caption], 'story', $me['id']);

        $dir = public_path('uploads/stories');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if ($chunked) {
            try {
                $file = app(PublishController::class)->assembled((string) $request->input('upload_id'), (string) $request->input('file_name', 'story.mp4'));
            } catch (ValidationException $e) {
                return $this->fail($request, collect($e->errors())->flatten()->first());
            }
            if ($file->getSize() > self::VIDEO_MAX_MB * 1024 * 1024) {
                @unlink($file->getPathname());

                return $this->fail($request, 'Vidéo trop lourde ('.self::VIDEO_MAX_MB.' Mo maximum).');
            }
            $type = 'video';
            $name = Str::uuid().'.'.(strtolower($file->getClientOriginalExtension()) ?: 'mp4');
            rename($file->getPathname(), $dir.'/'.$name);
            $score = $guard->videoNudityScore($dir.'/'.$name);
        } else {
            $type = 'image';
            $file = $request->file('media');
            $name = Str::uuid().'.'.($file->guessExtension() ?: 'jpg');
            $file->move($dir, $name);
            $score = $guard->nudityScore($dir.'/'.$name);
        }
        if ($score !== null && $score >= 0.6) {
            @unlink($dir.'/'.$name);

            return $this->fail($request, 'Ce contenu n’est pas autorisé sur '.config('terrain.app_name').'.');
        }
        self::purgeExpired();
        DB::table('fk_stories')->insert([
            'user_key' => $me['id'], 'author_name' => $me['name'] ?? 'Membre', 'athlete_id' => $me['athlete_id'] ?? null,
            'media' => 'uploads/stories/'.$name, 'media_type' => $type, 'caption' => $caption !== '' ? $caption : null,
            'country' => Region::viewer(), 'expires_at' => now()->addHours(self::HOURS), 'created_at' => now(), 'updated_at' => now(),
        ]);

        session()->flash('status', 'Ta story est en ligne pour 24 h.');

        return $request->expectsJson() ? response()->json(['redirect' => route('posts')]) : redirect()->route('posts');
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->withErrors(['media' => $message]);
    }

    public function view(int $id): JsonResponse
    {
        $me = $this->data->user();
        if ($me && DB::table('fk_stories')->where('id', $id)->where('user_key', '!=', $me['id'])->exists()) {
            $new = DB::table('fk_story_views')->insertOrIgnore(['story_id' => $id, 'user_key' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
            if ($new) {
                DB::table('fk_stories')->where('id', $id)->increment('views');
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): RedirectResponse
    {
        $me = $this->data->user();
        $s = DB::table('fk_stories')->where('id', $id)->first();
        abort_unless($s && $s->user_key === $me['id'], 403);
        self::remove($s);

        return redirect()->route('posts')->with('status', 'Story supprimée.');
    }

    public function report(int $id): RedirectResponse
    {
        $me = $this->data->user();
        abort_unless(DB::table('fk_stories')->where('id', $id)->exists(), 404);
        DB::table('fk_story_reports')->insertOrIgnore(['story_id' => $id, 'user_key' => $me['id'], 'created_at' => now(), 'updated_at' => now()]);
        $n = DB::table('fk_story_reports')->where('story_id', $id)->count();
        DB::table('fk_stories')->where('id', $id)->update(['reports' => $n, 'hidden' => $n >= self::HIDE_AFTER_REPORTS]);

        return redirect()->route('posts')->with('status', 'Merci, la story a été signalée.');
    }

    /** Supprime une story et son fichier. */
    public static function remove(object $s): void
    {
        if (str_starts_with((string) $s->media, 'uploads/stories/')) {
            @unlink(public_path($s->media));
        }
        DB::table('fk_story_views')->where('story_id', $s->id)->delete();
        DB::table('fk_story_reports')->where('story_id', $s->id)->delete();
        DB::table('fk_stories')->where('id', $s->id)->delete();
    }

    /** Ménage : les stories expirées depuis plus de 2 jours disparaissent avec leur fichier. */
    public static function purgeExpired(): void
    {
        foreach (DB::table('fk_stories')->where('expires_at', '<', now()->subDays(2))->limit(200)->get() as $s) {
            self::remove($s);
        }
    }
}
