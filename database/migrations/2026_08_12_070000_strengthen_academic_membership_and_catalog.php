<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->makeTenantScopedCodeUnique(
            'faculties', 'faculties_code_unique', ['university_id', 'code'],
            'faculties_university_code_unique', ['university_id', 'is_active'], 'faculties_uni_active_idx'
        );
        $this->makeTenantScopedCodeUnique(
            'departments', 'departments_code_unique', ['faculty_id', 'code'],
            'departments_faculty_code_unique', ['faculty_id', 'is_active'], 'departments_faculty_active_idx'
        );
        $this->makeTenantScopedCodeUnique(
            'courses', 'courses_code_unique', ['department_id', 'code'],
            'courses_department_code_unique', ['department_id', 'is_active'], 'courses_department_active_idx'
        );

        if (! Schema::hasTable('course_invitations')) {
            Schema::create('course_invitations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
                $table->string('email');
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at')->index();
                $table->timestamp('accepted_at')->nullable();
                $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['course_id', 'email'], 'course_invites_course_email_idx');
            });
        }
    }

    private function makeTenantScopedCodeUnique(
        string $tableName,
        string $legacyUnique,
        array $tenantColumns,
        string $tenantUnique,
        array $activeColumns,
        string $activeIndex
    ): void {
        if (! Schema::hasTable($tableName)) return;

        $indexes = collect(Schema::getIndexes($tableName))->pluck('name')->all();

        if (in_array($legacyUnique, $indexes, true)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($legacyUnique));
            $indexes = collect(Schema::getIndexes($tableName))->pluck('name')->all();
        }

        if (! in_array($tenantUnique, $indexes, true)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->unique($tenantColumns, $tenantUnique));
        }

        $indexes = collect(Schema::getIndexes($tableName))->pluck('name')->all();
        if (! in_array($activeIndex, $indexes, true)) {
            Schema::table($tableName, fn (Blueprint $table) => $table->index($activeColumns, $activeIndex));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('course_invitations');

        // We deliberately do not recreate the old global code UNIQUE constraints.
        // After multiple institutions have been seeded, legitimate tenant-scoped
        // duplicate codes such as CSC or ENG can exist and a rollback must not fail
        // or corrupt that data. The base migration still defines the historical
        // constraint for old snapshots.
        foreach ([
            'courses' => ['courses_department_code_unique', 'courses_department_active_idx'],
            'departments' => ['departments_faculty_code_unique', 'departments_faculty_active_idx'],
            'faculties' => ['faculties_university_code_unique', 'faculties_uni_active_idx'],
        ] as $tableName => [$uniqueName, $indexName]) {
            if (! Schema::hasTable($tableName)) continue;
            $indexes = collect(Schema::getIndexes($tableName))->pluck('name')->all();
            Schema::table($tableName, function (Blueprint $table) use ($indexes, $uniqueName, $indexName): void {
                if (in_array($uniqueName, $indexes, true)) $table->dropUnique($uniqueName);
                if (in_array($indexName, $indexes, true)) $table->dropIndex($indexName);
            });
        }
    }
};
