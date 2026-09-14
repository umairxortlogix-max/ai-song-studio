<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ai' => [
        'huggingface' => [
            'key' => env('AI_PROVIDER_A_KEY'),
            'url' => env('AI_PROVIDER_A_URL', 'https://api-inference.huggingface.co'),
            'model' => env('AI_PROVIDER_A_MODEL', 'facebook/musicgen-small'),
        ],
        'replicate' => [
            'key' => env('AI_PROVIDER_B_KEY'),
            'url' => env('AI_PROVIDER_B_URL', 'https://api.replicate.com/v1'),
            'model' => env('AI_PROVIDER_B_MODEL', 'meta/musicgen'),
        ],
        'stability' => [
            'key' => env('AI_PROVIDER_C_KEY'),
            'url' => env('AI_PROVIDER_C_URL', 'https://api.stability.ai/v2beta'),
            'model' => env('AI_PROVIDER_C_MODEL', 'stable-audio-2'),
        ],
        'local' => [
            'key' => null,
            'url' => env('AI_LOCAL_MODEL_URL', 'http://127.0.0.1:8800'),
            'model' => env('AI_LOCAL_MODEL_NAME', 'local-open-source'),
        ],
    ],
];
