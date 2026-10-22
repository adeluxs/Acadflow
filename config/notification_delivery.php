<?php

return [
    'queue' => 'notifications',
    'connection' => env('NOTIFICATION_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
    'provider_webhook_secret' => env('NOTIFICATION_PROVIDER_WEBHOOK_SECRET'),
    'onboarding_interval_hours' => 12,
    'onboarding_max_reminders' => 6,
    'daily_email_limit' => 10,
    'daily_push_limit' => 20,
    'retention_days' => 180,
    'failure_alert_percent' => 20,
    'failure_alert_minimum' => 10,
    'summary_reports_enabled' => false,
    'engagement_enabled' => true,
    'categories' => [
        'academic' => 'Academic alerts', 'organisation' => 'Departments & institutions',
        'research' => 'Research updates', 'onboarding' => 'Onboarding reminders',
        'engagement' => 'Optional engagement', 'billing' => 'Billing & access',
    ],
];
