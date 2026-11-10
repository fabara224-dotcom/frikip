<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Photo de couverture du profil (nouveau design). La photo de profil utilise accounts.avatar_url. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounts') && ! Schema::hasColumn('accounts', 'cover_url')) {
            Schema::table('accounts', function (Blueprint $t) {
                $t->string('cover_url', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'cover_url')) {
            Schema::table('accounts', function (Blueprint $t) {
                $t->dropColumn('cover_url');
            });
        }
    }
};
