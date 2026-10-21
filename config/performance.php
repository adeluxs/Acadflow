<?php

return [
    'queued_export_bytes' => (int) env('PERFORMANCE_QUEUED_EXPORT_BYTES', 250000),
    'export_disk' => env('RESEARCH_EXPORT_DISK', 'local'),
    // Enable on staging while profiling. Values contain durations only.
    'server_timing' => (bool) env('PERFORMANCE_SERVER_TIMING', false),
    'log_slow_requests' => (bool) env('PERFORMANCE_LOG_SLOW_REQUESTS', false),
    'slow_request_ms' => (int) env('PERFORMANCE_SLOW_REQUEST_MS', 1000),
];
