<?php

namespace App\Console\Commands;

use App\Models\GmailAccount;
use App\Services\GmailService;
use Illuminate\Console\Command;

class RenewGmailWatches extends Command
{
    protected $signature = 'gmail:renew-watches';

    protected $description = 'Renew Gmail push watches expiring within 24 hours';

    public function handle(GmailService $gmailService): int
    {
        $accounts = GmailAccount::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('watch_expires_at')
                    ->orWhere('watch_expires_at', '<=', now()->addDay());
            })
            ->get();

        foreach ($accounts as $account) {
            try {
                $gmailService->startWatch($account);
                $this->info("Renewed watch for {$account->gmail_email}");
            } catch (\Throwable $e) {
                $this->error("Failed for {$account->gmail_email}: {$e->getMessage()}");
                $account->update(['status' => 'watch_expired']);
            }
        }

        return self::SUCCESS;
    }
}
