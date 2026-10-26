<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Coaching (plan gratuit + suivi payant), matchs en direct, publicités et option « Sans pub ». */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Coaching
        if (! Schema::hasTable('coaches')) {
            Schema::create('coaches', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120);
                $t->string('sport', 40)->nullable();
                $t->string('specialty', 160)->nullable();      // ex. « Finition, attaquants »
                $t->text('bio')->nullable();
                $t->string('country', 60)->nullable();
                $t->string('user_key', 80)->nullable()->index(); // compte Frikip du coach (il voit ses sportifs dans « Espace coach »)
                $t->string('contact', 120)->nullable();         // e-mail ou téléphone du compte à rattacher
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('coaching_stats')) {
            Schema::create('coaching_stats', function (Blueprint $t) {
                $t->id();
                $t->string('user_key', 80)->index();
                $t->string('sport', 40);
                $t->string('position', 60)->nullable();
                $t->json('stats');                              // chiffres de la saison passée
                $t->json('plan')->nullable();                   // plan gratuit calculé
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('coaching_requests')) {
            Schema::create('coaching_requests', function (Blueprint $t) {
                $t->id();
                $t->string('reference', 20)->unique();
                $t->string('user_key', 80)->index();
                $t->string('athlete_id', 80)->nullable();
                $t->unsignedTinyInteger('package')->default(0);
                $t->string('package_label', 80);
                $t->unsignedInteger('days')->default(0);
                $t->unsignedBigInteger('amount')->default(0);
                $t->string('currency', 6)->default('GNF');
                $t->string('goal', 160)->nullable();            // objectif choisi
                $t->text('details')->nullable();                // la demande du sportif
                $t->string('whatsapp', 30)->nullable();
                $t->unsignedBigInteger('coach_id')->nullable()->index();
                $t->string('status', 20)->default('attente_paiement'); // attente_paiement, payee, en_cours, terminee, annulee
                $t->string('payment_ref', 24)->nullable()->index();
                $t->timestamp('paid_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('coaching_messages')) {
            Schema::create('coaching_messages', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('request_id')->index();
                $t->string('author', 10);                       // sportif | coach
                $t->string('author_name', 120)->nullable();
                $t->string('kind', 12)->default('message');     // message | programme | bilan | demande
                $t->text('body');
                $t->boolean('read')->default(false);
                $t->timestamps();
            });
        }

        // ---- Matchs en direct
        if (! Schema::hasTable('live_streams')) {
            Schema::create('live_streams', function (Blueprint $t) {
                $t->id();
                $t->string('title', 160);
                $t->string('sport', 40)->default('Football');
                $t->string('competition', 120)->nullable();
                $t->string('home', 80);
                $t->string('away', 80);
                $t->unsignedSmallInteger('home_score')->default(0);
                $t->unsignedSmallInteger('away_score')->default(0);
                $t->string('minute', 12)->nullable();
                $t->string('country', 60)->nullable();
                $t->string('city', 80)->nullable();
                $t->timestamp('starts_at')->nullable();
                $t->string('status', 12)->default('prevu');    // prevu | direct | termine
                $t->string('stream_url', 500);
                $t->string('replay_url', 500)->nullable();
                $t->string('source', 12)->default('club');      // club | admin | youtube (trouvé par l'API)
                $t->string('external_id', 40)->nullable()->index();
                $t->string('channel', 120)->nullable();
                $t->string('thumbnail', 500)->nullable();
                $t->string('mux_id', 80)->nullable();            // direct diffusé par Frikip (Mux)
                $t->string('stream_key', 120)->nullable();       // clé de diffusion (secrète)
                $t->string('playback_id', 80)->nullable();
                $t->boolean('started_live')->default(false);
                $t->string('owner_key', 80)->nullable()->index(); // club qui a publié (null = admin)
                $t->string('owner_name', 120)->nullable();
                $t->boolean('approved')->default(false);
                $t->boolean('rights_ok')->default(false);       // le publieur certifie avoir les droits
                $t->unsignedInteger('views')->default(0);
                $t->unsignedInteger('peak')->default(0);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('live_viewers')) {
            Schema::create('live_viewers', function (Blueprint $t) {
                $t->unsignedBigInteger('stream_id');
                $t->string('viewer', 80);
                $t->timestamp('seen_at');
                $t->primary(['stream_id', 'viewer']);
            });
        }

        // ---- Publicités
        if (! Schema::hasTable('ads')) {
            Schema::create('ads', function (Blueprint $t) {
                $t->id();
                $t->string('title', 120);
                $t->string('advertiser', 120);
                $t->string('video_path', 255)->nullable();      // fichier envoyé (storage/app/public/pubs)
                $t->string('video_url', 500)->nullable();       // ou adresse d'une vidéo .mp4
                $t->string('poster_path', 255)->nullable();
                $t->string('link', 500)->nullable();            // page ouverte au clic
                $t->string('cta', 40)->default('En savoir plus');
                $t->json('countries')->nullable();              // vide = tous les pays
                $t->string('sport', 40)->nullable();            // vide = tous les sports
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->unsignedInteger('max_impressions')->default(0); // 0 = sans limite
                $t->unsignedSmallInteger('weight')->default(1);     // plus il est élevé, plus la pub revient souvent
                $t->boolean('active')->default(true);
                $t->unsignedInteger('impressions')->default(0);
                $t->unsignedInteger('clicks')->default(0);
                $t->unsignedBigInteger('price')->default(0);    // montant facturé à l'annonceur (suivi)
                $t->string('currency', 6)->default('GNF');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('ad_daily')) {
            Schema::create('ad_daily', function (Blueprint $t) {
                $t->unsignedBigInteger('ad_id');
                $t->date('day');
                $t->unsignedInteger('impressions')->default(0);
                $t->unsignedInteger('clicks')->default(0);
                $t->primary(['ad_id', 'day']);
            });
        }

        // ---- Option « Sans pub »
        if (Schema::hasTable('members') && ! Schema::hasColumn('members', 'adfree_until')) {
            Schema::table('members', function (Blueprint $t) {
                $t->timestamp('adfree_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['coaches', 'coaching_stats', 'coaching_requests', 'coaching_messages', 'live_streams', 'live_viewers', 'ads', 'ad_daily'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
