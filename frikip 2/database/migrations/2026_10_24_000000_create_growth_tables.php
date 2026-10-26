<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fonctions du site (dans la base du site, quel que soit le mode : démo ou Supabase) :
 * membres, paiements, modération, vérifications, notifications, statistiques, clubs, détections,
 * alertes, parrainage, défis, témoignages, partenaires, connexion par téléphone.
 * « user_key » = identifiant du compte connecté (DataSource::user()['id']) ; « athlete_id » = son profil sportif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $t) {
            $t->string('user_key', 64)->primary();
            $t->string('role', 12);
            $t->string('athlete_id', 64)->nullable()->index();
            $t->string('name')->nullable();
            $t->string('sport', 30)->nullable();
            $t->string('city', 80)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('referral_code', 12)->unique();
            $t->string('referred_by', 64)->nullable()->index();
            $t->boolean('notify_email')->default(true);
            $t->boolean('notify_sms')->default(false);
            $t->boolean('notify_whatsapp')->default(false);
            $t->boolean('data_saver')->default(false);
            $t->timestamp('premium_until')->nullable();
            $t->timestamp('boost_until')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->string('user_key', 64)->index();
            $t->string('product', 20);          // premium, boost
            $t->unsignedInteger('months')->default(1);
            $t->unsignedInteger('amount');
            $t->string('currency', 3)->default('GNF');
            $t->string('method', 20)->nullable();
            $t->string('phone', 30)->nullable();
            $t->string('provider', 20)->default('cinetpay');
            $t->string('status', 20)->default('pending'); // pending, accepted, refused, cancelled
            $t->string('provider_status', 40)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->string('reporter_key', 64)->nullable();
            $t->string('target_type', 20);       // app, profile, video, message
            $t->string('target_id', 64)->nullable();
            $t->text('reason');
            $t->string('status', 20)->default('nouveau')->index(); // nouveau, traite, classe
            $t->string('action', 30)->nullable();
            $t->text('admin_note')->nullable();
            $t->timestamps();
        });

        Schema::create('moderation_actions', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20);              // hide_video, block_account
            $t->string('target_id', 64);
            $t->string('reason')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index(['type', 'target_id', 'active']);
        });

        Schema::create('verification_requests', function (Blueprint $t) {
            $t->id();
            $t->string('user_key', 64)->index();
            $t->string('role', 12);
            $t->string('full_name');
            $t->string('document_type', 40);
            $t->string('document_path')->nullable();
            $t->string('licence_number', 80)->nullable();
            $t->string('organization')->nullable();
            $t->string('guardian_name')->nullable();
            $t->string('guardian_phone', 30)->nullable();
            $t->string('guardian_document_path')->nullable();
            $t->boolean('guardian_consent')->default(false);
            $t->string('status', 20)->default('en_attente')->index(); // en_attente, validee, refusee
            $t->text('admin_note')->nullable();
            $t->timestamps();
        });

        Schema::create('notifications_feed', function (Blueprint $t) {
            $t->id();
            $t->string('user_key', 64)->index();
            $t->string('type', 30);
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('url')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('profile_views', function (Blueprint $t) {
            $t->id();
            $t->string('athlete_id', 64)->index();
            $t->string('viewer_key', 64)->nullable();
            $t->string('viewer_role', 12)->nullable();
            $t->string('viewer_name')->nullable();
            $t->string('country', 60)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });

        Schema::create('video_views', function (Blueprint $t) {
            $t->id();
            $t->string('video_id', 64)->index();
            $t->string('athlete_id', 64)->index();
            $t->string('viewer_key', 64)->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('follows_local', function (Blueprint $t) {
            $t->string('follower_key', 64);
            $t->string('athlete_id', 64);
            $t->timestamp('created_at')->nullable();
            $t->primary(['follower_key', 'athlete_id']);
            $t->index('athlete_id');
        });

        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 80)->unique();
            $t->string('owner_key', 64)->nullable()->index();
            $t->string('name');
            $t->string('type', 20);              // club, academie, agent, universite, federation
            $t->string('sport', 30)->default('Football');
            $t->string('city', 80)->nullable();
            $t->string('country', 60)->default('Guinée');
            $t->text('description')->nullable();
            $t->string('palmares')->nullable();
            $t->string('licence', 80)->nullable();
            $t->string('color', 9)->default('#1F7A4A');
            $t->boolean('verified')->default(false);
            $t->boolean('demo')->default(false);
            $t->timestamps();
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $t->string('owner_key', 64)->nullable()->index();
            $t->string('title');
            $t->string('sport', 30);
            $t->date('date');
            $t->string('time', 10)->nullable();
            $t->string('place');
            $t->string('city', 80);
            $t->unsignedInteger('capacity')->default(50);
            $t->unsignedTinyInteger('age_min')->nullable();
            $t->unsignedTinyInteger('age_max')->nullable();
            $t->text('description')->nullable();
            $t->boolean('demo')->default(false);
            $t->timestamps();
        });

        Schema::create('event_registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->string('user_key', 64);
            $t->string('athlete_id', 64)->nullable();
            $t->string('name');
            $t->string('code', 12)->unique();
            $t->timestamp('checked_in_at')->nullable();
            $t->timestamps();
            $t->unique(['event_id', 'user_key']);
        });

        Schema::create('saved_searches', function (Blueprint $t) {
            $t->id();
            $t->string('user_key', 64)->index();
            $t->string('label');
            $t->json('filters');
            $t->timestamp('last_sent_at')->nullable();
            $t->json('seen_ids')->nullable();
            $t->timestamps();
        });

        Schema::create('challenges', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('theme', 40);
            $t->string('sport', 30)->nullable();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->string('status', 12)->default('ouvert'); // ouvert, termine
            $t->unsignedBigInteger('winner_entry_id')->nullable();
            $t->timestamps();
        });

        Schema::create('challenge_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $t->string('athlete_id', 64);
            $t->string('video_id', 64);
            $t->string('user_key', 64)->nullable();
            $t->unsignedInteger('votes')->default(0);
            $t->timestamps();
            $t->unique(['challenge_id', 'athlete_id']);
        });

        Schema::create('challenge_votes', function (Blueprint $t) {
            $t->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('entry_id');
            $t->string('voter_key', 64);
            $t->timestamp('created_at')->nullable();
            $t->primary(['challenge_id', 'voter_key']);
        });

        Schema::create('success_stories', function (Blueprint $t) {
            $t->id();
            $t->string('user_key', 64)->nullable();
            $t->string('name');
            $t->string('sport', 30);
            $t->string('from_city', 80)->nullable();
            $t->string('recruited_by');
            $t->string('year', 4);
            $t->text('story');
            $t->boolean('consent')->default(false);
            $t->string('status', 12)->default('en_attente'); // en_attente, publie, refuse
            $t->boolean('demo')->default(false);
            $t->timestamps();
        });

        Schema::create('partnership_requests', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30);
            $t->string('organization');
            $t->string('contact_name');
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->text('message')->nullable();
            $t->string('status', 20)->default('nouvelle');
            $t->timestamps();
        });

        Schema::create('phone_codes', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 30)->index();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['phone_codes', 'partnership_requests', 'success_stories', 'challenge_votes', 'challenge_entries', 'challenges', 'saved_searches',
            'event_registrations', 'events', 'organizations', 'follows_local', 'video_views', 'profile_views', 'notifications_feed',
            'verification_requests', 'moderation_actions', 'reports', 'payments', 'members'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
