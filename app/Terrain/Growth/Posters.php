<?php

namespace App\Terrain\Growth;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Image d'aperçu (poster) des vidéos publiées, tirée de la vidéo avec ffmpeg quand il est installé sur le serveur.
 * Sans ffmpeg, rien ne casse : l'interface affiche alors le début de la vidéo.
 */
class Posters
{
    public static function available(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = function_exists('exec') && trim((string) @shell_exec('command -v ffmpeg 2>/dev/null')) !== '';
        }

        return $ok;
    }

    public static function make(string $videoId): ?string
    {
        if (! self::available()) {
            return null;
        }
        try {
            $row = DB::table('local_videos')->where('id', $videoId)->first();
            if (! $row || $row->poster || str_starts_with((string) $row->url, 'http')) {
                return $row->poster ?? null;
            }
            $src = public_path($row->url);
            if (! is_file($src)) {
                return null;
            }
            $dir = public_path('uploads/posters');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $rel = 'uploads/posters/'.$videoId.'.jpg';
            $cmd = 'ffmpeg -loglevel error -y -ss 1 -i '.escapeshellarg($src).' -frames:v 1 -vf scale=540:-2 -q:v 4 '.escapeshellarg(public_path($rel)).' 2>&1';
            @exec($cmd, $out, $code);
            if ($code !== 0 || ! is_file(public_path($rel))) {
                // vidéo de moins d'une seconde : première image
                @exec('ffmpeg -loglevel error -y -i '.escapeshellarg($src).' -frames:v 1 -vf scale=540:-2 -q:v 4 '.escapeshellarg(public_path($rel)).' 2>&1', $out, $code);
            }
            if (is_file(public_path($rel))) {
                DB::table('local_videos')->where('id', $videoId)->update(['poster' => $rel]);

                return $rel;
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }
}
