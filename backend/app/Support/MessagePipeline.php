<?php

namespace App\Support;

use App\Jobs\ClassifyMessageJob;
use App\Models\Classification;
use App\Models\GmailAccount;
use App\Models\GmailMessage;

class MessagePipeline
{
    public static function processMessage(GmailMessage $message): GmailMessage
    {
        ClassifyMessageJob::dispatchSync($message->id);

        return $message->fresh()->load(['classification', 'draftReply']);
    }

    public static function processPendingForAccount(GmailAccount $account): int
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
                self::processMessage($message);
                $count++;
            });

        return $count;
    }
}
