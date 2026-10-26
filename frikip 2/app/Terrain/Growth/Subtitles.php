<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sous-titres des vidéos (WebVTT).
 *
 * Règle d'affichage : la vidéo parle français et la page est en anglais → sous-titres anglais ;
 * la vidéo parle anglais et la page est en français → sous-titres français ; sinon pas de sous-titres.
 *
 * D'où viennent les sous-titres :
 *  - fichier .srt ou .vtt envoyé par le sportif (publication ou page « Langue et sous-titres ») ;
 *  - génération automatique si OPENAI_API_KEY est rempli dans .env (transcription Whisper + traduction) ;
 *  - vidéos commentées d'exemple : resources/data/sous-titres/.
 */
class Subtitles
{
    public const LANGS = ['fr' => 'Français', 'en' => 'English'];

    private array $available = [];

    /** @return array<string, list<string>> langues de sous-titres disponibles par vidéo */
    public function available(array $videoIds): array
    {
        $missing = array_values(array_diff($videoIds, array_keys($this->available)));
        if ($missing) {
            static $demo = null;
            if ($demo === null) {
                $demo = [];
                foreach (glob(resource_path('data/sous-titres/*.vtt')) ?: [] as $f) {
                    [$vid, $l] = explode('.', basename($f, '.vtt')) + [1 => ''];
                    $demo[$vid][] = $l;
                }
            }
            foreach ($missing as $id) {
                $this->available[$id] = $demo[$id] ?? [];
            }
            try {
                foreach (array_chunk($missing, 500) as $chunk) {
                    foreach (DB::table('video_subtitles')->whereIn('video_id', $chunk)->get(['video_id', 'lang']) as $r) {
                        $this->available[$r->video_id] = array_values(array_unique(array_merge($this->available[$r->video_id], [$r->lang])));
                    }
                }
            } catch (Throwable) {
            }
        }

        return array_intersect_key($this->available, array_flip($videoIds));
    }

    private function demoFile(string $videoId, string $lang): string
    {
        return resource_path('data/sous-titres/'.preg_replace('/[^A-Za-z0-9_-]/', '', $videoId).".{$lang}.vtt");
    }

    public function vtt(string $videoId, string $lang): ?string
    {
        if (! isset(self::LANGS[$lang])) {
            return null;
        }
        try {
            $row = DB::table('video_subtitles')->where('video_id', $videoId)->where('lang', $lang)->value('vtt');
            if ($row) {
                return $row;
            }
        } catch (Throwable) {
        }
        $f = $this->demoFile($videoId, $lang);

        return is_file($f) ? (string) file_get_contents($f) : null;
    }

    /** Piste à afficher d'office pour cette vidéo, selon la langue de la page (null = pas de sous-titres). */
    public static function defaultFor(array $v): ?string
    {
        $page = App::getLocale();
        $spoken = $v['lang'] ?? 'none';
        if (! isset(self::LANGS[$spoken]) || $spoken === $page) {
            return null;
        }

        return in_array($page, $v['tracks'] ?? [], true) ? $page : null;
    }

    /** Enregistre un fichier .srt ou .vtt (converti en WebVTT). */
    public function store(string $videoId, string $lang, string $content, string $source = 'manuel'): bool
    {
        $vtt = self::toVtt($content);
        if (! $vtt || ! isset(self::LANGS[$lang])) {
            return false;
        }
        DB::table('video_subtitles')->updateOrInsert(['video_id' => $videoId, 'lang' => $lang], ['vtt' => $vtt, 'source' => $source, 'updated_at' => now(), 'created_at' => now()]);
        unset($this->available[$videoId]);

        return true;
    }

    public function remove(string $videoId, string $lang): void
    {
        DB::table('video_subtitles')->where('video_id', $videoId)->where('lang', $lang)->delete();
        unset($this->available[$videoId]);
    }

    /** SRT ou VTT → WebVTT propre (sans balises HTML, horodatages vérifiés). */
    public static function toVtt(string $raw): ?string
    {
        $raw = str_replace(["\r\n", "\r"], "\n", trim(preg_replace('/^\xEF\xBB\xBF/', '', $raw)));
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $blocks = preg_split('/\n{2,}/', preg_replace('/^WEBVTT[^\n]*\n*/', '', $raw));
        $out = [];
        $time = '/^(\d{1,2}:)?(\d{1,2}):(\d{2})[.,](\d{3})\s*-->\s*(\d{1,2}:)?(\d{1,2}):(\d{2})[.,](\d{3})/';
        foreach ($blocks as $b) {
            $lines = array_values(array_filter(explode("\n", trim($b)), fn ($l) => $l !== ''));
            foreach ($lines as $i => $l) {
                if (preg_match($time, $l, $m)) {
                    $start = sprintf('%02d:%02d:%02d.%03d', (int) rtrim($m[1], ':'), (int) $m[2], (int) $m[3], (int) $m[4]);
                    $end = sprintf('%02d:%02d:%02d.%03d', (int) rtrim($m[5], ':'), (int) $m[6], (int) $m[7], (int) $m[8]);
                    $text = array_map(fn ($t) => trim(strip_tags($t)), array_slice($lines, $i + 1));
                    $text = array_filter($text, fn ($t) => $t !== '');
                    if ($text) {
                        $out[] = "{$start} --> {$end}\n".implode("\n", array_map(fn ($t) => str_replace('-->', '→', $t), $text));
                    }
                    break;
                }
            }
        }

        return $out ? "WEBVTT\n\n".implode("\n\n", $out)."\n" : null;
    }

    // ------------------------------------------------------------------ génération automatique (facultatif)

    public static function autoEnabled(): bool
    {
        return (string) config('terrain.openai.key') !== '';
    }

    /**
     * Transcrit la vidéo dans sa langue puis traduit dans l'autre langue.
     * Appelé après l'envoi de la page (l'utilisateur n'attend pas).
     */
    public function generate(string $videoId, string $file, string $lang): bool
    {
        if (! self::autoEnabled() || ! isset(self::LANGS[$lang]) || ! is_file($file)) {
            return false;
        }
        $meta = app(Engagement::class);
        $meta->saveMeta($videoId, ['subtitle_status' => 'en_cours']);
        try {
            $audio = $this->audioFor($file);
            $key = (string) config('terrain.openai.key');
            $r = Http::withToken($key)->timeout(600)->attach('file', fopen($audio, 'r'), basename($audio))
                ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => 'whisper-1', 'language' => $lang, 'response_format' => 'vtt']);
            if (! $r->successful() || ! str_contains($r->body(), '-->')) {
                throw new \RuntimeException('Transcription impossible ('.$r->status().')');
            }
            $this->store($videoId, $lang, $r->body(), 'auto');
            $other = $lang === 'fr' ? 'en' : 'fr';
            if ($translated = $this->translateVtt($r->body(), $other)) {
                $this->store($videoId, $other, $translated, 'auto');
            }
            $meta->saveMeta($videoId, ['subtitle_status' => 'pret']);
            if ($audio !== $file) {
                @unlink($audio);
            }

            return true;
        } catch (Throwable $e) {
            report($e);
            $meta->saveMeta($videoId, ['subtitle_status' => 'erreur']);

            return false;
        }
    }

    /** Whisper accepte 25 Mo maximum : au-delà, on extrait le son avec ffmpeg (s'il est installé). */
    private function audioFor(string $file): string
    {
        if (filesize($file) < 24 * 1024 * 1024) {
            return $file;
        }
        $out = storage_path('app/tmp-'.uniqid().'.mp3');
        @exec('ffmpeg -v error -y -i '.escapeshellarg($file).' -vn -ac 1 -ar 16000 -b:a 32k '.escapeshellarg($out).' 2>&1', $o, $code);
        if ($code !== 0 || ! is_file($out)) {
            throw new \RuntimeException('Vidéo trop lourde pour les sous-titres automatiques (installe ffmpeg pour extraire le son).');
        }

        return $out;
    }

    /** Traduit le texte des sous-titres en gardant les horodatages. */
    public function translateVtt(string $vtt, string $to): ?string
    {
        $vtt = self::toVtt($vtt);
        if (! $vtt) {
            return null;
        }
        preg_match_all('/(\d{2}:\d{2}:\d{2}\.\d{3} --> \d{2}:\d{2}:\d{2}\.\d{3})\n(.+?)(?=\n\n|\n*$)/s', $vtt, $m, PREG_SET_ORDER);
        if (! $m) {
            return null;
        }
        $texts = array_map(fn ($x) => str_replace("\n", ' ', $x[2]), $m);
        $out = $this->translateLines($texts, $to);
        if (! $out || count($out) !== count($texts)) {
            return null;
        }
        $cues = [];
        foreach ($m as $i => $x) {
            $cues[] = $x[1]."\n".trim((string) $out[$i]);
        }

        return "WEBVTT\n\n".implode("\n\n", $cues)."\n";
    }

    /** @param list<string> $lines */
    public function translateLines(array $lines, string $to): ?array
    {
        if (! self::autoEnabled() || ! $lines) {
            return null;
        }
        $target = $to === 'fr' ? 'French' : 'English';
        try {
            $r = Http::withToken((string) config('terrain.openai.key'))->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('terrain.openai.model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => "Translate each line into {$target}. It is sports commentary or a video title from West Africa. Keep names, numbers and tone. Answer as JSON: {\"lines\": [...]} with exactly the same number of lines."],
                    ['role' => 'user', 'content' => json_encode(['lines' => array_values($lines)], JSON_UNESCAPED_UNICODE)],
                ],
            ]);
            $data = json_decode((string) ($r->json('choices.0.message.content') ?? ''), true);

            return is_array($data['lines'] ?? null) ? array_values($data['lines']) : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
