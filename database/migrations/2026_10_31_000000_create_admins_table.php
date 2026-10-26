<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comptes de l'espace admin (identifiant + mot de passe, rôle) et auteur de chaque action du journal. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120);
                $t->string('email', 190)->unique();
                $t->string('password');
                $t->string('role', 20)->default('admin');   // admin = tout ; moderateur = membres et contenus
                $t->boolean('active')->default(true);
                $t->timestamp('last_login_at')->nullable();
                $t->string('last_login_ip', 45)->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('admin_log') && ! Schema::hasColumn('admin_log', 'admin_id')) {
            Schema::table('admin_log', function (Blueprint $t) {
                $t->unsignedBigInteger('admin_id')->nullable();
                $t->string('admin_name', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
