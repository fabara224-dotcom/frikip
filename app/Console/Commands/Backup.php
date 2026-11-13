<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * php artisan frikip:sauvegarde [--videos]
 * Sauvegarde la base de données (et, avec --videos, les vidéos et images envoyées) dans storage/sauvegardes/.
 * Les 7 dernières sauvegardes sont gardées. Tous les jours à 02:30 (tâche planifiée).
 * Copie ensuite le fichier .zip hors du serveur (Google Drive, ton ordinateur) : une sauvegarde sur le même serveur ne protège pas d'une panne du serveur.
 */
class Backup extends Command
{
    protected $signature = 'frikip:sauvegarde {--videos : inclure les vidéos et images envoyées (fichier plus lourd)} {--garder=7 : nombre de sauvegardes à garder}';

    protected $description = 'Sauvegarde la base de données (et les vidéos) de FootProWorld';

    public function handle(): int
    {
        $dir = storage_path('sauvegardes');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $name = 'frikip-'.now()->format('Y-m-d-His').($this->option('videos') ? '-complet' : '').'.zip';
        $zip = new ZipArchive;
        if ($zip->open("{$dir}/{$name}", ZipArchive::CREATE) !== true) {
            $this->error('Impossible de créer le fichier de sauvegarde.');

            return self::FAILURE;
        }
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            $db = (string) config('database.connections.sqlite.database');
            // copie cohérente même si le site est utilisé pendant la sauvegarde
            $tmp = "{$dir}/base-".uniqid().'.sqlite';
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($tmp));
            $zip->addFile($tmp, 'database.sqlite');
        } else {
            $tmp = "{$dir}/base-".uniqid().'.sql';
            $c = config("database.connections.{$driver}");
            $cmd = sprintf('mysqldump --single-transaction --quick -h %s -P %s -u %s %s %s > %s 2>/dev/null', escapeshellarg($c['host']), escapeshellarg((string) $c['port']),
                escapeshellarg($c['username']), $c['password'] !== '' ? '-p'.escapeshellarg($c['password']) : '', escapeshellarg($c['database']), escapeshellarg($tmp));
            exec($cmd, $o, $code);
            if ($code !== 0 || ! filesize($tmp)) {
                $this->error('mysqldump a échoué : vérifie qu’il est installé sur le serveur.');
                @unlink($tmp);

                return self::FAILURE;
            }
            $zip->addFile($tmp, 'database.sql');
        }
        $zip->addFromString('LISEZ-MOI.txt', "Sauvegarde FootProWorld du ".now()->format('d/m/Y H:i')."\n\nRestaurer la base (SQLite) : remplacer database/database.sqlite par database.sqlite de ce fichier.\nRestaurer la base (MySQL) : mysql -u UTILISATEUR -p BASE < database.sql\nVidéos : copier le dossier uploads/ dans public/.\nLe fichier .env (clés) n'est PAS dans la sauvegarde : garde-le à part, en lieu sûr.\n");
        if ($this->option('videos') && is_dir(public_path('uploads'))) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path('uploads'), \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $zip->addFile($f->getPathname(), 'uploads/'.ltrim(substr($f->getPathname(), strlen(public_path('uploads'))), '/'));
            }
        }
        $zip->close();
        @unlink($tmp);
        // On garde les N dernières
        $all = glob("{$dir}/frikip-*.zip") ?: [];
        rsort($all);
        foreach (array_slice($all, max(1, (int) $this->option('garder'))) as $old) {
            @unlink($old);
        }
        $this->info("Sauvegarde créée : storage/sauvegardes/{$name} (".round(filesize("{$dir}/{$name}") / 1048576, 1).' Mo)');

        return self::SUCCESS;
    }
}
