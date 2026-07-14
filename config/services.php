<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'receipt_model' => env('OPENAI_RECEIPT_MODEL', 'gpt-4o'),
        'receipt_image_detail' => env('OPENAI_RECEIPT_IMAGE_DETAIL', 'high'),
        'receipt_timeout' => env('OPENAI_RECEIPT_TIMEOUT', 60),
        'csv_model' => env('OPENAI_CSV_MODEL', env('OPENAI_RECEIPT_MODEL', 'gpt-4o')),
        'csv_timeout' => env('OPENAI_CSV_TIMEOUT', env('OPENAI_RECEIPT_TIMEOUT', 60)),
        'http_verify' => filter_var(env('OPENAI_HTTP_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
    ],

];
