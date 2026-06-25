<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_message_id')->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->string('gmail_draft_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'sent', 'rejected'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_replies');
    }
};
