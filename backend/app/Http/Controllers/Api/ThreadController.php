<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ClassifyMessageJob;
use App\Jobs\GenerateDraftJob;
use App\Models\Classification;
use App\Models\DraftReply;
use App\Models\GmailMessage;
use App\Models\GmailThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ThreadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id');

        $query = GmailThread::with(['messages.classification', 'messages.draftReply', 'gmailAccount'])
            ->whereIn('gmail_account_id', $accountIds);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('subject', 'like', $like)
                    ->orWhere('snippet', 'like', $like);
            });
        }

        $filter = (string) $request->query('filter', 'all');
        if ($filter === 'needs_review') {
            $query->whereHas('messages.draftReply', fn ($q) => $q->where('status', DraftReply::STATUS_PENDING));
        } elseif ($filter === 'sent') {
            $query->whereHas('messages.draftReply', fn ($q) => $q->where('status', DraftReply::STATUS_SENT));
        }

        $label = (string) $request->query('label', 'all');
        if ($label !== '' && $label !== 'all') {
            $query->whereHas('messages.classification', fn ($q) => $q->where('label', $label));
        }

        $mailboxId = (int) $request->query('mailbox', 0);
        if ($mailboxId > 0 && $accountIds->contains($mailboxId)) {
            $query->where('gmail_account_id', $mailboxId);
        } else {
            $mailboxQ = trim((string) $request->query('mailbox_q', ''));
            if ($mailboxQ !== '') {
                $like = '%'.$mailboxQ.'%';
                $query->whereHas('gmailAccount', fn ($q) => $q->where('gmail_email', 'like', $like));
            }
        }

        $threads = $query->orderByDesc('last_message_at')->paginate(20);

        $threads->getCollection()->transform(function (GmailThread $thread) {
            return $thread->applyEffectiveNotificationState();
        });

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
                $cacheKey = 'pipeline:queued:'.$message->id;
                if (Cache::add($cacheKey, true, 300)) {
                    ClassifyMessageJob::dispatch($message->id);
                }
            }
        }

        return response()->json($thread->applyEffectiveNotificationState());
    }

    public function markSeen(Request $request, GmailThread $thread): JsonResponse
    {
        $request->merge(['state' => 1]);

        return $this->updateNotificationState($request, $thread);
    }

    public function updateNotificationState(Request $request, GmailThread $thread): JsonResponse
    {
        $accountIds = $request->user()->gmailAccounts()->pluck('id');
        abort_unless($accountIds->contains($thread->gmail_account_id), 403);

        $validated = $request->validate([
            'state' => ['required', 'integer', 'in:0,1'],
        ]);

        if (! Schema::hasColumn('gmail_threads', 'notification_state')) {
            return response()->json([
                'message' => 'notification_state column missing. Run: php artisan migrate',
                'notification_state' => (int) $validated['state'],
            ], 503);
        }

        $thread->update(['notification_state' => $validated['state']]);

        return response()->json(['notification_state' => (int) $validated['state']]);
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
