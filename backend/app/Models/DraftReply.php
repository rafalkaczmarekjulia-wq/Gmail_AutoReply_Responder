<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DraftReply extends Model
{
    public const STATUS_PENDING = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_SENT = 'sent';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'gmail_message_id',
        'gmail_draft_id',
        'body',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function gmailMessage(): BelongsTo
    {
        return $this->belongsTo(GmailMessage::class);
    }
}
