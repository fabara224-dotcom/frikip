<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vrais comptes enregistrés dans la base du site (sans Supabase) :
 * comptes, profils sportifs, offres et candidatures, mots de passe oubliés et abonnements aux notifications du téléphone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $t) {
            $t->string('id', 64)->primary();              // u-n-xxxx (sportif) ou r-xxxx (recruteur)
            $t->string('role', 20);                        // athlete | recruiter
            $t->string('name', 120);
            $t->string('email', 200)->nullable()->unique();
            $t->string('phone', 30)->nullable()->unique();
            $t->string('password')->nullable();
            $t->string('athlete_id', 64)->nullable()->index();
            $t->string('org_type', 40)->nullable();        // recruteur : Club, Académie, Agent, Université…
            $t->timestamp('last_login_at')->nullable();
            $t->timestamps();
        });

        Schema::create('local_athletes', function (Blueprint $t) {
            $t->string('id', 64)->primary();              // n-xxxx
            $t->string('account_id', 64)->index();
            $t->string('name', 120);
            $t->string('sport', 40);
            $t->unsignedTinyInteger('age');
            $t->string('country', 60);
            $t->string('city', 120)->nullable();
            $t->string('height', 20)->nullable();
            $t->string('weight', 20)->nullable();
            $t->string('level', 40)->nullable();
            $t->string('club', 120)->nullable();
            $t->string('availability', 60)->nullable();
            $t->string('specialty', 80)->nullable();
            $t->text('fields')->nullable();               // JSON : fiche du sport
            $t->string('guardian_phone', 30)->nullable();
            $t->timestamps();
        });

        Schema::create('local_offers', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->string('owner_key', 64)->index();
            $t->string('organization', 120);
            $t->string('org_type', 40)->default('Club');
            $t->string('sport', 40);
            $t->string('title', 150);
            $t->string('place', 120)->nullable();
            $t->string('deadline', 40)->nullable();
            $t->string('criteria', 300)->nullable();
            $t->timestamps();
        });

        Schema::create('local_applications', function (Blueprint $t) {
            $t->id();
            $t->string('offer_id', 64)->index();
            $t->string('athlete_id', 64);
            $t->string('video_id', 64)->nullable();
            $t->text('message')->nullable();
            $t->timestamps();
            $t->unique(['offer_id', 'athlete_id']);
        });

        Schema::create('password_resets_local', function (Blueprint $t) {
            $t->id();
            $t->string('account_id', 64)->index();
            $t->string('token_hash', 100);
            $t->timestamp('expires_at');
            $t->timestamps();
        });

        // Notifications sur le téléphone / l'ordinateur, même site fermé (Web Push)
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('user_key', 64)->index();
            $t->string('endpoint', 600)->unique();
            $t->string('p256dh', 200);
            $t->string('auth', 100);
            $t->string('user_agent', 200)->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['push_subscriptions', 'password_resets_local', 'local_applications', 'local_offers', 'local_athletes', 'accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
