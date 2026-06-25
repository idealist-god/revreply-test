<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Services\ClassificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ClassifyEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public EmailMessage $message) {}

    public function handle(ClassificationService $classificationService): void
    {
        $classification = $classificationService->classify($this->message);

        if (in_array($classification, ['interested', 'meeting_request'])) {
            GenerateDraftJob::dispatch($this->message)->onQueue('draft');
        }

        Log::info('ClassifyEmailJob completed', [
            'email_message_id' => $this->message->id,
            'classification'   => $classification,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ClassifyEmailJob failed', [
            'email_message_id' => $this->message->id,
            'error'            => $e->getMessage(),
        ]);
    }
}
