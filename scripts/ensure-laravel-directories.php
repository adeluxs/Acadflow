<?php

declare(strict_types=1);

/**
 * Create Laravel runtime directories before Composer invokes Artisan.
 *
 * ZIP tools and source-control systems can omit empty directories. Laravel's
 * package discovery requires bootstrap/cache to exist and be writable during
 * composer install, before the application can repair the directory itself.
 */
$root = dirname(__DIR__);
$directories = [
    'bootstrap/cache',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
];

foreach ($directories as $relativeDirectory) {
    $directory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        fwrite(STDERR, "Unable to create required Laravel directory: {$directory}" . PHP_EOL);
        exit(1);
    }

    if (! is_writable($directory)) {
        @chmod($directory, 0775);
    }

    if (! is_writable($directory)) {
        fwrite(STDERR, "Required Laravel directory is not writable: {$directory}" . PHP_EOL);
        exit(1);
    }
}
