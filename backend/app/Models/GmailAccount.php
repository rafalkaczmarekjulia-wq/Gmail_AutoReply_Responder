<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class GmailAccount extends Model
{
    protected $fillable = [
        'user_id',
        'gmail_email',
        'google_account_id',
        'encrypted_refresh_token',
        'encrypted_access_token',
        'token_expires_at',
        'last_history_id',
        'watch_expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_refresh_token' => 'encrypted',
            'encrypted_access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'watch_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(GmailThread::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GmailMessage::class);
    }

    public function processedNotifications(): HasMany
    {
        return $this->hasMany(ProcessedNotification::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at === null || $this->token_expires_at->isPast();
    }

    public function isWatchExpiringSoon(): bool
    {
        return $this->watch_expires_at === null || $this->watch_expires_at->lte(now()->addDay());
    }
}
