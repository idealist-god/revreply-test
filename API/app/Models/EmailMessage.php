<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMessage extends Model
{
    protected $fillable = [
        'email_thread_id',
        'gmail_message_id',
        'from_address',
        'to_address',
        'subject',
        'body_text',
        'body_html',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at'  => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'email_thread_id');
    }

    public function draftReplies(): HasMany
    {
        return $this->hasMany(DraftReply::class);
    }
}
