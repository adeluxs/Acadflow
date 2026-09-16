<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'AiPromptService excludes volatile deadline from prompt context' => [
        'file' => 'app/Services/Ai/AiPromptService.php',
        'needles' => ["\$payload['_ai_deadline_at']"],
    ],
    'AiManager analyze supports named requestId' => [
        'file' => 'app/Ai/AiManager.php',
        'needles' => ['?string $requestId = null', "\$payload['_ai_request_id'] = trim(\$requestId)"],
    ],
    'AI assistant endpoint preserves five-argument service contract' => [
        'file' => 'app/Http/Controllers/AiController.php',
        'needles' => [
            "\$requestId = UserFacingError::requestId(\$request)",
            "\$startedAt = microtime(true)",
            "\$this->assistant->ask(\$user, \$data['message'], \$courseId, \$requestId, \$startedAt)",
        ],
    ],
    'Academic assistant service accepts correlation and lifecycle arguments' => [
        'file' => 'app/Services/Ai/AcademicAssistantService.php',
        'needles' => [
            "?int \$courseId = null, ?string \$requestId = null, ?float \$startedAt = null",
            "requestId: \$requestId",
        ],
    ],
    'AI provider success exposes pending billing settlement metadata on failure' => [
        'file' => 'app/Ai/AiManager.php',
        'needles' => ["'billing_settlement_pending' => true", "'billing_status' => 'reconciliation_required'"],
    ],
    'Monetization view guards validation errors when middleware is disabled' => [
        'file' => 'resources/views/admin/monetization/index.blade.php',
        'needles' => [
            '@if(isset($errors) && $errors->any())',
            "@if(isset(\$errors) && \$errors->has('target'))",
            "@if(isset(\$errors) && \$errors->has('amount'))",
        ],
    ],
];

$failed = false;
foreach ($checks as $label => $check) {
    $path = $root.'/'.$check['file'];
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    $ok = $contents !== '';
    foreach ($check['needles'] as $needle) {
        $ok = $ok && str_contains($contents, $needle);
    }
    echo ($ok ? 'PASS' : 'FAIL').': '.$label.PHP_EOL;
    $failed = $failed || ! $ok;
}

if ($failed) {
    exit(1);
}

echo PHP_EOL.'Pasted PHPUnit regression preflight: PASS'.PHP_EOL;
