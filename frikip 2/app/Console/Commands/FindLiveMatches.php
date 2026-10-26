<?php

namespace App\Console\Commands;

use App\Terrain\Growth\Algorithm;
use App\Terrain\Growth\LiveFinder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Cherche les matchs en direct sur YouTube (API officielle) et les ajoute à « En direct ».
 * Lancé par le planificateur ; respecte l'intervalle calculé pour le quota gratuit (sauf --maintenant).
 */
class FindLiveMatches extends Command
{
    protected $signature = 'frikip:directs {--maintenant : chercher tout de suite, sans attendre l’intervalle}';

    protected $description = 'Cherche les matchs diffusés en direct sur YouTube et les ajoute à la page En direct';

    public function handle(LiveFinder $finder, Algorithm $algo): int
    {
        if (! LiveFinder::configured()) {
            $this->warn('Ajoute YOUTUBE_API_KEY dans le fichier .env (clé gratuite sur https://console.cloud.google.com).');

            return self::SUCCESS;
        }
        if (! $algo->get('yt_enabled')) {
            $this->info('Recherche désactivée dans Admin > Direct.');

            return self::SUCCESS;
        }
        if (! $this->option('maintenant') && ! Cache::add('frikip.yt.sync', now()->toIso8601String(), now()->addMinutes($finder->interval()))) {
            $this->info('Dernière recherche trop récente (une toutes les '.$finder->interval().' min).');

            return self::SUCCESS;
        }
        $r = $finder->sync();
        $this->info("{$r['found']} direct(s) trouvé(s), {$r['added']} nouveau(x), {$r['ended']} terminé(s).");
        if ($r['error']) {
            $this->warn('YouTube : '.$r['error']);
        }

        return self::SUCCESS;
    }
}
