<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Éditeur vidéo de la page « Publier » (façon TikTok) : filtre, coupe (début / fin), vitesse, son, texte.
 *
 * - Si ffmpeg est installé sur le serveur, la vidéo est refaite une fois pour toutes après l'envoi
 *   (filtre, coupe, vitesse, son), compressée en MP4 léger et une miniature est créée.
 * - Sinon, les réglages sont appliqués pendant la lecture (filtre CSS, début / fin, vitesse).
 * Le texte est toujours affiché par-dessus la vidéo (il reste net et traduisible).
 */
class VideoEditor
{
    /** clé => [nom affiché, filtre CSS (lecture), filtre ffmpeg (fichier)] */
    public const FILTERS = [
        'normal' => ['Normal', 'none', ''],
        'eclatant' => ['Éclatant', 'saturate(1.4) contrast(1.1)', 'eq=saturation=1.4:contrast=1.1'],
        'match' => ['Nuit de match', 'contrast(1.25) brightness(.95) saturate(1.2)', 'eq=contrast=1.25:brightness=-0.03:saturation=1.2'],
        'chaud' => ['Chaud', 'sepia(.25) saturate(1.3) hue-rotate(-8deg)', 'colorbalance=rs=.08:gs=.02:bs=-.08,eq=saturation=1.2'],
        'froid' => ['Froid', 'hue-rotate(12deg) saturate(1.1) brightness(1.04)', 'colorbalance=rs=-.06:bs=.08,eq=brightness=0.03'],
        'nb' => ['Noir et blanc', 'grayscale(1) contrast(1.1)', 'hue=s=0,eq=contrast=1.1'],
        'retro' => ['Rétro', 'sepia(.55) contrast(.95) saturate(1.1)', 'colorchannelmixer=.62:.5:.1:0:.25:.75:.1:0:.2:.4:.5,eq=contrast=0.95'],
        'doux' => ['Doux', 'brightness(1.08) contrast(.92) saturate(.9)', 'eq=brightness=0.06:contrast=0.92:saturation=0.9'],
    ];

    public const SPEEDS = [0.5, 1, 1.5, 2];

    public const TEXT_POS = ['haut', 'milieu', 'bas'];

    public const TEXT_COLORS = ['blanc', 'jaune', 'vert'];

    /** Réglages reçus du formulaire → réglages sûrs. */
    public static function sanitize(array $in): ?array
    {
        $f = (string) ($in['filter'] ?? 'normal');
        $e = [
            'filter' => isset(self::FILTERS[$f]) ? $f : 'normal',
            'start' => max(0, round((float) ($in['start'] ?? 0), 2)),
            'end' => max(0, round((float) ($in['end'] ?? 0), 2)),
            'speed' => in_array((float) ($in['speed'] ?? 1), self::SPEEDS, false) ? (float) $in['speed'] : 1.0,
            'mute' => ! empty($in['mute']),
            'text_pos' => in_array($in['text_pos'] ?? '', self::TEXT_POS, true) ? $in['text_pos'] : 'milieu',
            'text_color' => in_array($in['text_color'] ?? '', self::TEXT_COLORS, true) ? $in['text_color'] : 'blanc',
            'baked' => false,
        ];
        if ($e['end'] > 0 && $e['end'] <= $e['start'] + 0.5) {
            $e['end'] = 0;   // coupe incohérente : on garde toute la vidéo
        }
        $default = $e['filter'] === 'normal' && $e['start'] == 0 && $e['end'] == 0 && $e['speed'] == 1.0 && ! $e['mute'] && $e['text_pos'] === 'milieu' && $e['text_color'] === 'blanc';

        return $default ? null : $e;
    }

    public static function decode(?string $json): ?array
    {
        $e = $json ? json_decode($json, true) : null;

        return is_array($e) ? $e : null;
    }

    /** Attributs à mettre sur <video> pour appliquer les réglages pendant la lecture (si le fichier n'a pas été refait). */
    public static function playback(?array $e): array
    {
        if (! $e) {
            return [];
        }
        $out = ['text_pos' => $e['text_pos'] ?? 'milieu', 'text_color' => $e['text_color'] ?? 'blanc'];
        if (empty($e['baked'])) {
            $out += ['css' => self::FILTERS[$e['filter'] ?? 'normal'][1] ?? 'none', 'start' => (float) ($e['start'] ?? 0), 'end' => (float) ($e['end'] ?? 0),
                'speed' => (float) ($e['speed'] ?? 1), 'mute' => ! empty($e['mute'])];
        }

        return $out;
    }

    public static function ffmpeg(): ?string
    {
        foreach ([(string) config('terrain.ffmpeg', ''), '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'] as $p) {
            if ($p !== '' && is_executable($p)) {
                return $p;
            }
        }
        $w = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));

        return $w !== '' && is_executable($w) ? $w : null;
    }

    /**
     * Refait la vidéo avec ffmpeg (après la réponse au membre) : coupe, filtre, vitesse, son, compression, miniature.
     * Sans ffmpeg : ne fait rien (les réglages restent appliqués à la lecture).
     */
    public function process(string $videoId): bool
    {
        $bin = self::ffmpeg();
        @set_time_limit(900);
        $row = DB::table('local_videos')->where('id', $videoId)->first();
        if (! $bin || ! $row || str_starts_with((string) $row->url, 'http')) {
            return false;
        }
        $src = public_path($row->url);
        if (! is_file($src)) {
            return false;
        }
        $e = self::decode($row->edit ?? null) ?? [];
        $vf = ["scale=-2:'min(1280,ih)'"];
        if (($fx = self::FILTERS[$e['filter'] ?? 'normal'][2] ?? '') !== '') {
            $vf[] = $fx;
        }
        $speed = (float) ($e['speed'] ?? 1);
        if ($speed != 1.0) {
            $vf[] = 'setpts=PTS/'.$speed;
        }
        $args = [escapeshellarg($bin), '-v error -y'];
        if (! empty($e['start'])) {
            $args[] = '-ss '.escapeshellarg((string) $e['start']);
        }
        if (! empty($e['end'])) {
            $args[] = '-to '.escapeshellarg((string) $e['end']);
        }
        $args[] = '-i '.escapeshellarg($src);
        $args[] = '-vf '.escapeshellarg(implode(',', $vf));
        if (! empty($e['mute'])) {
            $args[] = '-an';
        } else {
            $args[] = '-c:a aac -b:a 96k'.($speed != 1.0 ? ' -af '.escapeshellarg('atempo='.$speed) : '');
        }
        $name = $videoId.'-'.substr(md5((string) microtime(true)), 0, 6).'.mp4';
        $dest = public_path('uploads/videos/'.$name);
        $args[] = '-c:v libx264 -preset veryfast -crf 26 -pix_fmt yuv420p -movflags +faststart '.escapeshellarg($dest).' 2>&1';
        $out = (string) @shell_exec(implode(' ', $args));
        if (! is_file($dest) || filesize($dest) < 1000) {
            Log::warning('Éditeur vidéo : ffmpeg a échoué', ['video' => $videoId, 'sortie' => mb_substr($out, 0, 500)]);
            @unlink($dest);

            return false;
        }
        // Miniature (couverture) à 1 seconde
        $posterDir = public_path('uploads/posters');
        if (! is_dir($posterDir)) {
            @mkdir($posterDir, 0755, true);
        }
        $poster = 'uploads/posters/'.$videoId.'.jpg';
        @shell_exec(escapeshellarg($bin).' -v error -y -ss 1 -i '.escapeshellarg($dest).' -frames:v 1 -vf '.escapeshellarg('scale=480:-2').' -q:v 4 '.escapeshellarg(public_path($poster)).' 2>/dev/null');
        $dur = (float) trim((string) @shell_exec('ffprobe -v error -show_entries format=duration -of csv=p=0 '.escapeshellarg($dest).' 2>/dev/null'));
        $e['baked'] = true;
        try {
            DB::table('local_videos')->where('id', $videoId)->update([
                'url' => 'uploads/videos/'.$name, 'poster' => is_file(public_path($poster)) ? $poster : $row->poster,
                'duration' => $dur > 0 ? sprintf('%d:%02d', intdiv((int) round($dur), 60), (int) round($dur) % 60) : $row->duration,
                'edit' => json_encode($e), 'updated_at' => now(),
            ]);
            @unlink($src);
        } catch (Throwable $ex) {
            report($ex);

            return false;
        }

        return true;
    }
}
