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
        'default_max_file_size_mb' => (int) env('SUBMISSION_MAX_FILE_SIZE_MB', 50),
        'default_max_file_count' => (int) env('SUBMISSION_MAX_FILE_COUNT', 10),
        'default_max_total_size_mb' => (int) env('SUBMISSION_MAX_TOTAL_SIZE_MB', 500),
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
        'default_allow_late' => (bool) env('SUBMISSION_ALLOW_LATE', true),
        'default_penalty_percent' => (float) env('SUBMISSION_LATE_PENALTY_PERCENT', 10), // 10% deduction per assignment config
        'max_penalty_percent' => (float) env('SUBMISSION_MAX_LATE_PENALTY_PERCENT', 50),
    ],

    // Task Defaults
    'tasks' => [
        'default_submission_format' => env('SUBMISSION_DEFAULT_FORMAT', 'file'), // 'file', 'text', or 'both'
        'default_max_resubmissions' => env('SUBMISSION_MAX_RESUBMISSIONS'), // null = unlimited
        'default_group_size' => [
            'min' => (int) env('SUBMISSION_GROUP_MIN_SIZE', 1),
            'max' => (int) env('SUBMISSION_GROUP_MAX_SIZE', 6),
        ],
    ],

    // Deadlines
    'deadlines' => [
        'show_deadline_warning_days' => (int) env('SUBMISSION_DEADLINE_WARNING_DAYS', 3),
        'show_deadline_urgent_hours' => (int) env('SUBMISSION_DEADLINE_URGENT_HOURS', 24),
    ],

    // Notifications
    'notifications' => [
        'notify_deadline_approaching' => (bool) env('SUBMISSION_NOTIFY_DEADLINE_APPROACHING', true),
        'notify_submission_received' => (bool) env('SUBMISSION_NOTIFY_RECEIVED', true),
        'notify_grade_posted' => (bool) env('SUBMISSION_NOTIFY_GRADE_POSTED', true),
        'notify_correction_requested' => (bool) env('SUBMISSION_NOTIFY_CORRECTION_REQUESTED', true),
    ],

    // Storage
    'storage' => [
        'disk' => env('SUBMISSION_STORAGE_DISK', 'local'),
        'path' => env('SUBMISSION_STORAGE_PATH', 'submissions'),
    ],
];
