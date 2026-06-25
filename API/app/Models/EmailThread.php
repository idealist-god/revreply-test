<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmailThread extends Model
{
    protected $fillable = [
        'gmail_account_id',
        'gmail_thread_id',
        'subject',
        'snippet',
        'from_address',
        'last_message_at',
        'classification',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function gmailAccount(): BelongsTo
    {
        return $this->belongsTo(GmailAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function draftReplies(): HasMany
    {
        return $this->hasMany(DraftReply::class);
    }

    public function latestDraft(): HasOne
    {
        return $this->hasOne(DraftReply::class)->latestOfMany();
    }
}
