<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Fichiers des membres (vidéos, photos, stories, pièces jointes…) sur un stockage permanent.
 *
 * Le site écrit toujours ses fichiers sur le disque local (public/uploads ou storage/app), comme avant.
 * Si un stockage externe est configuré (bucket Laravel Cloud, S3, Cloudflare R2…), chaque fichier y est
 * aussi copié : ainsi rien ne se perd quand le serveur redémarre ou est redéployé.
 *  - fichiers publics  : clé « uploads/... » (même chemin que sur le site), servis par /uploads/{chemin}
 *  - fichiers privés   : clé « private/... » (pièces d'identité, médias des messages), jamais publics
 *
 * Réglage : MEDIA_DISK=nom-du-disque (par défaut « s3 » dès que AWS_BUCKET est rempli). Vide = tout reste local.
 */
class Media
{
    public static function disk(): ?string
    {
        $disk = config('filesystems.media_disk');

        return $disk && config("filesystems.disks.{$disk}") ? $disk : null;
    }

    public static function enabled(): bool
    {
        return self::disk() !== null;
    }

    /** Chemin local d'un fichier (public : relatif à public/, privé : relatif à storage/app/). */
    public static function local(string $rel, bool $private = false): string
    {
        $rel = ltrim($rel, '/');

        return $private ? storage_path('app/'.$rel) : public_path($rel);
    }

    private static function key(string $rel, bool $private): string
    {
        return ($private ? 'private/' : '').ltrim($rel, '/');
    }

    /** Copie le fichier local vers le stockage permanent (sans effet si aucun stockage n'est configuré). */
    public static function push(?string $rel, bool $private = false): void
    {
        if (! $rel || ! self::enabled() || str_starts_with($rel, 'http')) {
            return;
        }
        $path = self::local($rel, $private);
        if (! is_file($path)) {
            return;
        }
        try {
            $stream = fopen($path, 'r');
            Storage::disk(self::disk())->put(self::key($rel, $private), $stream, ['visibility' => $private ? 'private' : 'public']);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Garantit que le fichier existe sur le disque local (le récupère du stockage permanent si besoin). */
    public static function ensureLocal(?string $rel, bool $private = false): ?string
    {
        if (! $rel || str_starts_with($rel, 'http')) {
            return null;
        }
        $path = self::local($rel, $private);
        if (is_file($path)) {
            return $path;
        }
        if (! self::enabled()) {
            return null;
        }
        try {
            $disk = Storage::disk(self::disk());
            $key = self::key($rel, $private);
            if (! $disk->exists($key)) {
                return null;
            }
            @mkdir(dirname($path), 0775, true);
            $in = $disk->readStream($key);
            $out = fopen($path, 'w');
            stream_copy_to_stream($in, $out);
            fclose($out);
            if (is_resource($in)) {
                fclose($in);
            }

            return is_file($path) ? $path : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Supprime le fichier partout (local et stockage permanent). */
    public static function delete(?string $rel, bool $private = false): void
    {
        if (! $rel || str_starts_with($rel, 'http')) {
            return;
        }
        @unlink(self::local($rel, $private));
        if (self::enabled()) {
            try {
                Storage::disk(self::disk())->delete(self::key($rel, $private));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /** Adresse publique d'un fichier du stockage permanent (null si absent). */
    public static function url(string $rel): ?string
    {
        if (! self::enabled()) {
            return null;
        }
        $disk = Storage::disk(self::disk());
        $key = self::key($rel, false);
        try {
            if (! $disk->exists($key)) {
                return null;
            }
            if (config('filesystems.media_signed')) {   // bucket privé : lien signé valable 6 h
                return $disk->temporaryUrl($key, now()->addHours(6));
            }

            return $disk->url($key);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
