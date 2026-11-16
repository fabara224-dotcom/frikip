<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;

/**
 * Copie toutes les données d'une ancienne base SQLite (database.sqlite) vers la base actuelle
 * (PostgreSQL ou MySQL sur Laravel Cloud). À lancer une seule fois, APRÈS « php artisan migrate ».
 */
class ImportSqlite extends Command
{
    protected $signature = 'frikip:importer-sqlite {fichier : chemin du fichier database.sqlite} {--vider : vider les tables avant de copier}';

    protected $description = 'Importe les données d’une base SQLite dans la base configurée';

    /** Tables « parents » à copier en premier (clés étrangères). */
    private const FIRST = ['organizations', 'events', 'challenges'];

    private const SKIP = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public function handle(): int
    {
        $file = (string) $this->argument('fichier');
        if (! is_file($file)) {
            $this->error("Fichier introuvable : {$file}");

            return self::FAILURE;
        }
        if (DB::getDriverName() === 'sqlite') {
            $this->error('La base actuelle est déjà SQLite : configure d’abord PostgreSQL ou MySQL (DB_CONNECTION).');

            return self::FAILURE;
        }
        $src = new PDO('sqlite:'.$file);
        $src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tables = $src->query("select name from sqlite_master where type='table' and name not like 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        usort($tables, fn ($a, $b) => (in_array($b, self::FIRST, true) <=> in_array($a, self::FIRST, true)) ?: array_search($a, self::FIRST) <=> array_search($b, self::FIRST));
        $total = 0;
        foreach ($tables as $table) {
            if (in_array($table, self::SKIP, true) || ! Schema::hasTable($table)) {
                continue;
            }
            $cols = Schema::getColumnListing($table);
            $types = collect(Schema::getColumns($table))->pluck('type_name', 'name')->map(fn ($t) => strtolower((string) $t));
            if ($this->option('vider')) {
                DB::table($table)->delete();
            }
            $rows = $src->query('select * from "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC);
            $count = 0;
            $dup = 0;
            foreach (array_chunk($rows, 300) as $chunk) {
                $chunk = array_map(function ($r) use ($cols, $types) {
                    $r = array_intersect_key($r, array_flip($cols));
                    foreach ($r as $k => $v) {
                        if ($v !== null && in_array($types[$k] ?? '', ['bool', 'boolean', 'tinyint'], true)) {
                            $r[$k] = (bool) $v;
                        }
                    }

                    return $r;
                }, $chunk);
                try {
                    DB::table($table)->insert($chunk);
                    $count += count($chunk);
                } catch (\Throwable $e) {
                    foreach ($chunk as $one) {   // ligne par ligne pour ne perdre que les lignes en erreur
                        try {
                            DB::table($table)->insert($one);
                            $count++;
                        } catch (\Throwable $e2) {
                            if (preg_match('/unique|duplicate|23505|23000/i', $e2->getMessage())) {
                                $dup++;   // déjà présente (ex. données créées par les migrations)
                            } else {
                                $this->warn("  {$table} : ligne ignorée (".mb_substr($e2->getMessage(), 0, 160).')');
                            }
                        }
                    }
                }
            }
            $this->resetSequence($table, $cols);
            $this->line(str_pad($table, 34).$count.' ligne(s)'.($dup ? " ({$dup} déjà présente(s))" : ''));
            $total += $count;
        }
        $this->info("Import terminé : {$total} ligne(s) copiées.");

        return self::SUCCESS;
    }

    /** PostgreSQL : remet le compteur des identifiants après l'import. */
    private function resetSequence(string $table, array $cols): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! in_array('id', $cols, true)) {
            return;
        }
        try {
            $seq = DB::selectOne('select pg_get_serial_sequence(?, ?) as s', [$table, 'id'])->s ?? null;
            if ($seq) {
                DB::statement("select setval(?, coalesce((select max(id) from \"{$table}\"), 0) + 1, false)", [$seq]);
            }
        } catch (\Throwable) {
        }
    }
}
