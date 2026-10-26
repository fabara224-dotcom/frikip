<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Engagement;
use App\Terrain\Growth\Subtitles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/** Langue parlée, titres traduits et sous-titres d'une vidéo. */
class VideoController extends Controller
{
    public function __construct(private DataSource $data)
    {
    }

    /** Fichier de sous-titres WebVTT (lu par la balise <track> du lecteur). */
    public function subtitles(string $video, string $lang): Response
    {
        $vtt = app(Subtitles::class)->vtt($video, $lang);
        abort_unless($vtt, 404);

        return response($vtt, 200, ['Content-Type' => 'text/vtt; charset=utf-8', 'Cache-Control' => 'public, max-age=300']);
    }

    private function myVideo(string $video): array
    {
        $me = $this->data->user();
        $owner = $this->data->videoOwner($video);
        abort_unless($owner && $owner === ($me['athlete_id'] ?? null), 403);
        foreach ($this->data->athlete($owner)['videos'] ?? [] as $v) {
            if ($v['id'] === $video) {
                return $v;
            }
        }
        abort(404);
    }

    public function edit(string $video): View
    {
        $v = $this->myVideo($video);

        return view('web.video-edit', ['v' => $v, 'auto' => Subtitles::autoEnabled(), 'orig' => self::titleLang($video, $v['title'])]);
    }

    public function update(Request $request, string $video): RedirectResponse
    {
        $v = $this->myVideo($video);
        $subs = app(Subtitles::class);
        foreach (array_keys(Subtitles::LANGS) as $l) {
            if ($request->boolean("remove_{$l}")) {
                $subs->remove($video, $l);
            }
        }
        $errors = self::saveLanguageForm($request, $video, $v['title'], $v['caption'] ?? '');
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }
        if ($request->boolean('auto') && Subtitles::autoEnabled() && in_array($request->input('lang'), ['fr', 'en'], true)) {
            $file = method_exists($this->data, 'localVideoFile') ? $this->data->localVideoFile($video) : null;
            if ($file) {
                $lang = $request->input('lang');
                app()->terminating(fn () => app(Subtitles::class)->generate($video, $file, $lang));
            } else {
                return back()->with('status', __('Enregistré. Les sous-titres automatiques ne sont possibles qu’au moment de la publication pour cette vidéo : envoie un fichier .srt à la place.'));
            }
        }

        return redirect()->route('videos.edit', $video)->with('status', __('Enregistré.'));
    }

    /** Langue dans laquelle le titre d'origine a été écrit. */
    public static function titleLang(string $videoId, string $title): string
    {
        $m = app(Engagement::class)->meta([$videoId])[$videoId] ?? null;
        if ($m && $m->title_en === $title && $m->title_fr !== $title) {
            return 'en';
        }
        if ($m && $m->title_fr === $title) {
            return 'fr';
        }

        return App::getLocale() === 'en' ? 'en' : 'fr';
    }

    /**
     * Champs communs à la publication et à la modification :
     * lang (fr | en | none), has_audio, title_alt / caption_alt (autre langue), sub_fr / sub_en (.srt ou .vtt).
     *
     * @return array<string, string> erreurs éventuelles
     */
    public static function saveLanguageForm(Request $request, string $videoId, string $title, ?string $caption): array
    {
        $request->validate([
            'lang' => ['nullable', 'in:fr,en,none'],
            'title_alt' => ['nullable', 'string', 'max:150'],
            'caption_alt' => ['nullable', 'string', 'max:120'],
            'sub_fr' => ['nullable', 'file', 'max:512'],
            'sub_en' => ['nullable', 'file', 'max:512'],
        ]);
        // Langue du titre d'origine : celle de la page à la publication, ensuite celle déjà enregistrée
        $page = self::titleLang($videoId, $title);
        $other = $page === 'en' ? 'fr' : 'en';
        $titleAlt = trim((string) $request->input('title_alt'));
        $captionAlt = trim((string) $request->input('caption_alt'));
        // Traduction automatique du titre si rien n'est donné et que la clé OpenAI est remplie
        if ($titleAlt === '' && Subtitles::autoEnabled()) {
            $tr = app(Subtitles::class)->translateLines(array_values(array_filter([$title, (string) $caption])), $other);
            $titleAlt = (string) ($tr[0] ?? '');
            if ($caption && $captionAlt === '') {
                $captionAlt = (string) ($tr[1] ?? '');
            }
        }
        app(Engagement::class)->saveMeta($videoId, [
            'lang' => $request->input('lang', 'none') ?: 'none',
            'has_audio' => $request->boolean('has_audio'),
            "title_{$page}" => $title, "title_{$other}" => $titleAlt ?: null,
            "caption_{$page}" => $caption ?: null, "caption_{$other}" => $captionAlt ?: null,
        ]);

        $errors = [];
        foreach (array_keys(Subtitles::LANGS) as $l) {
            if ($f = $request->file("sub_{$l}")) {
                if (! app(Subtitles::class)->store($videoId, $l, (string) file_get_contents($f->getRealPath()))) {
                    $errors["sub_{$l}"] = __('Fichier de sous-titres illisible : il faut un fichier .srt ou .vtt.');
                }
            }
        }

        return $errors;
    }
}
