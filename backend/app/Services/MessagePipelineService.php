<?php

namespace App\Services;

use App\Jobs\ClassifyMessageJob;
use App\Jobs\GenerateDraftJob;
use App\Models\Classification;
use App\Models\GmailAccount;
use App\Models\GmailMessage;

class MessagePipelineService
{
    public function processMessage(GmailMessage $message): GmailMessage
    {
        ClassifyMessageJob::dispatchSync($message->id);

        $message->refresh()->load(['classification', 'draftReply']);

        if (
            ! $message->draftReply
            && $message->classification
            && $message->classification->label !== Classification::LABEL_NOT_INTERESTED
        ) {
            GenerateDraftJob::dispatchSync($message->id);
            $message->refresh()->load(['classification', 'draftReply']);
        }

        return $message;
    }

    public function processPendingForAccount(GmailAccount $account): int
    {
        $count = 0;

        GmailMessage::query()
            ->where('gmail_account_id', $account->id)
            ->where(function ($query) {
                $query->whereDoesntHave('classification')
                    ->orWhere(function ($query) {
                        $query->whereHas('classification', function ($query) {
                            $query->where('label', '!=', Classification::LABEL_NOT_INTERESTED);
                        })->whereDoesntHave('draftReply');
                    });
            })
            ->orderBy('id')
            ->each(function (GmailMessage $message) use (&$count) {
                $this->processMessage($message);
                $count++;
            });

        return $count;
    }
}
