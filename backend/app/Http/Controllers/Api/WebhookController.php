<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGmailHistoryJob;
use App\Models\GmailAccount;
use App\Models\ProcessedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function pubsub(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (! isset($payload['message']['data'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $data = json_decode(base64_decode($payload['message']['data']), true);

        if (! is_array($data)) {
            return response()->json(['error' => 'Invalid message data'], 400);
        }

        $emailAddress = $data['emailAddress'] ?? null;
        $historyId = isset($data['historyId']) ? (string) $data['historyId'] : null;

        if (! $emailAddress || ! $historyId) {
            return response()->json(['error' => 'Missing emailAddress or historyId'], 400);
        }

        $account = GmailAccount::where('gmail_email', $emailAddress)->first();

        if (! $account) {
            Log::warning('Pub/Sub for unknown mailbox', ['email' => $emailAddress]);
            return response()->json(['status' => 'ignored']);
        }

        $exists = ProcessedNotification::where('gmail_account_id', $account->id)
            ->where('history_id', $historyId)
            ->exists();

        if ($exists) {
            return response()->json(['status' => 'duplicate']);
        }

        ProcessGmailHistoryJob::dispatch($account->id, $historyId);

        return response()->json(['status' => 'queued']);
    }
}
