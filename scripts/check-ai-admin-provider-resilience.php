<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];

$read = static function (string $path) use ($root): string {
    $contents = @file_get_contents($root.DIRECTORY_SEPARATOR.$path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }
    return $contents;
};

$external = $read('app/Ai/Providers/ExternalProvider.php');
$controller = $read('app/Http/Controllers/AiController.php');
$openRouter = $read('app/Ai/Providers/OpenRouterProvider.php');
$runtime = $read('app/Services/Ai/AiRuntimeConfigService.php');
$config = $read('config/ai.php');
$migration = $read('database/migrations/2026_09_16_183000_remove_obsolete_openrouter_model_catalog_setting.php');

$checks['health checks have a wall-clock budget'] = str_contains($external, 'health_check_budget_seconds')
    && str_contains($external, 'healthAttemptTimeout($healthDeadline)');
$checks['health transport has dedicated short timeouts'] = str_contains($external, 'health_check_timeout')
    && str_contains($external, 'health_check_connect_timeout');
$checks['admin test connection clamps diagnostics'] = str_contains($controller, "config['health_check_budget_seconds']")
    && str_contains($controller, "config['health_check_timeout']")
    && str_contains($controller, "config['health_check_connect_timeout']")
    && str_contains($controller, "config['retry_count'] = 0");
$checks['runtime propagates health timeout config'] = str_contains($runtime, "'health_check_budget_seconds'")
    && str_contains($runtime, "'health_check_timeout'")
    && str_contains($runtime, "'health_check_connect_timeout'");
$checks['bootstrap config defaults are below PHP 30s'] = str_contains($config, "AI_HEALTH_CHECK_BUDGET_SECONDS', 12")
    && str_contains($config, "AI_HEALTH_CHECK_TIMEOUT', 6")
    && str_contains($config, "AI_HEALTH_CHECK_CONNECT_TIMEOUT', 3");
$checks['OpenRouter discovery is bounded'] = str_contains($openRouter, '->connectTimeout(')
    && str_contains($openRouter, '->timeout(')
    && str_contains($openRouter, 'health_check_timeout');
$checks['full OpenRouter catalog is not persisted to settings'] = ! str_contains($controller, "SettingService::set('ai_provider_openrouter_model_catalog'")
    && str_contains($controller, "Cache::put('ai:provider-model-catalog:openrouter'");
$checks['obsolete oversized catalog setting is cleaned'] = str_contains($migration, "ai_provider_openrouter_model_catalog")
    && str_contains($migration, "->delete()");

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ').$label.PHP_EOL;
    if (! $passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, "AI admin provider resilience preflight failed: ".implode(', ', $failed).PHP_EOL);
    exit(1);
}

echo 'AI admin provider resilience preflight passed: '.count($checks).'/'.count($checks).PHP_EOL;
