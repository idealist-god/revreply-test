<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_address')->unique();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('watch_history_id')->nullable();
            $table->timestamp('watch_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_accounts');
    }
};
