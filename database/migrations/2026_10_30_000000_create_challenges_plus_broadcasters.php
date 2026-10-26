<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Défis spéciaux (lot, sponsor, pays) et diffuseurs officiels des grands championnats. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('challenges')) {
            Schema::table('challenges', function (Blueprint $t) {
                foreach ([
                    'description' => fn () => $t->text('description')->nullable(),
                    'kind' => fn () => $t->string('kind', 12)->default('semaine'),      // semaine | special
                    'country' => fn () => $t->string('country', 60)->nullable(),       // vide = toute la région
                    'prize' => fn () => $t->string('prize', 160)->nullable(),          // ex. « Un maillot Fabara + 7 jours de Boost »
                    'sponsor' => fn () => $t->string('sponsor', 120)->nullable(),
                    'sponsor_url' => fn () => $t->string('sponsor_url', 500)->nullable(),
                ] as $col => $add) {
                    if (! Schema::hasColumn('challenges', $col)) {
                        $add();
                    }
                }
            });
        }
        if (Schema::hasTable('challenge_entries') && ! Schema::hasColumn('challenge_entries', 'excluded')) {
            Schema::table('challenge_entries', function (Blueprint $t) {
                $t->boolean('excluded')->default(false);
            });
        }

        if (! Schema::hasTable('broadcasters')) {
            Schema::create('broadcasters', function (Blueprint $t) {
                $t->id();
                $t->string('name', 80);
                $t->json('competitions')->nullable();   // identifiants API-Football (39 = Premier League…), vide = toutes
                $t->json('countries')->nullable();      // vide = tous les pays
                $t->string('url', 500);                 // page ou application officielle (lien partenaire possible)
                $t->string('note', 160)->nullable();    // ex. « Affiche du mercredi soir »
                $t->string('embed_url', 500)->nullable(); // flux fourni par le diffuseur dans le cadre d'un contrat
                $t->boolean('contract')->default(false);  // contrat de diffusion signé : le flux se regarde dans Frikip
                $t->boolean('active')->default(false);
                $t->unsignedSmallInteger('priority')->default(5);
                $t->unsignedInteger('clicks')->default(0);
                $t->timestamps();
            });
            // Points de départ, désactivés : à vérifier puis activer dans Admin > Diffuseurs (les droits changent chaque saison).
            $fr = ['Guinée', 'Sénégal', "Côte d'Ivoire", 'Mali', 'Burkina Faso', 'Bénin', 'Togo', 'Niger', 'Mauritanie', 'Cameroun', 'Guinée-Bissau', 'RDC'];
            $en = ['Ghana', 'Nigeria', 'Libéria', 'Sierra Leone', 'Gambie'];
            $now = now();
            DB::table('broadcasters')->insert([
                ['name' => 'Canal+ Afrique', 'competitions' => json_encode([61, 39, 2]), 'countries' => json_encode($fr), 'url' => 'https://www.canalplus-afrique.com',
                    'note' => 'Ligue 1, Premier League, grande affiche de Ligue des champions', 'priority' => 1, 'active' => false, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'New World TV', 'competitions' => json_encode([2, 3]), 'countries' => json_encode($fr), 'url' => 'https://www.newworldtv.com',
                    'note' => 'Ligue des champions et Ligue Europa', 'priority' => 2, 'active' => false, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'SuperSport (DStv, Showmax)', 'competitions' => json_encode([39, 140, 135, 2]), 'countries' => json_encode($en), 'url' => 'https://supersport.com',
                    'note' => 'Afrique anglophone', 'priority' => 1, 'active' => false, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'StarTimes', 'competitions' => json_encode([78, 135]), 'countries' => null, 'url' => 'https://www.startimestv.com',
                    'note' => 'Application StarTimes ON', 'priority' => 3, 'active' => false, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (Schema::hasTable('live_streams') && ! Schema::hasColumn('live_streams', 'fixture_id')) {
            Schema::table('live_streams', function (Blueprint $t) {
                $t->string('fixture_id', 40)->nullable()->index();   // match API-Football relié (ex. api-1035037)
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasters');
    }
};
