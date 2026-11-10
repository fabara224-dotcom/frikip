<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// ---- Tâches automatiques de Terrain (sur le serveur : une ligne cron « * * * * * php artisan schedule:run »)
Schedule::command('terrain:alertes')->dailyAt('08:00');     // alertes des recruteurs
Schedule::command('terrain:defis')->mondays()->at('00:05');  // défi de la semaine
Schedule::command('terrain:paiements')->everyTenMinutes();  // paiements CinetPay restés en attente
Schedule::command('frikip:directs')->everyFifteenMinutes();   // matchs en direct sur YouTube (respecte le quota gratuit)
Schedule::command('frikip:decouvrir')->dailyAt('03:30');   // vidéos « Découvrir » jusqu'au maximum réglé (Admin > Découvrir)
Schedule::command('frikip:sauvegarde')->dailyAt('02:30');      // sauvegarde de la base (storage/sauvegardes, 7 gardées)
Schedule::command('frikip:sauvegarde --videos --garder=2')->weeklyOn(0, '03:00');   // sauvegarde complète avec les vidéos, le dimanche
