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
        'webhook_secret' => env('RESEND_WEBHOOK_SECRET'),
    ],

    'timetable_solver' => [
        'python' => env('TIMETABLE_SOLVER_PYTHON') ?: (PHP_OS_FAMILY === 'Windows'
            ? base_path('.venv-timetable/Scripts/python.exe')
            : base_path('.venv-timetable/bin/python')),
        'script' => env('TIMETABLE_SOLVER_SCRIPT') ?: base_path('scripts/timetable_solver.py'),
        'time_limit_seconds' => (int) env('TIMETABLE_SOLVER_TIME_LIMIT', 12),
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

];
