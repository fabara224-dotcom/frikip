<?php

namespace App\Console\Commands;

use App\Terrain\Growth\Discover;
use Illuminate\Console\Command;

/** php artisan frikip:decouvrir — ajoute des vidéos de foot YouTube (intégrables, créditées) jusqu'au maximum réglé. */
class ImportDiscover extends Command
{
    protected $signature = 'frikip:decouvrir {--max= : nombre maximum de vidéos (sinon Admin > Découvrir)} {--pages=2 : pages de 50 résultats par thème}';

    protected $description = 'Ajoute des vidéos de football YouTube à l’onglet Découvrir';

    public function handle(Discover $d): int
    {
        if (! Discover::configured()) {
            $this->error('Ajoute YOUTUBE_API_KEY dans le fichier .env (clé gratuite sur console.cloud.google.com).');

            return self::FAILURE;
        }
        $r = $d->import($this->option('max') ? (int) $this->option('max') : null, max(1, (int) $this->option('pages')));
        $this->info("{$r['added']} vidéo(s) ajoutée(s), {$r['total']} au total.");
        if ($r['error']) {
            $this->warn('YouTube : '.$r['error']);
        }

        return self::SUCCESS;
    }
}
