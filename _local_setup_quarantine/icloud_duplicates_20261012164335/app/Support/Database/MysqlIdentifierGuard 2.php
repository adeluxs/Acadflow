<?php

declare(strict_types=1);

namespace App\Support\Database;

use RuntimeException;

final class MysqlIdentifierGuard
{
    public const MYSQL_MAX_IDENTIFIER_LENGTH = 64;

    /**
     * @return list<array{file:string,line:int,type:string,name:string,length:int,table:string}>
     */
    public function scan(string $migrationDirectory): array
    {
        if (! is_dir($migrationDirectory)) {
            return [];
        }

        $violations = [];
        $files = glob(rtrim($migrationDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            if ($source === false || $source === '') {
                continue;
            }

            foreach ($this->schemaBlocks($source) as $block) {
                $violations = array_merge($violations, $this->scanBlock($file, $source, $block));
            }
        }

        return $violations;
    }

    public function assertValid(string $migrationDirectory): void
    {
        $violations = $this->scan($migrationDirectory);
        if ($violations === []) {
            return;
        }

        $lines = [
            'MySQL schema identifier preflight failed.',
            'MySQL limits index, unique-key and foreign-key names to '.self::MYSQL_MAX_IDENTIFIER_LENGTH.' characters.',
            'Give the following constraints explicit short names before running migrations:',
        ];

        foreach ($violations as $violation) {
            $lines[] = sprintf(
                '- %s:%d [%s] %s (%d chars, table %s)',
                $violation['file'],
                $violation['line'],
                $violation['type'],
                $violation['name'],
                $violation['length'],
                $violation['table'],
            );
        }

        throw new RuntimeException(implode(PHP_EOL, $lines));
    }

    /**
     * @return list<array{table:string,start:int,end:int,body:string}>
     */
    private function schemaBlocks(string $source): array
    {
        preg_match_all(
            '/Schema::(?:create|table)\(\s*[\'\"]([^\'\"]+)[\'\"]\s*,\s*function\b/',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        $blocks = [];
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $start = $matches[0][$i][1];
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($source);
            $blocks[] = [
                'table' => $matches[1][$i][0],
                'start' => $start,
                'end' => $end,
                'body' => substr($source, $start, $end - $start),
            ];
        }

        return $blocks;
    }

    /**
     * @param array{table:string,start:int,end:int,body:string} $block
     * @return list<array{file:string,line:int,type:string,name:string,length:int,table:string}>
     */
    private function scanBlock(string $file, string $source, array $block): array
    {
        $violations = [];
        $table = $block['table'];
        $body = $block['body'];

        preg_match_all(
            '/\$table->(index|unique|fullText|spatialIndex)\(\s*\[([^\]]+)\]\s*(?:,\s*[\'\"]([^\'\"]+)[\'\"])?\s*\)/s',
            $body,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        foreach ($matches as $match) {
            $type = $match[1][0];
            preg_match_all('/[\'\"]([^\'\"]+)[\'\"]/', $match[2][0], $columnMatches);
            $columns = $columnMatches[1] ?? [];
            if ($columns === []) {
                continue;
            }

            $explicit = $match[3][0] ?? '';
            $suffix = match ($type) {
                'fullText' => 'fulltext',
                'spatialIndex' => 'spatialindex',
                default => strtolower($type),
            };
            $name = $explicit !== '' ? $explicit : $table.'_'.implode('_', $columns).'_'.$suffix;
            $this->addIfTooLong($violations, $file, $source, $block['start'] + $match[0][1], $type, $name, $table);
        }

        // Column-level ->index() / ->unique() declarations.
        preg_match_all(
            '/\$table->[A-Za-z_][A-Za-z0-9_]*\(\s*[\'\"]([^\'\"]+)[\'\"][^;]*?->(index|unique)\(\s*(?:[\'\"]([^\'\"]+)[\'\"])?\s*\)/s',
            $body,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        foreach ($matches as $match) {
            $column = $match[1][0];
            $type = $match[2][0];
            $explicit = $match[3][0] ?? '';
            $name = $explicit !== '' ? $explicit : $table.'_'.$column.'_'.$type;
            $this->addIfTooLong($violations, $file, $source, $block['start'] + $match[0][1], $type, $name, $table);
        }

        // foreignId(...)->constrained(...) declarations. Laravel's third constrained()
        // argument (or named indexName argument) is the FK constraint name.
        preg_match_all(
            '/\$table->foreignId\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)([^;]*?)->constrained\s*\(([^)]*)\)/s',
            $body,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        foreach ($matches as $match) {
            $column = $match[1][0];
            $arguments = $match[3][0];
            $explicit = $this->constrainedIndexName($arguments);
            $name = $explicit ?? $table.'_'.$column.'_foreign';
            $this->addIfTooLong($violations, $file, $source, $block['start'] + $match[0][1], 'foreign', $name, $table);
        }

        // Explicit $table->foreign(...) declarations.
        preg_match_all(
            '/\$table->foreign\(\s*(?:\[\s*)?[\'\"]([^\'\"]+)[\'\"](?:\s*\])?\s*(?:,\s*[\'\"]([^\'\"]+)[\'\"])?\s*\)/s',
            $body,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        foreach ($matches as $match) {
            $column = $match[1][0];
            $explicit = $match[2][0] ?? '';
            $name = $explicit !== '' ? $explicit : $table.'_'.$column.'_foreign';
            $this->addIfTooLong($violations, $file, $source, $block['start'] + $match[0][1], 'foreign', $name, $table);
        }

        return $violations;
    }

    private function constrainedIndexName(string $arguments): ?string
    {
        if (preg_match('/\bindexName\s*:\s*[\'\"]([^\'\"]+)[\'\"]/', $arguments, $match) === 1) {
            return $match[1];
        }

        preg_match_all('/[\'\"]([^\'\"]+)[\'\"]/', $arguments, $matches);
        $quoted = $matches[1] ?? [];

        return count($quoted) >= 3 ? $quoted[2] : null;
    }

    /**
     * @param list<array{file:string,line:int,type:string,name:string,length:int,table:string}> $violations
     */
    private function addIfTooLong(array &$violations, string $file, string $source, int $offset, string $type, string $name, string $table): void
    {
        $length = strlen($name);
        if ($length <= self::MYSQL_MAX_IDENTIFIER_LENGTH) {
            return;
        }

        $violations[] = [
            'file' => $file,
            'line' => substr_count(substr($source, 0, $offset), "\n") + 1,
            'type' => $type,
            'name' => $name,
            'length' => $length,
            'table' => $table,
        ];
    }
}
