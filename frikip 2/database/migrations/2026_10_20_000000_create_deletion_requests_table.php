<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->string('contact', 200);
            $table->string('profile_name', 200)->nullable();
            $table->string('scope', 20)->default('account');
            $table->text('note')->nullable();
            $table->string('status', 20)->default('nouvelle')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
    }
};
