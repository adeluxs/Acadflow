<?php

return [
    'webpush' => [
        'vapid_public_key' => env('VAPID_PUBLIC_KEY', ''),
        'vapid_private_key' => env('VAPID_PRIVATE_KEY', ''),
        'vapid_subject' => env('VAPID_SUBJECT', ''),
        'timeout' => 8,
        'connect_timeout' => 3,
        'allowed_hosts' => ['fcm.googleapis.com', 'android.googleapis.com', '*.push.services.mozilla.com', '*.push.apple.com', '*.notify.windows.com'],
    ],

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

    'sms' => [
        'base_url' => env('SMS_BASE_URL', 'https://api.sms-provider.com/v1'),
        'sender_id' => env('SMS_SENDER_ID', 'AcadSyntra'),
        'api_key' => env('SMS_API_KEY', ''),
    ],

];
