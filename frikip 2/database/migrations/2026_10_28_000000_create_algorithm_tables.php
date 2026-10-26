<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Algorithme réglable par l'admin (classement, fil, modération), avertissements, journal des actions admin
 * et pays des membres (toute l'Afrique de l'Ouest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('algo_settings', function (Blueprint $t) {
            $t->string('key', 64)->primary();
            $t->text('value');
            $t->timestamp('updated_at')->nullable();
        });

        // Avertissements donnés à un membre (faute confirmée, contenu bloqué automatiquement)
        Schema::create('member_strikes', function (Blueprint $t) {
            $t->id();
            $t->string('user_key', 64)->index();
            $t->string('rule', 40);                   // nudite, violence, haine, arnaque, spam, faux_profil, mineurs, autre
            $t->string('source', 20);                 // admin, image, signalements, mots
            $t->string('target_type', 20)->nullable();
            $t->string('target_id', 64)->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        // Contenus bloqués ou mis de côté automatiquement par l'algorithme (file de modération)
        Schema::create('moderation_events', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20);                   // texte_refuse, image, signalements, validation
            $t->string('rule', 40)->nullable();
            $t->string('user_key', 64)->nullable()->index();
            $t->string('target_type', 20)->nullable(); // video, comment, message, profile, offer, club
            $t->string('target_id', 64)->nullable();
            $t->string('excerpt', 300)->nullable();
            $t->float('score')->nullable();
            $t->string('status', 20)->default('a_traiter')->index(); // a_traiter, approuve, confirme
            $t->timestamps();
        });

        // Journal de l'espace admin : chaque action, qui ne se modifie pas
        Schema::create('admin_log', function (Blueprint $t) {
            $t->id();
            $t->string('action', 80);
            $t->string('target', 120)->nullable();
            $t->text('details')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::table('moderation_actions', function (Blueprint $t) {
            $t->timestamp('until')->nullable();       // fin d'une suspension
            $t->string('rule', 40)->nullable();
        });
        Schema::table('members', function (Blueprint $t) {
            $t->string('country', 60)->nullable()->index();
            $t->float('score')->default(0);
        });
        if (Schema::hasTable('accounts') && ! Schema::hasColumn('accounts', 'country')) {
            Schema::table('accounts', function (Blueprint $t) {
                $t->string('country', 60)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['algo_settings', 'member_strikes', 'moderation_events', 'admin_log'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
