<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comptes « Fan » (rien à ajouter : rôle « fan ») et profils de sportifs tenus par un proche. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('local_athletes') && ! Schema::hasColumn('local_athletes', 'managed_by')) {
            Schema::table('local_athletes', function (Blueprint $t) {
                $t->string('managed_by', 30)->nullable();      // lien : Frère, Mère, Ami…
                $t->string('manager_name', 120)->nullable();   // nom du proche qui tient le profil
            });
        }
    }

    public function down(): void
    {
    }
};
