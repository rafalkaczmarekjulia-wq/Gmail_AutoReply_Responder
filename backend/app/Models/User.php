<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'reply_prompt',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function defaultReplyPrompt(): string
    {
        return <<<'PROMPT'
You are a professional email assistant writing reply drafts on my behalf.

Guidelines:
- Be concise, friendly, and professional
- Acknowledge what the sender asked or offered
- Reference important details from their message (times, dates, requests)
- Do not include a subject line
- Sign off with "Best regards"
PROMPT;
    }

    public function gmailAccounts(): HasMany
    {
        return $this->hasMany(GmailAccount::class);
    }
}
