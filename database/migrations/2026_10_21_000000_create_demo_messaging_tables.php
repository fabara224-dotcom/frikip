<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie et appels du MODE DÉMO (sans Supabase).
 * Chaque visiteur a sa « salle » : deux appareils dans la même salle (lien d'invitation) peuvent
 * s'écrire, s'envoyer des vocaux et s'appeler pour de vrai. Avec Supabase, ces tables ne servent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_messages', function (Blueprint $table) {
            $table->id();
            $table->string('room', 40)->index();
            $table->string('sender', 60);
            $table->string('recipient', 60);
            $table->string('kind', 10)->default('text');      // text, voice, image, call
            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->unsignedInteger('duration')->nullable();  // secondes (vocal, appel)
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['room', 'sender', 'recipient']);
        });

        Schema::create('demo_calls', function (Blueprint $table) {
            $table->string('id', 40)->primary();
            $table->string('room', 40)->index();
            $table->string('caller', 60);
            $table->string('callee', 60);
            $table->string('kind', 10)->default('audio');      // audio, video
            $table->string('status', 15)->default('ringing'); // ringing, accepted, declined, missed, ended, cancelled
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_call_signals', function (Blueprint $table) {
            $table->id();
            $table->string('call_id', 40)->index();
            $table->string('sender', 60);
            $table->string('type', 20);                        // offer, answer, ice
            $table->longText('payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_call_signals');
        Schema::dropIfExists('demo_calls');
        Schema::dropIfExists('demo_messages');
    }
};
