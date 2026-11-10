<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Actu : publications texte et photo des membres (côté « Facebook » de Frikip), j'aime et commentaires. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $t) {
                $t->id();
                $t->string('user_key', 64)->index();
                $t->string('author_name', 120);
                $t->string('author_role', 20)->nullable();
                $t->string('athlete_id', 64)->nullable();
                $t->text('body')->nullable();
                $t->string('photo', 255)->nullable();
                $t->string('country', 60)->nullable()->index();
                $t->unsignedInteger('likes')->default(0);
                $t->unsignedInteger('comments')->default(0);
                $t->unsignedInteger('reports')->default(0);
                $t->boolean('hidden')->default(false)->index();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('post_likes')) {
            Schema::create('post_likes', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('post_id')->index();
                $t->string('user_key', 64);
                $t->timestamps();
                $t->unique(['post_id', 'user_key']);
            });
        }
        if (! Schema::hasTable('post_comments')) {
            Schema::create('post_comments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('post_id')->index();
                $t->string('user_key', 64);
                $t->string('author_name', 120);
                $t->text('body');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('post_reports')) {
            Schema::create('post_reports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('post_id')->index();
                $t->string('user_key', 64);
                $t->timestamps();
                $t->unique(['post_id', 'user_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reports');
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('posts');
    }
};
