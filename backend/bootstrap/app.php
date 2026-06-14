<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('gmail:renew-watches')->hourly();
        // Poll mailboxes + auto classify/draft every minute (local dev and Pub/Sub fallback)
        $schedule->command('gmail:poll')->everyMinute();
        $schedule->command('gmail:process-pending')->everyMinute();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
