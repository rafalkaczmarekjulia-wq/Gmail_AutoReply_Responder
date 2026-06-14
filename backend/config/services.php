<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'google' => [
        'client_id' => trim((string) env('GOOGLE_CLIENT_ID', '')),
        'client_secret' => trim((string) env('GOOGLE_CLIENT_SECRET', '')),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'pubsub_topic' => env('GOOGLE_PUBSUB_TOPIC'),
    ],

    'llm' => [
        'driver' => env('LLM_DRIVER', 'stub'),
        'openai_key' => env('OPENAI_API_KEY'),
        'openai_model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

];
