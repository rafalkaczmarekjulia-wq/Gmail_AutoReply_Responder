<?php

use App\Jobs\ProcessGmailHistoryJob;
use App\Models\GmailAccount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gmail:process-pending', function () {
    $accounts = GmailAccount::whereIn('status', ['active', 'watch_expired', 'error'])
        ->whereNotNull('last_history_id')
        ->get();

    $total = 0;
    foreach ($accounts as $account) {
        $count = ProcessGmailHistoryJob::processPendingForAccount($account);
        $total += $count;
        if ($count > 0) {
            $this->info("{$account->gmail_email}: {$count} message(s) processed");
        }
    }

    if ($total === 0) {
        $this->comment('No pending messages.');
    } else {
        $this->info("Done. {$total} message(s) processed.");
    }
})->purpose('Auto classify and draft for unprocessed messages');

Artisan::command('gmail:backfill-drafts {--account= : Gmail account ID}', function () {
    $accountId = $this->option('account');

    $query = GmailAccount::query();
    if ($accountId) {
        $query->where('id', $accountId);
    }

    $total = 0;
    foreach ($query->get() as $account) {
        $count = ProcessGmailHistoryJob::processPendingForAccount($account);
        $total += $count;
        if ($count > 0) {
            $this->info("{$account->gmail_email}: {$count} message(s) processed");
        }
    }

    $this->info("Done. {$total} message(s) processed.");
})->purpose('Classify and create missing reply drafts');
