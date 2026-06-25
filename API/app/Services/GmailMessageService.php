<?php

namespace App\Services;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\GmailAccount;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class GmailMessageService
{
    public function __construct(private GoogleOAuthService $oauthService) {}

    // fetch messsage IDs from $startHistoryId
    public function fetchNewMessageIds(GmailAccount $account, string $startHistoryId): array
    {
        $client  = $this->oauthService->getAuthenticatedClient($account);
        $service = new Gmail($client);

        $messageIds = [];
        $pageToken  = null;

        do {
            $params = [
                'startHistoryId'  => $startHistoryId,
                'historyTypes'    => ['messageAdded'],
                'labelId'         => 'INBOX',
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $service->users_history->listUsersHistory('me', $params);

            foreach ($response->getHistory() ?? [] as $historyItem) {
                foreach ($historyItem->getMessagesAdded() ?? [] as $added) {
                    $messageIds[] = $added->getMessage()->getId();
                }
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return array_unique($messageIds);
    }

    // Fetch a full message from Gmail and upsert it into the database.
    public function syncMessage(GmailAccount $account, string $gmailMessageId): ?EmailMessage
    {
        if (EmailMessage::where('gmail_message_id', $gmailMessageId)->exists()) {
            Log::debug('Message already synced, skipping', ['gmail_message_id' => $gmailMessageId]);
            return null;
        }

        $client  = $this->oauthService->getAuthenticatedClient($account);
        $service = new Gmail($client);

        $raw = $service->users_messages->get('me', $gmailMessageId, ['format' => 'full']);

        $parsed = $this->parseMessage($raw);

        $thread = EmailThread::firstOrCreate(
            [
                'gmail_account_id' => $account->id,
                'gmail_thread_id'  => $raw->getThreadId(),
            ],
            [
                'subject'         => $parsed['subject'],
                'snippet'         => $raw->getSnippet(),
                'from_address'    => $parsed['from'],
                'last_message_at' => $parsed['received_at'],
            ]
        );

        $thread->update([
            'last_message_at' => $parsed['received_at'],
            'snippet'         => $raw->getSnippet(),
        ]);

        return EmailMessage::create([
            'email_thread_id'  => $thread->id,
            'gmail_message_id' => $gmailMessageId,
            'from_address'     => $parsed['from'],
            'to_address'       => $parsed['to'],
            'subject'          => $parsed['subject'],
            'body_text'        => $parsed['body_text'],
            'body_html'        => $parsed['body_html'],
            'received_at'      => $parsed['received_at'],
        ]);
    }

    private function parseMessage(Message $message): array
    {
        $payload = $message->getPayload();
        $headers = [];

        foreach ($payload->getHeaders() as $header) {
            $headers[strtolower($header->getName())] = $header->getValue();
        }

        [$bodyText, $bodyHtml] = $this->extractBody($payload);

        $receivedAt = isset($headers['date'])
            ? Carbon::parse($headers['date'])
            : Carbon::createFromTimestampMs($message->getInternalDate());

        return [
            'from'        => $headers['from']    ?? null,
            'to'          => $headers['to']      ?? null,
            'subject'     => $headers['subject'] ?? null,
            'body_text'   => $bodyText,
            'body_html'   => $bodyHtml,
            'received_at' => $receivedAt,
        ];
    }

    private function extractBody($payload): array
    {
        $bodyText = null;
        $bodyHtml = null;

        $mimeType = $payload->getMimeType();
        $parts    = $payload->getParts();

        if (empty($parts)) {
            $data = $payload->getBody()->getData();
            $decoded = $data ? base64_decode(strtr($data, '-_', '+/')) : null;

            if ($mimeType === 'text/plain') {
                $bodyText = $decoded;
            } elseif ($mimeType === 'text/html') {
                $bodyHtml = $decoded;
            }

            return [$bodyText, $bodyHtml];
        }

        foreach ($parts as $part) {
            [$text, $html] = $this->extractBody($part);
            $bodyText = $bodyText ?? $text;
            $bodyHtml = $bodyHtml ?? $html;
        }

        return [$bodyText, $bodyHtml];
    }
}
