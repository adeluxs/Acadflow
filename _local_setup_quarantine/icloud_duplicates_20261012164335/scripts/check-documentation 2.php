<?php

declare(strict_types=1);

$root = dirname(__DIR__);

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed { return $default; }
}

$required = [
    'README.md',
    'CHANGELOG.md',
    'CONTRIBUTING.md',
    'docs/README.md',
    'docs/DEVELOPER_GUIDE.md',
    'docs/USER_GUIDE.md',
    'docs/DOCUMENTATION_MAINTENANCE.md',
    'docs/OPERATIONS_REDIS_QUEUE_AND_SHARED_HOSTING.md',
];

$errors = [];
foreach ($required as $file) {
    if (! is_file($root.DIRECTORY_SEPARATOR.$file)) {
        $errors[] = "Missing canonical documentation file: {$file}";
    }
}

$developer = @file_get_contents($root.'/docs/DEVELOPER_GUIDE.md') ?: '';
$userGuide = @file_get_contents($root.'/docs/USER_GUIDE.md') ?: '';
$changelog = @file_get_contents($root.'/CHANGELOG.md') ?: '';
$readme = @file_get_contents($root.'/README.md') ?: '';

$featureConfig = require $root.'/config/features.php';
foreach (array_keys((array) ($featureConfig['definitions'] ?? [])) as $feature) {
    if (! str_contains($developer, "`{$feature}`")) {
        $errors[] = "Developer Guide does not mention registered feature key: {$feature}";
    }
}

$aiConfig = require $root.'/config/ai.php';
foreach ((array) ($aiConfig['features'] ?? []) as $feature) {
    if (! str_contains($developer, "`{$feature}`") && ! str_contains($developer, (string) $feature)) {
        $errors[] = "Developer Guide does not mention AI feature key: {$feature}";
    }
    if (! str_contains($userGuide, (string) $feature)) {
        $errors[] = "User Guide does not mention AI feature key: {$feature}";
    }
}

$roleSource = @file_get_contents($root.'/app/Enums/UserRole.php') ?: '';
preg_match_all("/case\\s+[A-Z_]+\\s*=\\s*'([^']+)'/", $roleSource, $matches);
foreach ($matches[1] ?? [] as $role) {
    if (! str_contains($userGuide, "`{$role}`")) {
        $errors[] = "User Guide does not mention role key: {$role}";
    }
}

if (! str_contains($changelog, '## [Unreleased]')) {
    $errors[] = 'CHANGELOG.md must contain an [Unreleased] section.';
}
if (! str_contains($readme, 'docs/DEVELOPER_GUIDE.md') || ! str_contains($readme, 'docs/USER_GUIDE.md')) {
    $errors[] = 'README.md must link to the canonical developer and user guides.';
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL: documentation contract violations detected:\n");
    foreach ($errors as $error) fwrite(STDERR, " - {$error}\n");
    exit(1);
}

printf(
    "PASS: canonical documentation is present and covers %d feature keys, %d AI feature keys and %d user roles.\n",
    count((array) ($featureConfig['definitions'] ?? [])),
    count((array) ($aiConfig['features'] ?? [])),
    count($matches[1] ?? []),
);
