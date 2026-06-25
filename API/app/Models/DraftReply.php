<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftReply extends Model
{
    protected $fillable = [
        'email_thread_id',
        'email_message_id',
        'body',
        'gmail_draft_id',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'email_thread_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class, 'email_message_id');
    }
}
