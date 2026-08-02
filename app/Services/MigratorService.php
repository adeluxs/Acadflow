<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class MigratorService
{
    /**
     * Safely create a table (idempotent)
     * Use this instead of Schema::create() in migrations
     */
    public static function safeCreate(string $tableName, callable $callback): void
    {
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, $callback);
        }
    }

    /**
     * Safely drop a table (idempotent)
     */
    public static function safeDrop(string $tableName): void
    {
        if (Schema::hasTable($tableName)) {
            Schema::drop($tableName);
        }
    }

    /**
     * Check if all required tables exist
     */
    public static function verifyTables(array $requiredTables): array
    {
        $missing = [];
        
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }
        
        return $missing;
    }

    /**
     * Get migration status
     */
    public static function getStatus(): array
    {
        $migrationsTable = config('database.migrations.table', 'migrations');
        
        if (!Schema::hasTable($migrationsTable)) {
            return ['status' => 'no_migrations_table', 'message' => 'Migrations table not found'];
        }
        
        $ran = \DB::table($migrationsTable)->count();
        $files = glob(database_path('migrations/*.php'));
        
        return [
            'ran' => $ran,
            'pending' => count($files) - $ran,
            'total_files' => count($files),
        ];
    }
}
