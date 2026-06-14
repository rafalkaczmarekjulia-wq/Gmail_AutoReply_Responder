<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        try {
            if (! Schema::hasColumn('users', 'reply_prompt')) {
                Schema::table('users', function ($table) {
                    $table->text('reply_prompt')->nullable();
                });
            }

            if (Schema::hasTable('classifications') && ! Schema::hasColumn('classifications', 'extracted_keywords')) {
                Schema::table('classifications', function ($table) {
                    $table->json('extracted_keywords')->nullable();
                });
            }
        } catch (\Throwable) {
            //
        }
    }
}