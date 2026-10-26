<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Score choisi par le membre pour le haut du fil (équipe, compétition, match, aucun). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('members') && ! Schema::hasColumn('members', 'score_pref')) {
            Schema::table('members', function (Blueprint $t) {
                $t->string('score_pref', 300)->nullable();
            });
        }
    }

    public function down(): void
    {
    }
};
