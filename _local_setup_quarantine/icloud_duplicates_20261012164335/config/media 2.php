<?php

return [
    'scanner' => env('MEDIA_MALWARE_SCANNER', 'none'),
    'scan_fail_closed' => (bool) env('MEDIA_SCAN_FAIL_CLOSED', true),
    'download_token_minutes' => (int) env('MEDIA_DOWNLOAD_TOKEN_MINUTES', 15),
    'preview_token_minutes' => (int) env('MEDIA_PREVIEW_TOKEN_MINUTES', 5),
    'clamav' => [
        'binary' => env('CLAMAV_BINARY', 'clamscan'),
        'timeout' => (float) env('CLAMAV_TIMEOUT', 30),
    ],
];
