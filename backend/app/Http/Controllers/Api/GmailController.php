<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGmailHistoryJob;
use App\Models\GmailAccount;
use App\Services\GmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GmailController extends Controller
{
    public function __construct(private GmailService $gmailService) {}

    public function connect(Request $request): JsonResponse
    {
        if (! $this->gmailService->isConfigured()) {
            return response()->json([
                'message' => 'Google OAuth is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to backend/.env (see docs/GOOGLE_SETUP.md).',
                'configured' => false,
            ], 422);
        }

        $url = $this->gmailService->getAuthUrl($request->user()->id);

        return response()->json(['url' => $url, 'configured' => true]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'oauth_configured' => $this->gmailService->isConfigured(),
            'pubsub_configured' => $this->gmailService->isPubSubConfigured(),
            'redirect_uri' => config('services.google.redirect_uri'),
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        $state = $request->query('state');

        $mailboxesUrl = config('app.frontend_url').'/dashboard/mailboxes';

        if (! $code || ! $state) {
            return redirect($mailboxesUrl.'?error=oauth_failed');
        }

        try {
            $userId = $this->gmailService->parseOAuthState($state);
            $account = $this->gmailService->handleCallback($code, $userId);
        } catch (\Throwable $e) {
            return redirect($mailboxesUrl.'?error='.urlencode($e->getMessage()));
        }

        return redirect(
            $mailboxesUrl.'?connected=1&email='.urlencode($account->gmail_email)
        );
    }

    public function accounts(Request $request): JsonResponse
    {
        $accounts = $request->user()
            ->gmailAccounts()
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (GmailAccount $a) => [
                'id' => $a->id,
                'gmail_email' => $a->gmail_email,
                'status' => $a->status,
                'status_label' => $this->statusLabel($a->status),
                'last_history_id' => $a->last_history_id,
                'watch_expires_at' => $a->watch_expires_at,
                'connected_at' => $a->created_at,
                'updated_at' => $a->updated_at,
                'messages_count' => $a->messages_count,
            ]);

        return response()->json([
            'data' => $accounts,
            'meta' => [
                'total' => $accounts->count(),
                'oauth_configured' => $this->gmailService->isConfigured(),
                'pubsub_configured' => $this->gmailService->isPubSubConfigured(),
            ],
        ]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'token_revoked' => 'Reconnect required',
            'watch_expired' => 'Watch expired',
            'error' => 'Error',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function destroy(Request $request, GmailAccount $gmailAccount): JsonResponse
    {
        abort_unless($gmailAccount->user_id === $request->user()->id, 403);

        $this->gmailService->stopWatch($gmailAccount);
        $gmailAccount->delete();

        return response()->json(['message' => 'Gmail account disconnected']);
    }

    public function sync(Request $request, GmailAccount $gmailAccount): JsonResponse
    {
        abort_unless($gmailAccount->user_id === $request->user()->id, 403);

        if (! $gmailAccount->last_history_id) {
            return response()->json(['message' => 'No history ID'], 422);
        }

        try {
            $historyId = 'manual:'.$gmailAccount->id.':'.(int) (microtime(true) * 1000);
            ProcessGmailHistoryJob::dispatchSync($gmailAccount->id, $historyId);

            return response()->json(['message' => 'Sync complete', 'history_id' => $historyId]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function syncAll(Request $request): JsonResponse
    {
        $accounts = $request->user()
            ->gmailAccounts()
            ->whereIn('status', ['active', 'watch_expired', 'error'])
            ->whereNotNull('last_history_id')
            ->get();

        $synced = 0;
        $processed = 0;
        $errors = [];
        $usePoll = ! $this->gmailService->isPubSubConfigured();

        foreach ($accounts as $account) {
            try {
                if ($usePoll) {
                    $historyId = 'auto:'.$account->id.':'.(int) (microtime(true) * 1000);
                    ProcessGmailHistoryJob::dispatchSync($account->id, $historyId);
                }
                $processed += ProcessGmailHistoryJob::processPendingForAccount($account);
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = $account->gmail_email.': '.$e->getMessage();
            }
        }

        if ($errors !== []) {
            return response()->json([
                'message' => implode('; ', $errors),
                'synced' => $synced,
                'processed' => $processed,
            ], 500);
        }

        return response()->json([
            'message' => 'Sync complete',
            'synced' => $synced,
            'processed' => $processed,
        ]);
    }
}
