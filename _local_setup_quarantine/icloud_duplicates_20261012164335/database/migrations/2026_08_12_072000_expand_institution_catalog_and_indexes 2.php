<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('universities')) {
            Schema::table('universities', function (Blueprint $table): void {
                if (! Schema::hasColumn('universities', 'institution_type')) {
                    $table->string('institution_type', 30)->default('university')->after('code')->index();
                }
                if (! Schema::hasColumn('universities', 'ownership')) {
                    $table->string('ownership', 30)->nullable()->after('institution_type')->index();
                }
                if (! Schema::hasColumn('universities', 'state')) {
                    $table->string('state', 80)->nullable()->after('ownership')->index();
                }
                if (! Schema::hasColumn('universities', 'regulator')) {
                    $table->string('regulator', 30)->nullable()->after('state');
                }
                if (! Schema::hasColumn('universities', 'catalog_source')) {
                    $table->text('catalog_source')->nullable()->after('regulator');
                }
                if (! Schema::hasColumn('universities', 'catalog_verified_at')) {
                    $table->timestamp('catalog_verified_at')->nullable()->after('catalog_source');
                }
            });
        }

        foreach (['faculties', 'departments', 'courses'] as $catalogTable) {
            if (! Schema::hasTable($catalogTable)) continue;
            Schema::table($catalogTable, function (Blueprint $table) use ($catalogTable): void {
                if (! Schema::hasColumn($catalogTable, 'catalog_source')) {
                    $table->string('catalog_source', 60)->nullable()->index();
                }
                if (! Schema::hasColumn($catalogTable, 'is_catalog_template')) {
                    $table->boolean('is_catalog_template')->default(false)->index();
                }
            });
        }

        // Academic-session names are tenant data. A global unique name prevents
        // two institutions from both having a normal session such as 2026/2027.
        if (Schema::hasTable('academic_sessions')) {
            $driver = DB::getDriverName();
            $indexes = collect(Schema::getIndexes('academic_sessions'))->pluck('name')->all();
            $hasGlobalNameUnique = in_array('academic_sessions_name_unique', $indexes, true);
            $hasTenantUnique = in_array('academic_sessions_university_name_unique', $indexes, true)
                || in_array('academic_sessions_university_id_name_unique', $indexes, true);

            if ($driver === 'mysql' && $hasGlobalNameUnique) {
                DB::statement('ALTER TABLE academic_sessions DROP INDEX academic_sessions_name_unique');
            } elseif ($driver === 'pgsql' && $hasGlobalNameUnique) {
                DB::statement('DROP INDEX IF EXISTS academic_sessions_name_unique');
            }

            if (! $hasTenantUnique && $driver === 'mysql') {
                DB::statement('ALTER TABLE academic_sessions ADD UNIQUE academic_sessions_university_name_unique (university_id, name)');
            } elseif (! $hasTenantUnique && $driver === 'pgsql') {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS academic_sessions_university_name_unique ON academic_sessions (university_id, name)');
            } elseif ($driver === 'sqlite') {
                // SQLite cannot reliably drop an existing table-level UNIQUE constraint
                // in place. Fresh installs receive the fixed definition in the base migration.
            }
        }

        $this->addPerformanceIndexes();
    }

    private function addPerformanceIndexes(): void
    {
        $definitions = [
            'enrollments' => [
                ['columns' => ['course_id', 'semester_id', 'status'], 'name' => 'enroll_course_sem_status_idx'],
                ['columns' => ['user_id', 'status'], 'name' => 'enroll_user_status_idx'],
            ],
            'lecturer_course_assignments' => [
                ['columns' => ['user_id', 'semester_id'], 'name' => 'lecturer_semester_idx'],
            ],
            'submissions' => [
                ['columns' => ['course_id', 'status', 'submitted_at'], 'name' => 'submission_course_status_date_idx'],
                ['columns' => ['user_id', 'course_id'], 'name' => 'submission_user_course_idx'],
            ],
            'submission_tasks' => [
                ['columns' => ['course_id', 'status', 'close_at'], 'name' => 'task_course_status_close_idx'],
            ],
            'submission_grades' => [
                ['columns' => ['submission_id', 'is_final'], 'name' => 'grade_submission_final_idx'],
            ],
        ];

        foreach ($definitions as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) continue;
            foreach ($indexes as $index) {
                $existing = collect(Schema::getIndexes($tableName))->pluck('name')->all();
                if (in_array($index['name'], $existing, true)) continue;
                $columnsExist = collect($index['columns'])->every(fn (string $column) => Schema::hasColumn($tableName, $column));
                if (! $columnsExist) continue;
                Schema::table($tableName, fn (Blueprint $table) => $table->index($index['columns'], $index['name']));
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('academic_sessions')) {
            $driver = DB::getDriverName();
            $indexes = collect(Schema::getIndexes('academic_sessions'))->pluck('name')->all();
            if ($driver === 'mysql' && in_array('academic_sessions_university_name_unique', $indexes, true)) {
                DB::statement('ALTER TABLE academic_sessions DROP INDEX academic_sessions_university_name_unique');
                DB::statement('ALTER TABLE academic_sessions ADD UNIQUE academic_sessions_name_unique (name)');
            } elseif ($driver === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS academic_sessions_university_name_unique');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS academic_sessions_name_unique ON academic_sessions (name)');
            }
        }

        foreach ([
            'enrollments' => ['enroll_course_sem_status_idx', 'enroll_user_status_idx'],
            'lecturer_course_assignments' => ['lecturer_semester_idx'],
            'submissions' => ['submission_course_status_date_idx', 'submission_user_course_idx'],
            'submission_tasks' => ['task_course_status_close_idx'],
            'submission_grades' => ['grade_submission_final_idx'],
        ] as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) continue;
            $existing = collect(Schema::getIndexes($tableName))->pluck('name')->all();
            Schema::table($tableName, function (Blueprint $table) use ($indexes, $existing): void {
                foreach ($indexes as $name) if (in_array($name, $existing, true)) $table->dropIndex($name);
            });
        }

        foreach (['faculties', 'departments', 'courses'] as $catalogTable) {
            if (! Schema::hasTable($catalogTable)) continue;
            $columns = array_values(array_filter(['catalog_source', 'is_catalog_template'], fn ($column) => Schema::hasColumn($catalogTable, $column)));
            if ($columns) Schema::table($catalogTable, fn (Blueprint $table) => $table->dropColumn($columns));
        }

        if (Schema::hasTable('universities')) {
            $columns = array_values(array_filter([
                'institution_type', 'ownership', 'state', 'regulator', 'catalog_source', 'catalog_verified_at',
            ], fn ($column) => Schema::hasColumn('universities', $column)));
            if ($columns) Schema::table('universities', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }
};
