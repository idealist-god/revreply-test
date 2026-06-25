<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GmailAccount extends Model
{
    protected $fillable = [
        'user_id',
        'gmail_address',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'watch_history_id',
        'watch_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
            'watch_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailThreads(): HasMany
    {
        return $this->hasMany(EmailThread::class);
    }
}
