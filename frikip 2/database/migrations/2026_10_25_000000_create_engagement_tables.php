<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engagement réel sur les vidéos : j'aime, commentaires (avec réponses), j'aime sur les commentaires,
 * partages, langue de la vidéo et sous-titres. Utilisé dans les deux modes (démo et Supabase).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Vidéos publiées en mode démo (gardées pour tous les visiteurs, plus seulement dans la session)
        Schema::create('local_videos', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->string('athlete_id', 64)->index();
            $t->string('title', 150);
            $t->string('caption', 120)->nullable();
            $t->string('competition', 120)->nullable();
            $t->string('url', 500);
            $t->string('poster', 500)->nullable();
            $t->string('duration', 10)->nullable();
            $t->timestamps();
        });

        Schema::create('video_likes_local', function (Blueprint $t) {
            $t->string('video_id', 64);
            $t->string('user_key', 64);
            $t->timestamp('created_at')->nullable();
            $t->primary(['video_id', 'user_key']);
        });

        Schema::create('video_comments_local', function (Blueprint $t) {
            $t->id();
            $t->string('video_id', 64)->index();
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->string('user_key', 64);
            $t->string('author_name', 120);
            $t->string('author_role', 20)->default('athlete');
            $t->string('author_athlete_id', 64)->nullable();
            $t->text('body');
            $t->timestamps();
        });

        // Épinglé / masqué : pour les commentaires des deux modes (id local ou id Supabase)
        Schema::create('comment_meta', function (Blueprint $t) {
            $t->string('comment_id', 64)->primary();
            $t->string('video_id', 64)->index();
            $t->boolean('pinned')->default(false);
            $t->boolean('hidden')->default(false);
            $t->timestamps();
        });

        Schema::create('comment_likes', function (Blueprint $t) {
            $t->string('comment_id', 64);
            $t->string('user_key', 64);
            $t->timestamp('created_at')->nullable();
            $t->primary(['comment_id', 'user_key']);
        });

        Schema::create('video_shares', function (Blueprint $t) {
            $t->id();
            $t->string('video_id', 64)->index();
            $t->string('user_key', 64)->nullable();
            $t->string('channel', 20)->default('lien');
            $t->timestamp('created_at')->nullable();
        });

        // Langue parlée, présence de son et titres traduits
        Schema::create('video_meta', function (Blueprint $t) {
            $t->string('video_id', 64)->primary();
            $t->string('lang', 5)->default('none');   // fr | en | none (pas de parole)
            $t->boolean('has_audio')->default(true);
            $t->string('title_fr', 150)->nullable();
            $t->string('title_en', 150)->nullable();
            $t->string('caption_fr', 120)->nullable();
            $t->string('caption_en', 120)->nullable();
            $t->string('subtitle_status', 20)->nullable(); // en_cours | pret | erreur
            $t->timestamps();
        });

        Schema::create('video_subtitles', function (Blueprint $t) {
            $t->string('video_id', 64);
            $t->string('lang', 5);
            $t->longText('vtt');
            $t->string('source', 20)->default('manuel'); // manuel | auto
            $t->timestamps();
            $t->primary(['video_id', 'lang']);
        });
    }

    public function down(): void
    {
        foreach (['video_subtitles', 'video_meta', 'video_shares', 'comment_likes', 'comment_meta', 'video_comments_local', 'video_likes_local', 'local_videos'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
