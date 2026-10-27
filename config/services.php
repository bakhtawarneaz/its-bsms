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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sms_gateway' => [
        'url'      => env('SMS_GATEWAY_URL'),
        'username' => env('SMS_GATEWAY_USERNAME'),
        'password' => env('SMS_GATEWAY_PASSWORD'),
        'sender'   => env('SMS_GATEWAY_SENDER'),
        'timeout'  => (int) env('SMS_GATEWAY_TIMEOUT', 15),
    ],

    'otp' => [
        // "log" writes codes to the auth log (local only); "live" sends real SMS/email
        'delivery' => env('OTP_DELIVERY', 'log'),
    ],

    'mnp' => [
        'url'  => env('MNP_API_URL', 'https://mnpapi.its.com.pk/api/fetch'),
        'key'  => env('MNP_API_KEY'),
        'fake' => (bool) env('MNP_API_FAKE', false), // local: network from number prefix, no HTTP call
    ],

];
