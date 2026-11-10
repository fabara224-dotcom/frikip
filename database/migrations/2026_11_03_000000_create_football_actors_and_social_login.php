<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tous les acteurs du foot (joueur, supporter, club, recruteur, agent, entraîneur, arbitre, médecin…)
 * et connexion avec Google, Facebook et TikTok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $t) {
            foreach (['profession' => 60, 'organization' => 150, 'licence_number' => 60, 'favorite_club' => 120, 'avatar_url' => 500] as $col => $len) {
                if (! Schema::hasColumn('accounts', $col)) {
                    $t->string($col, $len)->nullable();
                }
            }
        });
        Schema::table('local_athletes', function (Blueprint $t) {
            if (! Schema::hasColumn('local_athletes', 'kind')) {
                $t->string('kind', 12)->default('joueur');      // joueur | pro (entraîneur, arbitre, médecin…)
            }
            if (! Schema::hasColumn('local_athletes', 'profession')) {
                $t->string('profession', 60)->nullable();
            }
        });
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $t) {
                $t->id();
                $t->string('provider', 20);                       // google | facebook | tiktok
                $t->string('provider_id', 191);
                $t->string('account_id', 64)->index();
                $t->string('email', 200)->nullable();
                $t->timestamps();
                $t->unique(['provider', 'provider_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
