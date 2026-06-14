<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ProcessedNotification extends Model
{
    protected $fillable = [
        'gmail_account_id',
        'history_id',
    ];

    public function gmailAccount(): BelongsTo
    {
        return $this->belongsTo(GmailAccount::class);
    }
}
