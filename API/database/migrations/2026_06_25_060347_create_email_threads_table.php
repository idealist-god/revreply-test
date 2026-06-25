<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_account_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_thread_id');
            $table->string('subject')->nullable();
            $table->text('snippet')->nullable();
            $table->string('from_address')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->enum('classification', ['pending', 'interested', 'not_interested', 'meeting_request', 'unclear'])->default('pending');
            $table->enum('status', ['new', 'draft_created', 'sent', 'dismissed'])->default('new');
            $table->timestamps();

            $table->unique(['gmail_account_id', 'gmail_thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_threads');
    }
};
