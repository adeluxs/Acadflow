<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Support\Database\MysqlIdentifierGuard;
use PHPUnit\Framework\TestCase;

final class MysqlIdentifierLengthTest extends TestCase
{
    public function test_migrations_do_not_generate_mysql_identifiers_longer_than_64_characters(): void
    {
        $violations = (new MysqlIdentifierGuard())->scan(dirname(__DIR__, 2).'/database/migrations');

        $message = implode(PHP_EOL, array_map(
            static fn (array $item): string => sprintf(
                '%s:%d %s (%d chars)',
                $item['file'],
                $item['line'],
                $item['name'],
                $item['length'],
            ),
            $violations,
        ));

        self::assertSame([], $violations, $message);
    }
}
