<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests run on SQLite, which accepts MySQL/MariaDB reserved words as column aliases.
 * Production runs MariaDB, where "AS reads" or "AS rank" is a syntax error (500 page).
 * This scans raw SQL in the app for aliases that MySQL/MariaDB reserve.
 */
class NoReservedSqlAliasesTest extends TestCase
{
    private const RESERVED = ['reads', 'read', 'rank', 'range', 'key', 'keys', 'order', 'group', 'groups', 'row', 'rows', 'window',
        'over', 'lead', 'lag', 'interval', 'condition', 'match', 'check', 'usage', 'limit', 'change', 'release', 'index', 'signal',
        'repeat', 'leave', 'div', 'mod', 'load', 'lock', 'option', 'references', 'sensitive', 'virtual', 'where', 'with', 'values', 'value_count'];

    public function test_raw_sql_does_not_use_reserved_words_as_aliases(): void
    {
        $root = dirname(__DIR__, 2).'/app';
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $pattern = '/\bAS\s+('.implode('|', array_map('preg_quote', self::RESERVED)).')\b(?=\s*[,\'"\)]|\s+FROM|\s*$)/i';
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            foreach (file($file->getPathname()) as $number => $line) {
                // Only lines that build SQL; skip comments and plain English ("marked as read").
                if (! preg_match('/selectRaw|DB::raw|orderByRaw|havingRaw|whereRaw|DB::select|->select\(|COALESCE|COUNT\(|SUM\(/i', $line)) {
                    continue;
                }
                if (preg_match($pattern, $line, $match)) {
                    $offenders[] = str_replace($root, 'app', $file->getPathname()).':'.($number + 1).' uses "AS '.$match[1].'"';
                }
            }
        }

        $this->assertSame([], $offenders, "Rename these aliases (MySQL/MariaDB reserve them):\n".implode("\n", $offenders));
    }
}
