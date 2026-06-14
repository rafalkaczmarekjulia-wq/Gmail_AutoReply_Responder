<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ClassifyMessageJob;
use App\Jobs\GenerateDraftJob;
use App\Jobs\ProcessGmailHistoryJob;
use App\Models\Classification;
use App\Models\GmailMessage;
use App\Models\GmailThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id');

        foreach ($request->user()->gmailAccounts as $account) {
            ProcessGmailHistoryJob::processPendingForAccount($account);
        }

        $threads = GmailThread::with(['messages.classification', 'messages.draftReply', 'gmailAccount'])
            ->whereIn('gmail_account_id', $accountIds)
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return response()->json($threads);
    }

    public function show(Request $request, GmailThread $thread): JsonResponse
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id');
        abort_unless($accountIds->contains($thread->gmail_account_id), 403);

        $thread->load(['messages.classification', 'messages.draftReply', 'gmailAccount']);

        foreach ($thread->messages as $message) {
            $needsPipeline = ! $message->classification
                || (! $message->draftReply && $message->classification?->label !== Classification::LABEL_NOT_INTERESTED);

            if ($needsPipeline) {
                try {
                    ClassifyMessageJob::runPipeline($message->id);
                } catch (\Throwable) {
                    // logged in job; return partial thread state
                }
            }
        }

        $thread->load(['messages.classification', 'messages.draftReply', 'gmailAccount']);

        return response()->json($thread);
    }

    public function message(Request $request, GmailMessage $message): JsonResponse
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id');
        abort_unless($accountIds->contains($message->gmail_account_id), 403);

        $message->load(['classification', 'draftReply', 'thread', 'gmailAccount']);

        return response()->json($message);
    }

    public function generateDraft(Request $request, GmailMessage $message): JsonResponse
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id');
        abort_unless($accountIds->contains($message->gmail_account_id), 403);

        $message->load(['classification', 'draftReply']);

        if ($message->draftReply) {
            return response()->json($message->load(['classification', 'draftReply', 'thread', 'gmailAccount']));
        }

        if (! $message->classification) {
            ClassifyMessageJob::dispatchSync($message->id);
            $message->refresh()->load(['classification', 'draftReply']);
        }

        if ($message->classification?->label === Classification::LABEL_NOT_INTERESTED) {
            return response()->json(['message' => 'Drafts are not generated for not_interested mail.'], 422);
        }

        if (! $message->draftReply) {
            GenerateDraftJob::dispatchSync($message->id);
        }

        return response()->json(
            $message->fresh()->load(['classification', 'draftReply', 'thread', 'gmailAccount'])
        );
    }

    public function process(Request $request, GmailMessage $message): JsonResponse
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id');
        abort_unless($accountIds->contains($message->gmail_account_id), 403);

        try {
            ClassifyMessageJob::dispatchSync($message->id);
            $message->refresh()->load(['classification', 'draftReply', 'thread', 'gmailAccount']);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
                'hint' => 'If this mentions a missing column, run: cd backend && php artisan migrate',
            ], 500);
        }

        if (! $message->classification) {
            return response()->json(['message' => 'Classification failed'], 500);
        }

        if (! $message->draftReply && $message->classification->label !== Classification::LABEL_NOT_INTERESTED) {
            return response()->json([
                'message' => 'Draft was not created. Reconnect Gmail on Mailboxes or check backend logs.',
                'classification' => $message->classification,
            ], 500);
        }

        return response()->json($message);
    }
}
