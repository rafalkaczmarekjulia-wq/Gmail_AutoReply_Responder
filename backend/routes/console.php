<?php

use App\Jobs\ProcessGmailHistoryJob;
use App\Models\GmailAccount;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

Artisan::command('db:ensure-notification-state', function () {
    if (! Schema::hasColumn('gmail_threads', 'notification_state')) {
        Schema::table('gmail_threads', function (Blueprint $table) {
            $table->unsignedTinyInteger('notification_state')->default(0)->after('last_message_at');
        });
        $this->info('Added notification_state column to gmail_threads.');
    } else {
        $this->comment('Column notification_state already exists.');
    }

    DB::table('gmail_threads')->update(['notification_state' => 1]);

    $pendingThreadIds = DB::table('draft_replies')
        ->join('gmail_messages', 'draft_replies.gmail_message_id', '=', 'gmail_messages.id')
        ->where('draft_replies.status', 'pending_approval')
        ->distinct()
        ->pluck('gmail_messages.gmail_thread_id');

    if ($pendingThreadIds->isNotEmpty()) {
        DB::table('gmail_threads')
            ->whereIn('id', $pendingThreadIds)
            ->update(['notification_state' => 0]);
        $this->info('Set notification_state=0 for threads with pending drafts.');
    }

    $this->info('Done.');
})->purpose('Add notification_state column and set read/unread defaults');
