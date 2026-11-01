<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stories : une photo ou une courte vidéo visible 24 h, en haut de l'Actu. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fk_stories')) {
            Schema::create('fk_stories', function (Blueprint $t) {
                $t->id();
                $t->string('user_key', 64)->index();
                $t->string('author_name', 120);
                $t->string('athlete_id', 64)->nullable();
                $t->string('media', 255);
                $t->string('media_type', 10);           // image | video
                $t->string('caption', 200)->nullable();
                $t->string('country', 60)->nullable();
                $t->unsignedInteger('views')->default(0);
                $t->unsignedInteger('reports')->default(0);
                $t->boolean('hidden')->default(false);
                $t->timestamp('expires_at')->index();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('fk_story_views')) {
            Schema::create('fk_story_views', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('story_id')->index();
                $t->string('user_key', 64);
                $t->timestamps();
                $t->unique(['story_id', 'user_key']);
            });
        }
        if (! Schema::hasTable('fk_story_reports')) {
            Schema::create('fk_story_reports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('story_id')->index();
                $t->string('user_key', 64);
                $t->timestamps();
                $t->unique(['story_id', 'user_key']);
            });
        }
        // Publications vérifiées par l'admin : ne reviennent plus dans « À vérifier » tant qu'aucun nouveau signalement n'arrive
        if (Schema::hasTable('posts') && ! Schema::hasColumn('posts', 'reviewed_at')) {
            Schema::table('posts', function (Blueprint $t) {
                $t->timestamp('reviewed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fk_story_reports');
        Schema::dropIfExists('fk_story_views');
        Schema::dropIfExists('fk_stories');
    }
};
