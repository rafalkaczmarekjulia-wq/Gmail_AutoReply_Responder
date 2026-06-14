<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGmailHistoryJob;
use App\Models\GmailAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class SimulateWebhooks extends Command
{
    protected $signature = 'gmail:simulate-webhooks
                            {count=100 : Number of webhook notifications to enqueue}
                            {--account= : Gmail account ID (defaults to first active)}';

    protected $description = 'Load-test Pub/Sub ingress by enqueueing history sync jobs';

    public function handle(): int
    {
        $count = max(1, (int) $this->argument('count'));
        $accountId = $this->option('account');

        $account = $accountId
            ? GmailAccount::find($accountId)
            : GmailAccount::where('status', 'active')->first();

        if (! $account) {
            $this->error('No Gmail account found. Connect a mailbox first.');

            return self::FAILURE;
        }

        $before = $this->queueDepth();
        $started = microtime(true);

        for ($i = 0; $i < $count; $i++) {
            ProcessGmailHistoryJob::queue($account->id, 'loadtest:'.now()->timestamp.":{$i}");
        }

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);
        $after = $this->queueDepth();

        $this->table(['Metric', 'Value'], [
            ['Account', "{$account->gmail_email} (#{$account->id})"],
            ['Jobs enqueued', (string) $count],
            ['Enqueue time (ms)', (string) $elapsedMs],
            ['gmail-sync depth (before)', (string) $before],
            ['gmail-sync depth (after)', (string) $after],
        ]);

        $this->info('Monitor worker/Horizon and GET /api/metrics while jobs drain.');

        return self::SUCCESS;
    }

    private function queueDepth(): int
    {
        try {
            return Queue::connection('redis')->size('gmail-sync');
        } catch (\Throwable) {
            return -1;
        }
    }
}
