<?php

namespace App\Services;

use App\Models\EmailMessage;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;

class DraftGenerationService
{
    private string $model;

    public function __construct(private Client $openai)
    {
        $this->model = config('services.llm.model', 'gpt-4o-mini');
    }

    private const SYSTEM_PROMPT = <<<PROMPT
You are a professional sales assistant writing email replies on behalf of a sales representative.
Write only the body of the reply — no subject line, no greeting, no sign-off.
Keep the reply under 150 words.
Sound natural and human, not like a template or automated response.
Match the tone to the classification context provided.
PROMPT;

    public function generate(EmailMessage $message, string $classification): string
    {
        if (config('services.llm.stub')) {
            return $this->generateStub($classification);
        }

        try {
            $response = $this->openai->chat()->create([
                'model'       => $this->model,
                'messages'    => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => $this->buildPrompt($message, $classification)],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 300,
            ]);

            return trim($response->choices[0]->message->content);

        } catch (\Throwable $e) {
            Log::warning('DraftGenerationService: OpenAI call failed, using fallback', [
                'email_message_id' => $message->id,
                'error'            => $e->getMessage(),
            ]);

            return $this->generateStub($classification);
        }
    }

    private function buildPrompt(EmailMessage $message, string $classification): string
    {
        $body = mb_substr(trim($message->body_text ?? ''), 0, 2000);

        return <<<PROMPT
Classification: {$classification}

Original email:
{$body}

Write a reply appropriate for a {$classification} email.
PROMPT;
    }

    private function generateStub(string $classification): string
    {
        return match ($classification) {
            'interested'      => "Thank you for your interest! I'd love to tell you more about what we offer. Would you have 15 minutes for a quick call this week?",
            'meeting_request' => "Absolutely, I'd be happy to connect. I have availability Tuesday and Thursday afternoon — do either of those work for you?",
            default           => "Thank you for reaching out. I'd be happy to help — could you share a bit more about what you're looking for?",
        };
    }
}
