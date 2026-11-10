<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cahier des modifications :
 * - user_blocks   : bloquer un utilisateur (il ne peut plus écrire, ses contenus sont cachés pour moi)
 * - shortlists    : listes de joueurs présélectionnés des recruteurs
 * - verification_badges : statuts de vérification distincts, avec date et source
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_blocks')) {
            Schema::create('user_blocks', function (Blueprint $t) {
                $t->id();
                $t->string('blocker', 64)->index();
                $t->string('blocked', 64)->index();
                $t->timestamp('created_at')->nullable();
                $t->unique(['blocker', 'blocked']);
            });
        }
        if (! Schema::hasTable('shortlists')) {
            Schema::create('shortlists', function (Blueprint $t) {
                $t->id();
                $t->string('owner_key', 64)->index();
                $t->string('name', 80);
                $t->timestamps();
            });
            Schema::create('shortlist_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('shortlist_id')->index();
                $t->string('athlete_id', 64);
                $t->string('note', 300)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['shortlist_id', 'athlete_id']);
            });
        }
        if (! Schema::hasTable('verification_badges')) {
            Schema::create('verification_badges', function (Blueprint $t) {
                $t->id();
                $t->string('subject', 64)->index();          // compte ou profil de joueur
                $t->string('type', 30);                      // identite | recruteur | agent_fifa | enregistrement
                $t->string('source', 200)->nullable();       // ex. « Répertoire FIFA des agents », « Ligue de Conakry »
                $t->string('admin_name', 120)->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->string('revoked_reason', 300)->nullable();
                $t->text('dispute')->nullable();             // contestation du membre
                $t->timestamps();
                $t->index(['subject', 'type']);
            });
        }
    }

    public function down(): void
    {
        foreach (['user_blocks', 'shortlist_items', 'shortlists', 'verification_badges'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
