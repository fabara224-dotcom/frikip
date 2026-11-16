<?php

namespace App\Console\Commands;

use App\Support\Media;
use Illuminate\Console\Command;

/** Envoie les fichiers déjà présents (public/uploads, pièces privées) vers le stockage permanent (bucket). */
class MediaPush extends Command
{
    protected $signature = 'frikip:fichiers-vers-bucket {--prives : inclure aussi les pièces privées (vérifications, médias des messages)}';

    protected $description = 'Copie tous les fichiers des membres vers le stockage permanent configuré (MEDIA_DISK)';

    public function handle(): int
    {
        if (! Media::enabled()) {
            $this->error('Aucun stockage permanent configuré (MEDIA_DISK ou AWS_BUCKET vide).');

            return self::FAILURE;
        }
        $sets = [[public_path('uploads'), 'uploads', false]];
        if ($this->option('prives')) {
            $sets[] = [storage_path('app/verifications'), 'verifications', true];
            $sets[] = [storage_path('app/demo-media'), 'demo-media', true];
        }
        $n = 0;
        foreach ($sets as [$dir, $prefix, $private]) {
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (! $f->isFile() || str_starts_with($f->getFilename(), '.')) {
                    continue;
                }
                $rel = $prefix.'/'.ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($dir))), '/');
                Media::push($rel, $private);
                $n++;
                if ($n % 50 === 0) {
                    $this->line("… {$n} fichiers envoyés");
                }
            }
        }
        $this->info("{$n} fichier(s) copiés vers le stockage permanent.");

        return self::SUCCESS;
    }
}
