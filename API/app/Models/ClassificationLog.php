<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassificationLog extends Model
{
    protected $fillable = [
        'email_message_id',
        'classification',
        'confidence',
        'llm_provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'cost_usd',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'confidence'        => 'float',
            'prompt_tokens'     => 'integer',
            'completion_tokens' => 'integer',
            'cost_usd'          => 'decimal:6',
            'raw_response'      => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
