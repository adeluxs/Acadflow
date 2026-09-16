<?php

$root = dirname(__DIR__);
$errors = [];

$bootstrap = file_get_contents($root.'/tests/bootstrap.php') ?: '';
$phpunit = file_get_contents($root.'/phpunit.xml') ?: '';

$required = ['APP_CONFIG_CACHE', 'APP_EVENTS_CACHE', 'APP_ROUTES_CACHE'];
foreach ($required as $key) {
    if (! str_contains($bootstrap, $key)) {
        $errors[] = "tests/bootstrap.php does not isolate {$key}.";
    }
    if (! str_contains($phpunit, $key)) {
        $errors[] = "phpunit.xml does not define {$key} for the testing process.";
    }
}

if (! str_contains($phpunit, 'bootstrap="tests/bootstrap.php"')) {
    $errors[] = 'phpunit.xml is not using the test cache-isolation bootstrap.';
}

if (str_contains($bootstrap, "routes-v7.php") || str_contains($bootstrap, "config.php'")) {
    $errors[] = 'Test bootstrap must not delete or load production cache artifacts directly.';
}

if ($errors !== []) {
    fwrite(STDERR, "Test cache-isolation regression check failed:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

echo "Test cache-isolation regression check passed.\n";
