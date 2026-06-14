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
            $table->string('gmail_email');
            $table->string('google_account_id');
            $table->text('encrypted_refresh_token');
            $table->text('encrypted_access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('last_history_id')->nullable();
            $table->timestamp('watch_expires_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'google_account_id']);
            $table->index('gmail_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmail_accounts');
    }
};
