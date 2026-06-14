<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Classification extends Model
{
    public const LABEL_INTERESTED = 'interested';
    public const LABEL_NOT_INTERESTED = 'not_interested';
    public const LABEL_MEETING_REQUEST = 'meeting_request';
    public const LABEL_UNCLEAR = 'unclear';

    public const DRAFTABLE_LABELS = [
        self::LABEL_INTERESTED,
        self::LABEL_MEETING_REQUEST,
        self::LABEL_UNCLEAR,
    ];

    protected $fillable = [
        'gmail_message_id',
        'label',
        'extracted_keywords',
        'confidence',
        'model',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'extracted_keywords' => 'array',
            'raw_response' => 'array',
        ];
    }

    public function gmailMessage(): BelongsTo
    {
        return $this->belongsTo(GmailMessage::class);
    }
}
