<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGmailHistoryJob;
use App\Models\GmailAccount;
use App\Services\GmailService;
use Illuminate\Console\Command;

class PollGmailAccounts extends Command
{
    protected $signature = 'gmail:poll';

    protected $description = 'Poll active Gmail accounts for new mail (fallback when Pub/Sub is not configured)';

    public function handle(GmailService $gmailService): int
    {
        $accounts = GmailAccount::whereIn('status', ['active', 'watch_expired', 'error'])
            ->whereNotNull('last_history_id')
            ->get();

        if ($accounts->isEmpty()) {
            $this->comment('No mailboxes to process.');

            return self::SUCCESS;
        }

        $pollMinute = now()->format('Y-m-d-H-i');
        $usePoll = ! $gmailService->isPubSubConfigured();

        foreach ($accounts as $account) {
            if ($usePoll) {
                $pollKey = 'poll:'.$account->id.':'.$pollMinute;
                ProcessGmailHistoryJob::dispatchSync($account->id, $pollKey);
                $this->info("Polled {$account->gmail_email}");
            } else {
                $count = ProcessGmailHistoryJob::processPendingForAccount($account);
                if ($count > 0) {
                    $this->info("Processed {$count} message(s) for {$account->gmail_email}");
                }
            }
        }

        return self::SUCCESS;
    }
}
