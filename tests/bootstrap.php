<?php

/**
 * PHPUnit bootstrap for AcadSyntra.
 *
 * Production optimization caches (especially Laravel's compiled route cache)
 * can be large enough to push a 128 MB PHPUnit process over its memory limit.
 * Tests must also never inherit cached production configuration because that
 * can make APP_ENV/testing settings ineffective.
 *
 * Point framework caches at test-only, non-persistent files before Laravel or
 * Composer boots. This preserves production caches on disk while keeping test
 * application boots isolated and lightweight.
 */
$testingCachePaths = [
    'APP_CONFIG_CACHE' => 'bootstrap/cache/config-testing.php',
    'APP_EVENTS_CACHE' => 'bootstrap/cache/events-testing.php',
    'APP_ROUTES_CACHE' => 'bootstrap/cache/routes-testing.php',
];

foreach ($testingCachePaths as $key => $relativePath) {
    putenv($key.'='.$relativePath);
    $_ENV[$key] = $relativePath;
    $_SERVER[$key] = $relativePath;

    $absolutePath = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

require dirname(__DIR__).'/vendor/autoload.php';
