<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LlmService
{
    public function classify(string $subject, string $body): array
    {
        if (config('services.llm.driver') === 'openai' && filled(config('services.llm.openai_key'))) {
            try {
                return $this->classifyWithOpenAi($subject, $body);
            } catch (\Throwable $e) {
                Log::warning('OpenAI classify failed, using stub', ['error' => $e->getMessage()]);
            }
        }

        return $this->classifyWithStub($subject, $body);
    }

    public function generateDraft(
        string $subject,
        string $body,
        string $label,
        array $keywords = [],
        ?string $userReplyPrompt = null
    ): string {
        if (config('services.llm.driver') === 'openai' && filled(config('services.llm.openai_key'))) {
            try {
                return $this->generateDraftWithOpenAi($subject, $body, $label, $keywords, $userReplyPrompt);
            } catch (\Throwable $e) {
                Log::warning('OpenAI draft failed, using stub', ['error' => $e->getMessage()]);
            }
        }

        return $this->generateDraftWithStub($subject, $body, $label, $keywords, $userReplyPrompt);
    }

    private function classifyWithStub(string $subject, string $body): array
    {
        $text = strtolower($subject.' '.$body);

        $label = 'unclear';
        $confidence = 0.6;
        $keywords = $this->extractKeywordsWithStub($text);

        if (Str::contains($text, ['meeting', 'meet', 'schedule', 'call', 'zoom', 'calendar', 'time slot', 'time slots', 'availability'])) {
            $label = 'meeting_request';
            $confidence = 0.85;
        } elseif (Str::contains($text, ['interested', 'pricing', 'demo', 'learn more', 'introduce yourself', 'introduce'])) {
            $label = 'interested';
            $confidence = 0.9;
        } elseif (Str::contains($text, ['unsubscribe', 'not interested', 'remove me'])) {
            $label = 'not_interested';
            $confidence = 0.88;
        }

        return [
            'label' => $label,
            'keywords' => $keywords,
            'confidence' => $confidence,
            'model' => 'stub',
            'raw_response' => ['source' => 'keyword_stub', 'keywords' => $keywords],
        ];
    }

    private function extractKeywordsWithStub(string $text): array
    {
        $patterns = [
            'meeting', 'meet', 'call', 'zoom', 'schedule', 'calendar', 'time slot', 'time slots',
            'interested', 'pricing', 'demo', 'introduce',
            'unsubscribe', 'pm', 'am', 'cet', 'utc', 'est', 'pst',
        ];

        $found = [];
        foreach ($patterns as $word) {
            if (Str::contains($text, $word)) {
                $found[] = $word;
            }
        }

        if (preg_match_all('/\b\d{1,2}(?::\d{2})?\s*(?:am|pm)\b/i', $text, $times)) {
            foreach ($times[0] as $time) {
                $found[] = strtolower(trim($time));
            }
        }

        if (preg_match_all('/\b\d{1,2}\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/i', $text, $dates)) {
            foreach ($dates[0] as $date) {
                $found[] = strtolower(trim($date));
            }
        }

        return array_values(array_unique($found));
    }

    private function generateDraftWithStub(
        string $subject,
        string $body,
        string $label,
        array $keywords,
        ?string $userReplyPrompt
    ): string {
        $keywordHint = $keywords !== [] ? ' I noted: '.implode(', ', $keywords).'.' : '';

        return match ($label) {
            'meeting_request' => "Hi,\n\nThanks for reaching out. I'd be happy to share my availability.{$keywordHint} Please let me know which times work best for you.\n\nBest regards",
            'interested' => "Hi,\n\nThank you for your interest.{$keywordHint} I'd love to share more details. What specific questions can I answer for you?\n\nBest regards",
            'not_interested' => "Hi,\n\nThank you for letting me know.\n\nBest regards",
            default => "Hi,\n\nThanks for your email.{$keywordHint} I'll review this and get back to you shortly.\n\nBest regards",
        };
    }

    private function classifyWithOpenAi(string $subject, string $body): array
    {
        $response = Http::withToken(config('services.llm.openai_key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.llm.openai_model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Classify the inbound email. Labels: interested, not_interested, meeting_request, unclear. '
                            .'Extract 3-8 important keywords or phrases from the email (times, dates, intent, names). '
                            .'Respond with JSON only: {"label":"...","confidence":0.0,"keywords":["..."]}',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Subject: {$subject}\n\nBody:\n{$body}",
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
            ])
            ->throw()
            ->json();

        $content = json_decode($response['choices'][0]['message']['content'] ?? '{}', true);
        $keywords = array_values(array_filter((array) ($content['keywords'] ?? [])));

        return [
            'label' => $content['label'] ?? 'unclear',
            'keywords' => $keywords,
            'confidence' => (float) ($content['confidence'] ?? 0.5),
            'model' => config('services.llm.openai_model'),
            'raw_response' => $response,
        ];
    }

    private function generateDraftWithOpenAi(
        string $subject,
        string $body,
        string $label,
        array $keywords,
        ?string $userReplyPrompt
    ): string {
        $systemPrompt = trim($userReplyPrompt ?? '') !== ''
            ? $userReplyPrompt
            : 'Write a concise professional email reply draft. Do not include a subject line.';

        $keywordLine = $keywords !== [] ? implode(', ', $keywords) : 'none';

        $response = Http::withToken(config('services.llm.openai_key'))
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.llm.openai_model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    [
                        'role' => 'user',
                        'content' => "Classification: {$label}\nExtracted keywords: {$keywordLine}\nSubject: {$subject}\n\nOriginal email:\n{$body}",
                    ],
                ],
            ])
            ->throw()
            ->json();

        return trim($response['choices'][0]['message']['content'] ?? '');
    }
}
