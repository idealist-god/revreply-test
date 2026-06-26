<?php

namespace App\Services;

use App\Models\ClassificationLog;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use OpenAI\Client;
use Illuminate\Support\Facades\Log;

class ClassificationService
{
    private string $model;

    public function __construct(private Client $openai)
    {
        $this->model = config('services.llm.model', 'gpt-4o-mini');
    }

    // Cost per token in USD (gpt-4o-mini)
    private const COST_PER_INPUT_TOKEN  = 0.00000015;
    private const COST_PER_OUTPUT_TOKEN = 0.0000006;

    private const SYSTEM_PROMPT = <<<PROMPT
You are an email classifier for a sales team. Classify the email into exactly one of these categories:

- interested: The sender expresses genuine interest in the product or service, asks follow-up questions, or requests more information.
- not_interested: The sender explicitly declines, opts out, or shows no interest.
- meeting_request: The sender wants to schedule a call, demo, or meeting.
- unclear: The email does not clearly fit any of the above categories.

Respond with valid JSON only. No explanation outside the JSON.
Format: {"classification": "<category>", "confidence": <0.0-1.0>, "reason": "<one sentence>"}
PROMPT;

    public function classify(EmailMessage $message): string
    {
        if (config('services.llm.stub')) {
            return $this->classifyStub($message);
        }

        $bodyText = $this->prepareBody($message->body_text);

        $response = $this->openai->chat()->create([
            'model'    => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user',   'content' => "Classify this email:\n\n{$bodyText}"],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature'     => 0.1,
        ]);

        $raw    = $response->choices[0]->message->content;
        $parsed = json_decode($raw, true);

        $classification = $parsed['classification'] ?? 'unclear';
        $confidence     = $parsed['confidence']     ?? null;

        $promptTokens     = $response->usage->promptTokens;
        $completionTokens = $response->usage->completionTokens;

        ClassificationLog::create([
            'email_message_id'  => $message->id,
            'classification'    => $classification,
            'confidence'        => $confidence,
            'llm_provider'      => 'openai',
            'model'             => $this->model,
            'prompt_tokens'     => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost_usd'          => ($promptTokens * self::COST_PER_INPUT_TOKEN)
                                 + ($completionTokens * self::COST_PER_OUTPUT_TOKEN),
            'raw_response'      => $parsed,
        ]);

        $message->update(['processed_at' => now()]);

        $message->thread->update(['classification' => $classification]);

        return $classification;
    }

    private function classifyStub(EmailMessage $message): string
    {
        $classification = 'interested';

        ClassificationLog::create([
            'email_message_id'  => $message->id,
            'classification'    => $classification,
            'confidence'        => 0.95,
            'llm_provider'      => 'stub',
            'model'             => 'stub',
            'prompt_tokens'     => 0,
            'completion_tokens' => 0,
            'cost_usd'          => 0,
            'raw_response'      => ['stub' => true],
        ]);

        $message->update(['processed_at' => now()]);

        $message->thread->update(['classification' => $classification]);

        Log::debug('ClassificationService stub used', ['email_message_id' => $message->id]);

        return $classification;
    }

    private function prepareBody(string $bodyText): string
    {
        // Trim whitespace, collapse blank lines, cap at 3000 chars to control token cost
        $text = preg_replace('/\n{3,}/', "\n\n", trim($bodyText));

        return mb_substr($text, 0, 3000);
    }
}
