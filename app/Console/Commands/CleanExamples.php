<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Supprime de la base tout ce qui concerne les anciens profils d'exemple (a1…a326, vidéos v1…v1000) :
 * vues, j'aime, commentaires, partages, abonnements, participations aux défis, clubs, détections et témoignages d'exemple.
 * Les vrais comptes (identifiants n-…, vidéos p-…) ne sont pas touchés.
 */
class CleanExamples extends Command
{
    protected $signature = 'frikip:nettoyer-exemples {--forcer : sans demander de confirmation}';

    protected $description = 'Supprime les données liées aux anciens profils d’exemple';

    public function handle(): int
    {
        if (! $this->option('forcer') && ! $this->confirm('Supprimer toutes les données liées aux profils d’exemple ?', true)) {
            return self::SUCCESS;
        }
        $total = 0;
        $del = function (string $table, callable $where) use (&$total) {
            if (! Schema::hasTable($table)) {
                return;
            }
            try {
                $q = DB::table($table);
                $where($q);
                $n = $q->delete();
                $total += $n;
                if ($n) {
                    $this->line("  {$table} : {$n}");
                }
            } catch (Throwable $e) {
                $this->warn("  {$table} : {$e->getMessage()}");
            }
        };
        $video = fn ($col) => fn ($q) => $q->where($col, 'like', 'v%')->where($col, 'not like', '%-%');
        $athlete = fn ($col) => fn ($q) => $q->where($col, 'like', 'a%')->where($col, 'not like', '%-%');
        foreach (['video_views', 'video_likes_local', 'video_comments_local', 'video_shares', 'video_meta', 'video_subtitles'] as $t) {
            $del($t, $video('video_id'));
        }
        $del('video_views', $athlete('athlete_id'));
        foreach (['profile_views', 'follows_local', 'challenge_entries'] as $t) {
            $del($t, $athlete('athlete_id'));
        }
        foreach (['organizations', 'events', 'success_stories'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'demo')) {
                $del($t, fn ($q) => $q->where('demo', true));
            }
        }
        $del('demo_messages', fn ($q) => $q->where('room', '!=', 'reel'));
        $this->info("{$total} ligne(s) d’exemple supprimée(s). Les vrais comptes sont intacts.");

        return self::SUCCESS;
    }
}
