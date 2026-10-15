<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$seederDir = $root.'/database/seeders';

if (! is_dir($seederDir)) {
    fwrite(STDERR, "database/seeders directory not found.\n");
    exit(1);
}

$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($seederDir));
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $files[] = $file->getPathname();
}
sort($files);

$forbidden = [
    'updateOrCreate(' => 'Seeders must preserve existing rows; use firstOrCreate with a stable identity instead.',
    'truncate(' => 'Seeders must never truncate existing data.',
    'forceDelete(' => 'Seeders must never force-delete existing data.',
    '->delete(' => 'Seeders must never delete existing data.',
    '::delete(' => 'Seeders must never delete existing data.',
    '->update(' => 'Seeders must not overwrite existing seeded/admin-edited data.',
    '::update(' => 'Seeders must not overwrite existing seeded/admin-edited data.',
    'upsert(' => 'Upsert may overwrite existing rows; seeders must be create-missing-only.',
    'insert(' => 'Direct insert can duplicate seed data; use firstOrCreate with a stable identity.',
    '::create(' => 'Direct create can duplicate seed data; use firstOrCreate with a stable identity.',
    '->create(' => 'Direct relation create can duplicate seed data; use firstOrCreate with a stable identity.',
];

$violations = [];
foreach ($files as $file) {
    $contents = file_get_contents($file) ?: '';
    $relative = str_replace($root.DIRECTORY_SEPARATOR, '', $file);

    foreach ($forbidden as $needle => $message) {
        if (str_contains($contents, $needle)) {
            $violations[] = "{$relative}: contains {$needle} — {$message}";
        }
    }
}

$nigeriaSeeder = file_get_contents($seederDir.'/NigeriaAcademicCatalogSeeder.php') ?: '';
if (! str_contains($nigeriaSeeder, 'preserveExisting: true')) {
    $violations[] = 'database/seeders/NigeriaAcademicCatalogSeeder.php must call registry sync with preserveExisting: true.';
}

$databaseSeeder = file_get_contents($seederDir.'/DatabaseSeeder.php') ?: '';
if (preg_match('/migrate:fresh|db:wipe|schema:drop|dropAllTables|truncate/i', $databaseSeeder)) {
    $violations[] = 'database/seeders/DatabaseSeeder.php contains a destructive reset operation.';
}

if ($violations !== []) {
    fwrite(STDERR, "Seeder idempotency preflight FAILED:\n\n".implode("\n", array_map(fn ($v) => ' - '.$v, $violations))."\n");
    exit(1);
}

echo "Seeder idempotency preflight PASS\n";
echo 'Seeders checked: '.count($files)."\n";
echo "No destructive/overwrite seeding patterns found.\n";
echo "Nigeria registry seeding preserves existing institution rows.\n";
