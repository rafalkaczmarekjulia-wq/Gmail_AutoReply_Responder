<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gmail_thread_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gmail_message_id');
            $table->string('gmail_thread_id_str');
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['gmail_account_id', 'gmail_message_id']);
            $table->index('gmail_thread_id_str');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_messages');
    }
};
