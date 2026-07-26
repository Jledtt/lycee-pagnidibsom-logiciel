<?php

return [
    'mail_enabled' => env('LPP_MAIL_NOTIFICATIONS_ENABLED', true),

    'quota' => [
        'daily' => (int) env('RESEND_DAILY_LIMIT', 100),
        'monthly' => (int) env('RESEND_MONTHLY_LIMIT', 3000),
        'daily_reserve' => (int) env('RESEND_DAILY_RESERVE', 5),
    ],

    'blocked_recipient_domains' => [
        'example.com',
        'example.org',
        'example.net',
        'invalid',
        'local',
        'localhost',
        'test',
    ],
];
