<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$must = static function (string $file, array $needles, string $label) use ($root, &$errors): void {
    $path = $root.'/'.$file;
    if (! is_file($path)) {
        $errors[] = "Missing {$file}.";
        return;
    }

    $text = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (! str_contains($text, $needle)) {
            $errors[] = "{$label}: missing {$needle} in {$file}.";
        }
    }
};

$must('app/Services/Scholarly/ScholarlyDiscoveryService.php', [
    'Http::pool(',
    'Cache::get($cacheKey)',
    'Cache::put($cacheKey, $discovered',
    "DB::table('scholarly_records')->upsert",
    'array_chunk($rows, 25)',
    "'provider', 'external_identifier'",
], 'Bounded scholarly discovery');

$must('app/Services/Scholarly/HttpScholarlyProvider.php', [
    'enqueueSearch(Pool $pool',
    'parsePooledSearch(',
    'connectTimeout($this->connectTimeout())',
    'timeout($this->timeout())',
    'normalizePubmedSearch(',
], 'Concurrent scholarly providers');

$must('config/scholarly.php', [
    "'timeout' => (int) env('SCHOLARLY_SEARCH_TIMEOUT', 5)",
    "'connect_timeout' => (int) env('SCHOLARLY_CONNECT_TIMEOUT', 2)",
    "'cache_seconds' => (int) env('SCHOLARLY_SEARCH_CACHE_SECONDS', 300)",
], 'Scholarly timeout configuration');

$must('resources/js/global-loader.js', [
    'window.AcadFlowLoader',
    'installFetchLoader()',
    'installAxiosLoader()',
    'installNavigationLoader()',
], 'Global loader runtime');

$must('resources/views/partials/global-loader.blade.php', [
    'acadflow-global-loader',
    "branding/acadflow-mark.png",
    'data-acadflow-loader-label',
], 'Global loader markup');

$must('resources/js/app.js', [
    "import { initGlobalLoader } from './global-loader';",
    'initGlobalLoader();',
], 'Global loader bootstrap');

foreach ([
    'public/branding/acadflow-logo.png',
    'public/branding/acadflow-mark.png',
    'public/branding/acadflow-icon-192.png',
    'public/branding/acadflow-icon-512.png',
] as $asset) {
    if (! is_file($root.'/'.$asset) || filesize($root.'/'.$asset) < 1000) {
        $errors[] = "Brand asset missing or invalid: {$asset}.";
    }
}

foreach ([
    'resources/views/layouts/app.blade.php',
    'resources/views/layouts/auth.blade.php',
    'resources/views/layouts/onboarding.blade.php',
    'resources/views/landing.blade.php',
] as $layout) {
    $must($layout, ["partials.global-loader", "branding/acadflow-mark.png"], 'Shared branding');
}

if ($errors !== []) {
    fwrite(STDERR, "Scholarly timeout + branding preflight FAILED:\n - ".implode("\n - ", array_unique($errors))."\n");
    exit(1);
}

echo "Scholarly timeout + branding preflight passed (concurrent bounded search, batched persistence, global branded loader).\n";
