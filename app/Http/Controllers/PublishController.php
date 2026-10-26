<?php

namespace App\Http\Controllers;

use App\Terrain\DataSource;
use App\Terrain\Growth\Subtitles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Publication d'une vidéo par un sportif.
 *
 * Les vidéos sont envoyées en petits morceaux de 4 Mo (route publish.chunk) puis rassemblées sur le serveur :
 * plus d'erreur « POST data is too large » quelle que soit la limite de PHP (8 Mo par défaut avec Herd),
 * et un envoi coupé sur une connexion 3G peut reprendre le morceau en cours.
 */
class PublishController extends Controller
{
    /** Taille maximale d'une vidéo (Mo) */
    public const MAX_MB = 300;

    /** Taille d'un morceau (octets) : sous la limite post_max_size de 8 Mo de PHP */
    public const CHUNK = 4 * 1024 * 1024;

    private const VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v', 'video/3gpp', 'video/x-matroska', 'application/octet-stream'];

    public function __construct(private DataSource $data)
    {
    }

    /** Propriétaire des envois par morceaux : le compte connecté, ou « admin » pour l'espace admin (vidéos de pub). */
    private ?string $ownerOverride = null;

    public function asOwner(string $owner): static
    {
        $this->ownerOverride = $owner;

        return $this;
    }

    private function owner(): string
    {
        return $this->ownerOverride ?? (string) ($this->data->user()['id'] ?? '');
    }

    public function create(): View
    {
        $defis = [];
        try {
            $me = $this->data->user();
            if (! empty($me['athlete_id']) && ($a = $this->data->athlete($me['athlete_id']))) {
                $defis = app(\App\Terrain\Growth\Challenges::class)->openFor($a);
            }
        } catch (Throwable) {
        }

        return view('web.publish', ['auto' => Subtitles::autoEnabled(), 'chunk' => self::CHUNK, 'maxMb' => self::MAX_MB, 'defis' => $defis]);
    }

    // ------------------------------------------------------------------ envoi par morceaux

    private static function dir(): string
    {
        $dir = storage_path('app/envois');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** Reçoit un morceau de la vidéo (corps brut de la requête). */
    public function chunk(Request $request): JsonResponse
    {
        $id = (string) $request->header('X-Upload-Id');
        $index = (int) $request->header('X-Chunk-Index', -1);
        $total = (int) $request->header('X-Chunk-Total', 0);
        $size = (int) $request->header('X-File-Size', 0);
        if (! preg_match('/^[a-zA-Z0-9]{20,40}$/', $id) || $index < 0 || $total < 1 || $index >= $total || $size < 1) {
            return response()->json(['error' => __('Envoi invalide. Recommence.')], 422);
        }
        if ($size > self::MAX_MB * 1024 * 1024 || $total > ceil($size / self::CHUNK) + 1) {
            return response()->json(['error' => __('La vidéo dépasse :n Mo. Coupe-la ou réduis sa qualité.', ['n' => self::MAX_MB])], 413);
        }
        $owner = $this->owner();
        $meta = self::dir()."/{$id}.json";
        $part = self::dir()."/{$id}.part";
        if ($index === 0) {
            // Nouvel envoi : on fait le ménage des envois abandonnés (plus de 24 h)
            foreach (glob(self::dir().'/*') ?: [] as $f) {
                if (filemtime($f) < time() - 86400) {
                    @unlink($f);
                }
            }
            file_put_contents($meta, json_encode(['owner' => $owner, 'size' => $size, 'total' => $total, 'next' => 0]));
            file_put_contents($part, '');
        }
        $m = is_file($meta) ? json_decode((string) file_get_contents($meta), true) : null;
        if (! $m || $m['owner'] !== $owner) {
            return response()->json(['error' => __('Envoi introuvable. Recommence.')], 404);
        }
        if ($index < $m['next']) {
            return response()->json(['ok' => true, 'next' => $m['next']]);   // morceau déjà reçu (nouvel essai après coupure)
        }
        if ($index !== $m['next']) {
            return response()->json(['error' => __('Morceau manquant.'), 'next' => $m['next']], 409);
        }
        $body = $request->getContent();
        if ($body === '' || strlen($body) > self::CHUNK) {
            return response()->json(['error' => __('Morceau invalide.')], 422);
        }
        file_put_contents($part, $body, FILE_APPEND | LOCK_EX);
        $m['next'] = $index + 1;
        file_put_contents($meta, json_encode($m));
        if ($m['next'] === $m['total'] && filesize($part) !== $m['size']) {
            return response()->json(['error' => __('La vidéo est arrivée incomplète. Recommence.')], 422);
        }

        return response()->json(['ok' => true, 'next' => $m['next'], 'done' => $m['next'] === $m['total']]);
    }

    /** Vidéo rassemblée après l'envoi par morceaux, vérifiée comme un fichier envoyé normalement. */
    public function assembled(string $id, string $name): UploadedFile
    {
        $meta = self::dir()."/{$id}.json";
        $part = self::dir()."/{$id}.part";
        $m = preg_match('/^[a-zA-Z0-9]{20,40}$/', $id) && is_file($meta) ? json_decode((string) file_get_contents($meta), true) : null;
        if (! $m || $m['owner'] !== $this->owner() || $m['next'] !== $m['total'] || ! is_file($part) || filesize($part) !== $m['size']) {
            throw ValidationException::withMessages(['video' => __('L’envoi de la vidéo n’est pas terminé. Recommence.')]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($part) ?: 'application/octet-stream';
        // Signature du fichier : boîte « ftyp » (MP4, MOV, 3GP) ou en-tête EBML (WebM, MKV)
        $head = (string) file_get_contents($part, false, null, 0, 12);
        $isVideo = substr($head, 4, 4) === 'ftyp' || str_starts_with($head, "\x1A\x45\xDF\xA3");
        if (! $isVideo || ! in_array($mime, self::VIDEO_MIMES, true)) {
            @unlink($part);
            throw ValidationException::withMessages(['video' => __('Ce fichier n’est pas une vidéo. Formats acceptés : MP4, MOV ou WebM.')]);
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'mp4';
        $final = self::dir()."/{$id}.".(in_array($ext, ['mp4', 'mov', 'webm', 'm4v', '3gp', 'mkv'], true) ? $ext : 'mp4');
        rename($part, $final);
        @unlink($meta);

        return new UploadedFile($final, Str::limit($name, 120, ''), $mime, null, true);
    }

    // ------------------------------------------------------------------ publication

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $chunked = $request->filled('upload_id');
        try {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:150'],
                'caption' => ['nullable', 'string', 'max:120'],
                'competition' => ['nullable', 'string', 'max:120'],
                'duration' => ['nullable', 'string', 'max:10'],
                'upload_id' => ['nullable', 'string', 'max:40'],
                'file_name' => ['nullable', 'string', 'max:200'],
                // Envoi classique (sans JavaScript) : limité par la configuration de PHP
                'video' => [$this->data->isDemo() || $chunked ? 'nullable' : 'required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.(self::MAX_MB * 1024)],
                'lang' => ['nullable', 'in:fr,en,none'],
                'title_alt' => ['nullable', 'string', 'max:150'],
                'caption_alt' => ['nullable', 'string', 'max:120'],
                'sub_fr' => ['nullable', 'file', 'max:512'],
                'sub_en' => ['nullable', 'file', 'max:512'],
                'challenge_id' => ['nullable', 'integer'],
            ], [
                'title.required' => __('Donne un titre à ta vidéo.'),
                'video.required' => __('Choisis une vidéo.'),
                'video.mimetypes' => __('Formats acceptés : MP4, MOV ou WebM.'),
                'video.max' => __('La vidéo dépasse :n Mo. Coupe-la ou réduis sa qualité.', ['n' => self::MAX_MB]),
                'sub_fr.max' => __('Fichier de sous-titres trop lourd (512 Ko maximum).'),
                'sub_en.max' => __('Fichier de sous-titres trop lourd (512 Ko maximum).'),
            ]);
            app(\App\Terrain\Growth\ContentGuard::class)->enforce(['title' => $data['title'], 'caption' => $data['caption'] ?? null, 'competition' => $data['competition'] ?? null,
                'title_alt' => $data['title_alt'] ?? null, 'caption_alt' => $data['caption_alt'] ?? null], 'video', $this->data->user()['id'] ?? null);
            $file = $chunked ? $this->assembled((string) $data['upload_id'], (string) ($data['file_name'] ?? 'video.mp4')) : $request->file('video');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
            }
            throw $e;
        }

        // Fichier gardé de côté pour les sous-titres automatiques (avant que l'envoi ne le déplace)
        $keep = null;
        if ($file && $request->boolean('auto') && Subtitles::autoEnabled() && in_array($request->input('lang'), ['fr', 'en'], true)) {
            $keep = storage_path('app/sous-titres-'.uniqid().'.'.($file->getClientOriginalExtension() ?: 'mp4'));
            @copy($file->getRealPath(), $keep);
        }
        try {
            $id = $this->data->publishVideo($data, $file);
        } catch (Throwable $e) {
            report($e);
            $msg = __('L’envoi a échoué. Réessaie avec une meilleure connexion.');

            return $request->expectsJson() ? response()->json(['error' => $msg], 500) : back()->withInput()->withErrors(['video' => $msg]);
        } finally {
            if ($chunked && $file && is_file($file->getPathname())) {
                @unlink($file->getPathname());   // copie restée dans storage/app/envois (déjà déplacée en cas de succès)
            }
        }

        $next = route('feed', $id ? ['v' => $id] : []);
        // Algorithme : analyse d'image (nudité) et validation des vidéos des nouveaux comptes
        $held = null;
        if ($id) {
            try {
                $url = DB::table('local_videos')->where('id', $id)->value('url');
                $path = $url && ! str_starts_with($url, 'http') ? public_path(ltrim(parse_url($url, PHP_URL_PATH) ?: $url, '/')) : null;
                $me = $this->data->user();
                $held = app(\App\Terrain\Growth\ContentGuard::class)->afterPublish($id, $me['id'] ?? null, $me['athlete_id'] ?? null, $path && is_file($path) ? $path : null);
            } catch (Throwable $e) {
                report($e);
            }
        }
        // Participation à un défi directement depuis la publication
        if ($id && ! $held && ! empty($data['challenge_id'])) {
            try {
                $ch = app(\App\Terrain\Growth\Challenges::class);
                $me = $this->data->user();
                $a = $this->data->athlete($me['athlete_id']);
                $c = collect($ch->openFor($a))->firstWhere('id', (int) $data['challenge_id']);
                if ($c && collect($a['videos'])->contains('id', $id)) {
                    $ch->enter($c, $a, $id, $me['id']);
                    $next = route('challenges.show', $c->id);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        // Langue parlée, son, titre dans l'autre langue, sous-titres
        if ($id) {
            try {
                $errors = VideoController::saveLanguageForm($request, $id, $data['title'], $data['caption'] ?? null);
                if ($keep) {
                    $lang = $request->input('lang');
                    app()->terminating(function () use ($id, $keep, $lang) {
                        app(Subtitles::class)->generate($id, $keep, $lang);
                        @unlink($keep);
                    });
                }
                if ($errors) {
                    $next = route('videos.edit', $id);
                    session()->flash('errors', (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag($errors)));
                }
            } catch (ValidationException $e) {
                $next = route('videos.edit', $id);
            } catch (Throwable $e) {
                report($e);
            }
        } elseif ($keep) {
            @unlink($keep);
        }

        // Les abonnés du sportif sont prévenus (seulement si la vidéo est visible)
        if (! $held) try {
            $me = $this->data->user();
            $followers = DB::table('follows_local')->where('athlete_id', $me['athlete_id'])->pluck('follower_key');
            app(\App\Terrain\Growth\Notifier::class)->sendMany($followers, 'video', $me['name'].' a publié une nouvelle vidéo', $data['title'], route('athletes.show', $me['athlete_id']));
        } catch (Throwable $e) {
            report($e);
        }

        if ($held) {
            session()->flash('status', $held);
            $next = route('me');
        } else {
            session()->flash('status', __('Vidéo publiée. Partage-la sur WhatsApp pour avoir des vues !'));
        }

        return $request->expectsJson() ? response()->json(['ok' => true, 'url' => $next]) : redirect($next);
    }
}
