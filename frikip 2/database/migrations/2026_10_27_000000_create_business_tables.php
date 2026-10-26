<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offres payantes : abonnement « Structure » (académies, petits clubs) et commandes de montage vidéo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'structure_until')) {
            Schema::table('members', function (Blueprint $t) {
                $t->timestamp('structure_until')->nullable();
            });
        }
        if (! Schema::hasColumn('organizations', 'views')) {
            Schema::table('organizations', function (Blueprint $t) {
                $t->unsignedInteger('views')->default(0);
            });
        }
        Schema::create('video_orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 30)->unique();
            $t->string('user_key', 64)->index();
            $t->string('athlete_id', 64)->nullable();
            $t->unsignedTinyInteger('package');
            $t->string('package_label', 80);
            $t->unsignedInteger('amount');
            $t->string('currency', 3)->default('GNF');
            $t->text('notes')->nullable();
            $t->string('whatsapp', 30)->nullable();
            $t->string('rush_link', 500)->nullable();     // lien Google Drive / WeTransfer…
            $t->text('files')->nullable();                // JSON : rushs envoyés sur le site
            $t->string('payment_ref', 40)->nullable()->index();
            $t->string('status', 20)->default('attente_paiement'); // attente_paiement, payee, en_cours, livree, annulee
            $t->string('delivered_url', 500)->nullable();
            $t->text('admin_note')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_orders');
    }
};
