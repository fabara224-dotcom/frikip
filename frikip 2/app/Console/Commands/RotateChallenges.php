<?php

namespace App\Console\Commands;

use App\Terrain\Growth\Challenges;
use Illuminate\Console\Command;

/** Clôture le défi de la semaine terminé (annonce du gagnant) et ouvre le suivant. */
class RotateChallenges extends Command
{
    protected $signature = 'terrain:defis';

    protected $description = 'Clôture les défis terminés et ouvre le défi de la semaine';

    public function handle(Challenges $challenges): int
    {
        $c = $challenges->current();
        $this->info($c ? "Défi en cours : {$c->title} (jusqu'au {$c->ends_on})" : 'Aucun défi (base non migrée ?)');

        return self::SUCCESS;
    }
}
