<?php

namespace App\Services;

use App\Jobs\ClassifyMessageJob;
use App\Models\GmailAccount;
use App\Models\GmailMessage;
use App\Models\GmailThread;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MessageSyncService
{
    public function __construct(private GmailService $gmailService) {}

    public function syncMessage(GmailAccount $account, string $gmailMessageId): ?GmailMessage
    {
        $data = $this->gmailService->fetchMessage($account, $gmailMessageId);

        if (! $data || ! $data['is_inbound']) {
            return null;
        }

        $thread = GmailThread::updateOrCreate(
            [
                'gmail_account_id' => $account->id,
                'gmail_thread_id' => $data['gmail_thread_id'],
            ],
            [
                'subject' => $data['subject'],
                'snippet' => Str($data['body_text'])->limit(200)->toString(),
                'last_message_at' => $data['received_at'],
            ]
        );

        if (Schema::hasColumn('gmail_threads', 'notification_state')) {
            $thread->update(['notification_state' => 0]);
        }

        $message = GmailMessage::updateOrCreate(
            [
                'gmail_account_id' => $account->id,
                'gmail_message_id' => $data['gmail_message_id'],
            ],
            [
                'gmail_thread_id' => $thread->id,
                'gmail_thread_id_str' => $data['gmail_thread_id'],
                'from_email' => $data['from_email'],
                'subject' => $data['subject'],
                'body_text' => $data['body_text'],
                'received_at' => $data['received_at'],
            ]
        );

        $this->runPipeline($message->id);

        return $message;
    }

    public function runPipeline(int $gmailMessageId): void
    {
        try {
            ClassifyMessageJob::runPipeline($gmailMessageId);
        } catch (\Throwable $e) {
            Log::error('Pipeline after sync failed', [
                'gmail_message_id' => $gmailMessageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
