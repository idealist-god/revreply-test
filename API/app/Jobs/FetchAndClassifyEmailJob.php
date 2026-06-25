<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Models\GmailAccount;
use App\Services\GmailMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchAndClassifyEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(
        public GmailAccount $account,
        public string $gmailMessageId,
    ) {}

    public function handle(GmailMessageService $messageService): void
    {
        if (EmailMessage::where('gmail_message_id', $this->gmailMessageId)->exists()) {
            Log::debug('FetchAndClassifyEmailJob: already processed, skipping', [
                'gmail_message_id' => $this->gmailMessageId,
            ]);
            return;
        }

        $message = $messageService->syncMessage($this->account, $this->gmailMessageId);

        if (! $message) {
            return;
        }

        ClassifyEmailJob::dispatch($message)->onQueue('classify');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('FetchAndClassifyEmailJob failed', [
            'gmail_account_id' => $this->account->id,
            'gmail_message_id' => $this->gmailMessageId,
            'error'            => $e->getMessage(),
        ]);
    }
}
