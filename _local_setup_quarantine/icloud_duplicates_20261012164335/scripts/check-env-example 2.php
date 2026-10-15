<?php

$root = dirname(__DIR__);
$example = $root.'/.env.example';

if (! is_file($example)) {
    fwrite(STDERR, "FAIL: .env.example is missing.\n");
    exit(1);
}

$keys = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (str_starts_with($relative, 'vendor/') || str_starts_with($relative, 'storage/')) {
        continue;
    }

    $contents = file_get_contents($path);
    if (preg_match_all('/env\(\s*[\'\"]([A-Z0-9_]+)[\'\"]/', $contents, $matches)) {
        foreach ($matches[1] as $key) {
            $keys[$key] = true;
        }
    }
}

$exampleContents = file_get_contents($example);
preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $exampleContents, $matches);
$exampleKeys = array_fill_keys($matches[1], true);

$missing = array_values(array_diff(array_keys($keys), array_keys($exampleKeys)));
sort($missing);

$duplicates = array_count_values($matches[1]);
$duplicates = array_keys(array_filter($duplicates, static fn (int $count): bool => $count > 1));
sort($duplicates);

// Values that are syntactically optional to Dotenv but invalid when left empty
// at runtime. Keep this list deliberately small and limited to values where an
// empty string causes a framework/runtime failure rather than simply disabling
// an integration.
$requiredNonEmpty = ['SESSION_COOKIE', 'DB_CACHE_TABLE', 'DB_CACHE_LOCK_TABLE'];
$emptyCritical = [];
foreach ($requiredNonEmpty as $key) {
    if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $exampleContents, $valueMatch)) {
        $value = trim($valueMatch[1]);
        if ($value === '' || $value === '""' || $value === "''") {
            $emptyCritical[] = $key;
        }
    }
}

if ($missing || $duplicates || $emptyCritical) {
    if ($missing) {
        fwrite(STDERR, "FAIL: env() keys missing from .env.example:\n - ".implode("\n - ", $missing)."\n");
    }
    if ($duplicates) {
        fwrite(STDERR, "FAIL: duplicate keys in .env.example:\n - ".implode("\n - ", $duplicates)."\n");
    }
    if ($emptyCritical) {
        fwrite(STDERR, "FAIL: runtime-critical env keys must not be blank in .env.example:\n - ".implode("\n - ", $emptyCritical)."\n");
    }
    exit(1);
}

echo 'PASS: '.count($keys)." env() keys are represented in .env.example with no duplicate keys and no blank runtime-critical values.\n";
