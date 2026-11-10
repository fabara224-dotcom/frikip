<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - discover_videos : vidéos de foot YouTube (intégrables, créditées) de l'onglet « Découvrir »
 * - room_messages   : salon du match ou du live (commentaires et réactions en direct)
 * - local_videos.edit : réglages de l'éditeur vidéo (filtre, coupe, vitesse, texte)
 * - live_streams    : directs vidéo des membres (Cloudflare Stream)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discover_videos')) {
            Schema::create('discover_videos', function (Blueprint $t) {
                $t->id();
                $t->string('youtube_id', 20)->unique();
                $t->string('title', 200);
                $t->string('channel', 120)->nullable();
                $t->string('channel_id', 40)->nullable();
                $t->string('thumbnail', 300)->nullable();
                $t->unsignedInteger('duration')->default(0);   // secondes
                $t->string('topic', 120)->nullable();
                $t->timestamp('published_at')->nullable();
                $t->unsignedInteger('plays')->default(0);
                $t->boolean('hidden')->default(false);
                $t->timestamps();
                $t->index(['hidden', 'topic']);
            });
        }
        if (! Schema::hasTable('room_messages')) {
            Schema::create('room_messages', function (Blueprint $t) {
                $t->id();
                $t->string('room', 80)->index();              // m-<match> ou l-<live>
                $t->string('user_key', 64)->index();
                $t->string('name', 120);
                $t->string('kind', 10)->default('text');      // text | react
                $t->string('body', 300);
                $t->boolean('hidden')->default(false);
                $t->unsignedSmallInteger('reports')->default(0);
                $t->timestamp('created_at')->nullable();
            });
        }
        if (Schema::hasTable('local_videos') && ! Schema::hasColumn('local_videos', 'edit')) {
            Schema::table('local_videos', function (Blueprint $t) {
                $t->text('edit')->nullable();                 // JSON : filtre, début, fin, vitesse, texte
            });
        }
        if (Schema::hasTable('live_streams') && ! Schema::hasColumn('live_streams', 'cf_uid')) {
            Schema::table('live_streams', function (Blueprint $t) {
                $t->string('cf_uid', 64)->nullable();         // Cloudflare Stream : identifiant du direct
                $t->string('whip_url', 500)->nullable();      // envoi depuis le téléphone (WebRTC)
                $t->string('whep_url', 500)->nullable();      // lecture (WebRTC)
                $t->string('kind', 12)->nullable();           // null = match ; 'membre' = live d'un membre
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discover_videos');
        Schema::dropIfExists('room_messages');
    }
};
