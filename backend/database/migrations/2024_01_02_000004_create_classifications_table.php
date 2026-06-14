<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmail_message_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->json('extracted_keywords')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('model')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique('gmail_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classifications');
    }
};
