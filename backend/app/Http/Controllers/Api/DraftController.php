<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DraftReply;
use App\Services\GmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function __construct(private GmailService $gmailService) {}

    public function approve(Request $request, DraftReply $draft): JsonResponse
    {
        $draft->load('gmailMessage.gmailAccount');
        $accountIds = $request->user()->gmailAccounts()->pluck('id');
        abort_unless($accountIds->contains($draft->gmailMessage->gmail_account_id), 403);

        $data = $request->validate([
            'body' => ['sometimes', 'string'],
        ]);

        $body = $data['body'] ?? $draft->body;
        $message = $draft->gmailMessage;
        $account = $message->gmailAccount;

        if ($draft->gmail_draft_id) {
            $this->gmailService->updateDraft(
                $account,
                $draft->gmail_draft_id,
                $message->gmail_thread_id_str,
                $message->from_email ?? '',
                $message->subject ?? 'Your message',
                $body
            );

            $this->gmailService->sendDraft($account, $draft->gmail_draft_id);
        } else {
            $this->gmailService->sendReply(
                $account,
                $message->gmail_thread_id_str,
                $message->from_email ?? '',
                $message->subject ?? 'Your message',
                $body
            );
        }

        $draft->update([
            'body' => $body,
            'status' => DraftReply::STATUS_SENT,
            'gmail_draft_id' => null,
            'approved_at' => now(),
        ]);

        return response()->json($draft->fresh());
    }

    public function reject(Request $request, DraftReply $draft): JsonResponse
    {
        $draft->load('gmailMessage');
        $accountIds = $request->user()->gmailAccounts()->pluck('id');
        abort_unless($accountIds->contains($draft->gmailMessage->gmail_account_id), 403);

        $draft->update(['status' => DraftReply::STATUS_REJECTED]);

        return response()->json($draft->fresh());
    }
}
