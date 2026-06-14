<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_account_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_thread_id');
            $table->string('subject')->nullable();
            $table->text('snippet')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['gmail_account_id', 'gmail_thread_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_threads');
    }
};
