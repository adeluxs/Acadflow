<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'users' => ['admin_users_created_idx' => ['created_at', 'id']],
        'universities' => ['admin_universities_type_name_idx' => ['institution_type', 'name', 'id']],
        'faculties' => [
            'admin_faculties_name_idx' => ['name', 'id'],
            'admin_faculties_university_name_idx' => ['university_id', 'name', 'id'],
        ],
        'departments' => ['admin_departments_active_name_idx' => ['is_active', 'name', 'id']],
        'audit_logs' => [
            'admin_audit_created_idx' => ['created_at', 'id'],
            'admin_audit_user_created_idx' => ['user_id', 'created_at', 'id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                // MySQL DDL can partially commit if a deployment is interrupted.
                if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES, true) as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }
};
