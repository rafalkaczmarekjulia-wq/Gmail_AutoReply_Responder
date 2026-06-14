<?php

namespace App\Jobs;

use App\Models\DraftReply;
use App\Models\GmailMessage;
use App\Services\GmailService;
use App\Services\LlmService;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $gmailMessageId)
    {
        $this->onQueue('ai');
    }

    public function handle(GmailService $gmailService, LlmService $llmService): void
    {
        $message = GmailMessage::with(['gmailAccount.user', 'classification', 'draftReply'])
            ->findOrFail($this->gmailMessageId);

        if ($message->draftReply) {
            return;
        }

        $classification = $message->classification;
        $label = $classification?->label ?? 'unclear';
        $keywords = $classification?->extracted_keywords ?? [];
        $replyPrompt = $message->gmailAccount?->user?->reply_prompt
            ?? \App\Models\User::defaultReplyPrompt();

        $body = $llmService->generateDraft(
            $message->subject ?? '',
            $message->body_text ?? '',
            $label,
            $keywords,
            $replyPrompt
        );

        $account = $message->gmailAccount;
        $draftId = null;

        try {
            $draftId = $gmailService->createDraft(
                $account,
                $message->gmail_thread_id_str,
                $message->from_email ?? '',
                $message->subject ?? 'Your message',
                $body
            );
        } catch (\Throwable $e) {
            Log::warning('Gmail draft create failed; saving in-app draft only', [
                'gmail_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        DraftReply::create([
            'gmail_message_id' => $message->id,
            'gmail_draft_id' => $draftId,
            'body' => $body,
            'status' => DraftReply::STATUS_PENDING,
        ]);
    }
}
