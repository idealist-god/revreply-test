<?php

namespace App\Jobs;

use App\Models\DraftReply;
use App\Models\EmailMessage;
use App\Services\DraftGenerationService;
use App\Services\GoogleOAuthService;
use Google\Service\Gmail;
use Google\Service\Gmail\Draft;
use Google\Service\Gmail\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateDraftJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [30, 60, 120];

    public function __construct(public EmailMessage $message) {}

    public function handle(DraftGenerationService $draftService, GoogleOAuthService $oauthService): void
    {
        $thread = $this->message->thread;

        if (DraftReply::where('email_message_id', $this->message->id)
            ->whereIn('status', ['pending', 'approved', 'sent'])
            ->exists()
        ) {
            Log::debug('GenerateDraftJob: draft already exists, skipping', [
                'email_message_id' => $this->message->id,
            ]);
            return;
        }

        $body = $draftService->generate($this->message, $thread->classification);

        $account = $thread->gmailAccount;
        $client  = $oauthService->getAuthenticatedClient($account);
        $service = new Gmail($client);

        $rawMessage = $this->buildRawMessage($body);

        $gmailMessage = new Message();
        $gmailMessage->setRaw($rawMessage);
        $gmailMessage->setThreadId($thread->gmail_thread_id);

        $draft = new Draft();
        $draft->setMessage($gmailMessage);

        $created = $service->users_drafts->create('me', $draft);

        DraftReply::create([
            'email_thread_id'  => $thread->id,
            'email_message_id' => $this->message->id,
            'body'             => $body,
            'gmail_draft_id'   => $created->getId(),
            'status'           => 'pending',
        ]);

        $thread->update(['status' => 'draft_created']);

        Log::info('GenerateDraftJob completed', [
            'email_message_id' => $this->message->id,
            'gmail_draft_id'   => $created->getId(),
        ]);
    }

    private function buildRawMessage(string $body): string
    {
        $thread  = $this->message->thread;
        $to      = $this->message->from_address;
        $subject = 'Re: ' . ($this->message->subject ?? '');

        $raw = implode("\r\n", [
            "To: {$to}",
            "Subject: {$subject}",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=utf-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
            $body,
        ]);

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateDraftJob failed', [
            'email_message_id' => $this->message->id,
            'error'            => $e->getMessage(),
        ]);
    }
}
