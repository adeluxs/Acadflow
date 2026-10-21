<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'research_types' => ['research_type_options_idx' => ['university_id', 'is_active', 'name']],
        'research_projects' => [
            'research_owner_updated_idx' => ['owner_id', 'workspace_mode', 'updated_at'],
            'research_tenant_updated_idx' => ['university_id', 'workspace_mode', 'updated_at'],
        ],
        'users' => ['research_supervisor_options_idx' => ['university_id', 'role', 'is_active', 'first_name', 'last_name']],
        'sessions' => ['session_user_activity_idx' => ['user_id', 'last_activity']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            foreach ($indexes as $name => $columns) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES) as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            foreach ($indexes as $name => $columns) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }
};
