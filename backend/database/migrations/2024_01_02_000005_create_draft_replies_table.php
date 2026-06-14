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
            $table->foreignId('gmail_message_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_draft_id')->nullable();
            $table->longText('body');
            $table->string('status')->default('pending_approval');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_replies');
    }
};
