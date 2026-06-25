<?php

namespace App\Services;

use App\Models\GmailAccount;
use Google\Service\Gmail;
use Google\Service\Gmail\WatchRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class GmailWatchService
{
    public function __construct(private GoogleOAuthService $oauthService) {}

    public function startWatch(GmailAccount $account): void
    {
        $client  = $this->oauthService->getAuthenticatedClient($account);
        $service = new Gmail($client);

        $request = new WatchRequest();
        $request->setLabelIds(['INBOX']);
        $request->setTopicName(config('services.google.pubsub_topic'));

        $response = $service->users->watch('me', $request);

        $account->update([
            'watch_history_id' => $response->getHistoryId(),
            // expiration comes back as milliseconds since epoch
            'watch_expires_at' => Carbon::createFromTimestampMs($response->getExpiration()),
        ]);

        Log::info('Gmail watch started', [
            'gmail_account_id' => $account->id,
            'gmail_address'    => $account->gmail_address,
            'history_id'       => $response->getHistoryId(),
            'expires_at'       => $account->watch_expires_at,
        ]);
    }

    public function renewWatch(GmailAccount $account): void
    {
        $this->startWatch($account);
    }

    public function stopWatch(GmailAccount $account): void
    {
        $client  = $this->oauthService->getAuthenticatedClient($account);
        $service = new Gmail($client);

        $service->users->stop('me');

        $account->update([
            'watch_history_id' => null,
            'watch_expires_at' => null,
        ]);

        Log::info('Gmail watch stopped', [
            'gmail_account_id' => $account->id,
            'gmail_address'    => $account->gmail_address,
        ]);
    }
}
