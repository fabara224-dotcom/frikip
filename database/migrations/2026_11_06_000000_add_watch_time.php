<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Durée de visionnage (secondes regardées par vue) et vidéos enregistrées en favori. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('video_views') && ! Schema::hasColumn('video_views', 'seconds')) {
            Schema::table('video_views', function (Blueprint $t) {
                $t->unsignedInteger('seconds')->default(0);
            });
        }
        if (! Schema::hasTable('video_saves')) {
            Schema::create('video_saves', function (Blueprint $t) {
                $t->string('user_key', 64);
                $t->string('video_id', 64)->index();
                $t->timestamp('created_at')->nullable();
                $t->primary(['user_key', 'video_id']);
            });
        }
    }

    public function down(): void
    {
    }
};
