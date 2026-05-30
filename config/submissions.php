<?php

// config/submissions.php
// Configuration for the submission system

return [
    // Billing & Payment
    'billing' => [
        'grace_period_days' => env('SUBMISSION_GRACE_PERIOD', 7),
        'require_payment_before_submission' => env('REQUIRE_PAYMENT', true),
    ],

    // File Upload Limits
    'uploads' => [
        'default_max_file_size_mb' => 50,
        'default_max_file_count' => 10,
        'default_max_total_size_mb' => 500,
        'allowed_mime_types' => [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'application/zip' => 'zip',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ],
    ],

    // Late Submission Policy
    'late_submissions' => [
        'default_allow_late' => true,
        'default_penalty_percent' => 10, // 10% deduction per assignment config
        'max_penalty_percent' => 50,
    ],

    // Task Defaults
    'tasks' => [
        'default_submission_format' => 'file', // 'file', 'text', or 'both'
        'default_max_resubmissions' => null, // null = unlimited
        'default_group_size' => [
            'min' => 1,
            'max' => 6,
        ],
    ],

    // Deadlines
    'deadlines' => [
        'show_deadline_warning_days' => 3,
        'show_deadline_urgent_hours' => 24,
    ],

    // Notifications
    'notifications' => [
        'notify_deadline_approaching' => true,
        'notify_submission_received' => true,
        'notify_grade_posted' => true,
        'notify_correction_requested' => true,
    ],

    // Storage
    'storage' => [
        'disk' => 'local',
        'path' => 'submissions',
    ],
];
