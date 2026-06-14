<?php

namespace App\Services;

use App\Models\GmailAccount;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\WatchRequest;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GmailService
{
    private const SCOPES = [
        Gmail::GMAIL_READONLY,
        Gmail::GMAIL_COMPOSE,
        Gmail::GMAIL_MODIFY,
    ];

    public function getAuthUrl(int $userId): string
    {
        $this->ensureGoogleConfigured();

        $client = $this->makeOAuthClient();
        $client->setState($this->createOAuthState($userId));

        return $client->createAuthUrl();
    }

    public function createOAuthState(int $userId): string
    {
        return encrypt([
            'user_id' => $userId,
            'ts' => now()->timestamp,
        ]);
    }

    public function parseOAuthState(string $state): int
    {
        try {
            $payload = decrypt($state);
        } catch (DecryptException) {
            throw new RuntimeException('Invalid OAuth state. Please try Connect Gmail again.');
        }

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw new RuntimeException('Invalid OAuth state. Please try Connect Gmail again.');
        }

        if (now()->timestamp - (int) ($payload['ts'] ?? 0) > 600) {
            throw new RuntimeException('OAuth session expired. Please try Connect Gmail again.');
        }

        return (int) $payload['user_id'];
    }

    public function isConfigured(): bool
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        return filled($clientId) && filled($clientSecret);
    }

    public function isPubSubConfigured(): bool
    {
        $topic = config('services.google.pubsub_topic');

        return filled($topic) && ! str_contains($topic, 'your-project');
    }

    public function handleCallback(string $code, int $userId): GmailAccount
    {
        $this->ensureGoogleConfigured();

        $client = $this->makeOAuthClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException('OAuth error: '.$token['error']);
        }

        $client->setAccessToken($token);
        $gmail = new Gmail($client);
        $profile = $gmail->users->getProfile('me');

        $account = GmailAccount::updateOrCreate(
            [
                'user_id' => $userId,
                'google_account_id' => $profile->getEmailAddress(),
            ],
            [
                'gmail_email' => $profile->getEmailAddress(),
                'encrypted_refresh_token' => $token['refresh_token'] ?? '',
                'encrypted_access_token' => $token['access_token'] ?? null,
                'token_expires_at' => isset($token['expires_in'])
                    ? now()->addSeconds((int) $token['expires_in'])
                    : null,
                'last_history_id' => (string) $profile->getHistoryId(),
                'status' => 'active',
            ]
        );

        if ($this->isPubSubConfigured()) {
            $this->startWatch($account);
        } else {
            Log::info('Gmail watch skipped — Pub/Sub not configured. Use Sync now on dashboard.', [
                'gmail_account_id' => $account->id,
            ]);
        }

        return $account->fresh();
    }

    public function getGmailClient(GmailAccount $account): Gmail
    {
        $this->ensureValidToken($account);

        $client = $this->makeOAuthClient();
        $client->setAccessToken([
            'access_token' => $account->encrypted_access_token,
            'refresh_token' => $account->encrypted_refresh_token,
            'expires_in' => max(0, now()->diffInSeconds($account->token_expires_at, false)),
        ]);

        return new Gmail($client);
    }

    public function startWatch(GmailAccount $account): void
    {
        $gmail = $this->getGmailClient($account);
        $request = new WatchRequest();
        $request->setTopicName(config('services.google.pubsub_topic'));
        $request->setLabelIds(['INBOX']);

        $response = $gmail->users->watch('me', $request);

        $account->update([
            'last_history_id' => (string) $response->getHistoryId(),
            'watch_expires_at' => now()->addMilliseconds((int) $response->getExpiration()),
            'status' => 'active',
        ]);
    }

    public function stopWatch(GmailAccount $account): void
    {
        try {
            $gmail = $this->getGmailClient($account);
            $gmail->users->stop('me');
        } catch (\Throwable $e) {
            Log::warning('Failed to stop Gmail watch', [
                'gmail_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function fetchHistoryChanges(GmailAccount $account, ?string $startHistoryId): array
    {
        $gmail = $this->getGmailClient($account);
        $messageIds = [];
        $pageToken = null;
        $latestHistoryId = $startHistoryId;
        $historyRecordCount = 0;

        do {
            $params = ['startHistoryId' => $startHistoryId];
            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            try {
                $history = $gmail->users_history->listUsersHistory('me', $params);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() === 404) {
                    Log::warning('History ID too old, full resync needed', [
                        'gmail_account_id' => $account->id,
                        'history_id' => $startHistoryId,
                    ]);
                    throw new RuntimeException('history_too_old');
                }
                throw $e;
            }

            if ($history->getHistoryId()) {
                $latestHistoryId = (string) $history->getHistoryId();
            }

            foreach ($history->getHistory() ?? [] as $record) {
                $historyRecordCount++;

                foreach ($record->getMessagesAdded() ?? [] as $added) {
                    $messageIds[] = $added->getMessage()->getId();
                }

                foreach ($record->getLabelsAdded() ?? [] as $labeled) {
                    $labels = $labeled->getLabelIds() ?? [];
                    if (in_array('INBOX', $labels, true) && $labeled->getMessage()?->getId()) {
                        $messageIds[] = $labeled->getMessage()->getId();
                    }
                }
            }

            $pageToken = $history->getNextPageToken();
        } while ($pageToken);

        return [
            'message_ids' => array_values(array_unique($messageIds)),
            'latest_history_id' => $latestHistoryId,
            'history_record_count' => $historyRecordCount,
        ];
    }

    public function fetchMessage(GmailAccount $account, string $messageId): ?array
    {
        $gmail = $this->getGmailClient($account);

        try {
            $message = $gmail->users_messages->get('me', $messageId, ['format' => 'full']);
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }

        $headers = $message->getPayload()->getHeaders() ?? [];

        $from = '';
        $subject = '';
        foreach ($headers as $header) {
            if ($header->getName() === 'From') {
                $from = $header->getValue();
            }
            if ($header->getName() === 'Subject') {
                $subject = $header->getValue();
            }
        }
        $body = $this->extractBody($message->getPayload());

        $isInbound = ! str_contains(strtolower($from), strtolower($account->gmail_email));

        return [
            'gmail_message_id' => $message->getId(),
            'gmail_thread_id' => $message->getThreadId(),
            'from_email' => $from,
            'subject' => $subject,
            'body_text' => $body,
            'received_at' => $message->getInternalDate()
                ? now()->createFromTimestampMs((int) $message->getInternalDate())
                : now(),
            'is_inbound' => $isInbound,
        ];
    }

    public function createDraft(GmailAccount $account, string $threadId, string $to, string $subject, string $body): string
    {
        $gmail = $this->getGmailClient($account);
        $raw = $this->buildRawEmail($to, $subject, $body, $threadId);

        $draft = new Gmail\Draft();
        $message = new Gmail\Message();
        $message->setRaw($raw);
        $message->setThreadId($threadId);
        $draft->setMessage($message);

        $created = $gmail->users_drafts->create('me', $draft);

        return $created->getId();
    }

    public function updateDraft(GmailAccount $account, string $draftId, string $threadId, string $to, string $subject, string $body): void
    {
        $gmail = $this->getGmailClient($account);
        $raw = $this->buildRawEmail($to, $subject, $body, $threadId);

        $draft = new Gmail\Draft();
        $message = new Gmail\Message();
        $message->setRaw($raw);
        $message->setThreadId($threadId);
        $draft->setMessage($message);
        $draft->setId($draftId);

        $gmail->users_drafts->update('me', $draftId, $draft);
    }

    public function sendDraft(GmailAccount $account, string $draftId): string
    {
        $gmail = $this->getGmailClient($account);

        $draft = new Gmail\Draft();
        $draft->setId($draftId);

        $sent = $gmail->users_drafts->send('me', $draft);

        return $sent->getId() ?? '';
    }

    public function sendReply(
        GmailAccount $account,
        string $threadId,
        string $to,
        string $subject,
        string $body
    ): string {
        $gmail = $this->getGmailClient($account);
        $raw = $this->buildRawEmail($to, $subject, $body, $threadId);

        $message = new Gmail\Message();
        $message->setRaw($raw);
        $message->setThreadId($threadId);

        $sent = $gmail->users_messages->send('me', $message);

        return $sent->getId() ?? '';
    }

    private function ensureValidToken(GmailAccount $account): void
    {
        if (! $account->isTokenExpired()) {
            return;
        }

        $lockKey = "gmail:token_refresh:{$account->id}";
        Cache::lock($lockKey, 10)->block(5, function () use ($account) {
            $account->refresh();

            if (! $account->isTokenExpired()) {
                return;
            }

            $client = $this->makeOAuthClient();
            $client->setAccessToken([
                'refresh_token' => $account->encrypted_refresh_token,
            ]);

            $newToken = $client->fetchAccessTokenWithRefreshToken($account->encrypted_refresh_token);

            if (isset($newToken['error'])) {
                $account->update(['status' => 'token_revoked']);
                throw new RuntimeException('Token refresh failed: '.$newToken['error']);
            }

            $account->update([
                'encrypted_access_token' => $newToken['access_token'],
                'token_expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
                'status' => 'active',
            ]);
        });
    }

    private function makeOAuthClient(): GoogleClient
    {
        $this->ensureGoogleConfigured();

        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent select_account');
        $client->setScopes(self::SCOPES);

        $caBundle = base_path('cacert.pem');
        if (is_readable($caBundle)) {
            $client->setHttpClient(new GuzzleClient(['verify' => $caBundle]));
        }

        return $client;
    }

    private function ensureGoogleConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Google OAuth is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in backend/.env — see docs/GOOGLE_SETUP.md'
            );
        }
    }

    private function extractBody(?Gmail\MessagePart $part): string
    {
        if (! $part) {
            return '';
        }

        if ($part->getBody()?->getData()) {
            return $this->decodeBody($part->getBody()->getData());
        }

        foreach ($part->getParts() ?? [] as $subPart) {
            if ($subPart->getMimeType() === 'text/plain' && $subPart->getBody()?->getData()) {
                return $this->decodeBody($subPart->getBody()->getData());
            }
        }

        foreach ($part->getParts() ?? [] as $subPart) {
            $text = $this->extractBody($subPart);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function decodeBody(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    private function buildRawEmail(string $to, string $subject, string $body, string $threadId): string
    {
        $raw = "To: {$to}\r\n";
        $raw .= "Subject: Re: {$subject}\r\n";
        $raw .= "In-Reply-To: <{$threadId}>\r\n";
        $raw .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $raw .= $body;

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
