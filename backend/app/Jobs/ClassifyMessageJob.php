<?php

namespace App\Jobs;

use App\Models\Classification;
use App\Models\GmailMessage;
use App\Services\LlmService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $gmailMessageId)
    {
        $this->onQueue('ai');
    }

    public static function runPipeline(int $gmailMessageId): void
    {
        try {
            (new self($gmailMessageId))->handle(app(LlmService::class));
        } catch (\Throwable $e) {
            Log::error('Message pipeline failed', [
                'gmail_message_id' => $gmailMessageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function handle(LlmService $llmService): void
    {
        $message = GmailMessage::with(['classification', 'draftReply'])->findOrFail($this->gmailMessageId);

        if ($message->classification) {
            if (! $message->draftReply && $message->classification->label !== Classification::LABEL_NOT_INTERESTED) {
                GenerateDraftJob::dispatchSync($message->id);
            }

            return;
        }

        $result = $llmService->classify(
            $message->subject ?? '',
            $message->body_text ?? ''
        );

        $classificationData = [
            'gmail_message_id' => $message->id,
            'label' => $result['label'],
            'confidence' => $result['confidence'],
            'model' => $result['model'],
            'raw_response' => $result['raw_response'],
        ];

        if (Schema::hasColumn('classifications', 'extracted_keywords')) {
            $classificationData['extracted_keywords'] = $result['keywords'] ?? [];
        }

        $classification = Classification::create($classificationData);

        if ($classification->label !== Classification::LABEL_NOT_INTERESTED) {
            GenerateDraftJob::dispatchSync($message->id);
        }
    }
}
