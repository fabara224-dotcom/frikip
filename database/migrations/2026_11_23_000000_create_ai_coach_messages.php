<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Coach IA : la conversation de chaque membre avec son coach personnel (Claude). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_coach_messages')) {
            Schema::create('ai_coach_messages', function (Blueprint $t) {
                $t->id();
                $t->string('user_key', 80)->index();
                $t->string('role', 12);              // user | assistant
                $t->text('content');
                $t->unsignedInteger('tokens')->default(0);
                $t->timestamps();
                $t->index(['user_key', 'role', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_coach_messages');
    }
};
