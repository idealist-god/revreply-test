<?php

namespace App\Jobs;

use App\Models\GmailAccount;
use App\Services\GmailMessageService;
use App\Services\GoogleOAuthService;
use Google\Service\Gmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessGmailHistoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public GmailAccount $account,
        public string $historyId,
    ) {}

    public function handle(GmailMessageService $messageService, GoogleOAuthService $oauthService): void
    {
        $startHistoryId = $this->account->watch_history_id ?? $this->historyId;

        try {
            $messageIds = $messageService->fetchNewMessageIds($this->account, $startHistoryId);
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 410) {
                $this->handleGoneError($oauthService);
                return;
            }
            throw $e;
        }

        foreach ($messageIds as $messageId) {
            FetchAndClassifyEmailJob::dispatch($this->account, $messageId)
                ->onQueue('classify');
        }

        $this->account->update(['watch_history_id' => $this->historyId]);

        Log::info('ProcessGmailHistoryJob completed', [
            'gmail_account_id' => $this->account->id,
            'new_messages'     => count($messageIds),
            'history_id'       => $this->historyId,
        ]);
    }

    private function handleGoneError(GoogleOAuthService $oauthService): void
    {
        Log::warning('Gmail history gone (410), resetting historyId', [
            'gmail_account_id' => $this->account->id,
        ]);

        $client  = $oauthService->getAuthenticatedClient($this->account);
        $service = new Gmail($client);
        $profile = $service->users->getProfile('me');

        $this->account->update([
            'watch_history_id' => $profile->getHistoryId(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessGmailHistoryJob failed', [
            'gmail_account_id' => $this->account->id,
            'history_id'       => $this->historyId,
            'error'            => $e->getMessage(),
        ]);
    }
}
