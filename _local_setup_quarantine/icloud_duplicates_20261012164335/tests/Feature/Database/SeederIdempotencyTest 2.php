<?php

namespace Tests\Feature\Database;

use App\Models\AiPromptVersion;
use App\Models\FeatureFlag;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_can_run_twice_without_duplicates_or_overwriting_existing_values(): void
    {
        config()->set('academic_catalog.remote_sync', false);
        config()->set('academic_catalog.seed_templates', true);

        $this->seed(DatabaseSeeder::class);

        $tables = [
            'settings',
            'feature_flags',
            'users',
            'universities',
            'faculties',
            'departments',
            'courses',
            'academic_sessions',
            'semesters',
            'lecturer_course_assignments',
            'enrollments',
            'ai_prompt_versions',
            'workflow_definitions',
            'workflow_stages',
            'research_types',
            'knowledge_categories',
            'achievements',
        ];

        $before = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $before[$table] = DB::table($table)->count();
            }
        }

        $siteName = Setting::query()->where('key', 'site_name')->firstOrFail();
        $siteName->forceFill(['value' => 'My Existing AcadFlow'])->save();

        $feature = FeatureFlag::query()->where('name', 'research_studio')->firstOrFail();
        $feature->forceFill([
            'is_enabled' => false,
            'settings' => array_merge((array) $feature->settings, [
                'access_status' => 'maintenance',
                'admin_note' => 'Keep this existing administrator choice.',
            ]),
        ])->save();


        $student = User::query()->where('email', 'student001@student.futo.edu.ng')->firstOrFail();
        $student->forceFill(['first_name' => 'Customized Student'])->save();

        $prompt = AiPromptVersion::query()
            ->whereNull('university_id')
            ->where('feature', 'knowledge_companion')
            ->where('version', 2)
            ->firstOrFail();
        $prompt->forceFill(['system_prompt' => 'Existing customized grounded prompt'])->save();

        $this->seed(DatabaseSeeder::class);

        foreach ($before as $table => $count) {
            $this->assertSame(
                $count,
                DB::table($table)->count(),
                "Seeder re-run changed row count for {$table}."
            );
        }

        $this->assertSame(
            'My Existing AcadFlow',
            (string) Setting::query()->where('key', 'site_name')->value('value')
        );

        $feature->refresh();
        $this->assertFalse((bool) $feature->is_enabled);
        $this->assertSame('maintenance', data_get($feature->settings, 'access_status'));
        $this->assertSame('Keep this existing administrator choice.', data_get($feature->settings, 'admin_note'));


        $this->assertSame(
            'Customized Student',
            User::query()->where('email', 'student001@student.futo.edu.ng')->value('first_name')
        );

        $this->assertSame(
            'Existing customized grounded prompt',
            AiPromptVersion::query()
                ->whereNull('university_id')
                ->where('feature', 'knowledge_companion')
                ->where('version', 2)
                ->value('system_prompt')
        );
    }
}
