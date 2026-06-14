<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class GmailMessage extends Model
{
    protected $fillable = [
        'gmail_account_id',
        'gmail_thread_id',
        'gmail_message_id',
        'gmail_thread_id_str',
        'from_email',
        'subject',
        'body_text',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function gmailAccount(): BelongsTo
    {
        return $this->belongsTo(GmailAccount::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(GmailThread::class, 'gmail_thread_id');
    }

    public function classification(): HasOne
    {
        return $this->hasOne(Classification::class);
    }

    public function draftReply(): HasOne
    {
        return $this->hasOne(DraftReply::class);
    }
}
