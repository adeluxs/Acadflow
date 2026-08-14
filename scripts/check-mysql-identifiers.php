<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Support/Database/MysqlIdentifierGuard.php';

use App\Support\Database\MysqlIdentifierGuard;

$guard = new MysqlIdentifierGuard();

try {
    $guard->assertValid(__DIR__.'/../database/migrations');
    fwrite(STDOUT, "MySQL identifier preflight: OK (all migration identifiers are <= 64 characters).".PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
}
