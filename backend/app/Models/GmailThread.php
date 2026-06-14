<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class GmailThread extends Model
{
    protected $fillable = [
        'gmail_account_id',
        'gmail_thread_id',
        'subject',
        'snippet',
        'last_message_at',
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
        return $this->hasMany(GmailMessage::class);
    }
}
