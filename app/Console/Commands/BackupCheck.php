<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;
use Throwable;
use ZipArchive;

/** php artisan frikip:sauvegarde-test — ouvre la dernière sauvegarde à part et vérifie qu'elle se restaure (test de restauration). */
class BackupCheck extends Command
{
    protected $signature = 'frikip:sauvegarde-test';

    protected $description = 'Vérifie que la dernière sauvegarde peut être restaurée';

    public function handle(): int
    {
        $all = glob(storage_path('sauvegardes/frikip-*.zip')) ?: [];
        rsort($all);
        if (! $all) {
            $this->error('Aucune sauvegarde. Lance d’abord : php artisan frikip:sauvegarde');

            return self::FAILURE;
        }
        $zip = new ZipArchive;
        if ($zip->open($all[0]) !== true) {
            $this->error('Sauvegarde illisible : '.basename($all[0]));

            return self::FAILURE;
        }
        $tmp = storage_path('sauvegardes/test-'.uniqid());
        mkdir($tmp);
        $zip->extractTo($tmp);
        $zip->close();
        $ok = false;
        try {
            if (is_file("{$tmp}/database.sqlite")) {
                $pdo = new PDO('sqlite:'.$tmp.'/database.sqlite');
                $check = $pdo->query('PRAGMA integrity_check')->fetchColumn();
                $counts = [];
                foreach (['accounts', 'local_athletes', 'local_videos', 'members'] as $t) {
                    $counts[$t] = (int) $pdo->query("select count(*) from {$t}")->fetchColumn();
                }
                $ok = $check === 'ok';
                $this->line('Intégrité : '.$check);
                foreach ($counts as $t => $n) {
                    $this->line("  {$t} : {$n}");
                }
            } elseif (is_file("{$tmp}/database.sql")) {
                $ok = filesize("{$tmp}/database.sql") > 1000 && str_contains((string) file_get_contents("{$tmp}/database.sql", false, null, 0, 20000), 'CREATE TABLE');
                $this->line('Fichier SQL : '.round(filesize("{$tmp}/database.sql") / 1024).' Ko');
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        } finally {
            array_map('unlink', glob("{$tmp}/*.*") ?: []);
            @exec('rm -rf '.escapeshellarg($tmp));
        }
        $ok ? $this->info('La sauvegarde '.basename($all[0]).' se restaure correctement.') : $this->error('La sauvegarde '.basename($all[0]).' est abîmée.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
