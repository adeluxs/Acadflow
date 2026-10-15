<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table): void {
                if (! Schema::hasColumn('courses', 'access_mode')) {
                    $table->string('access_mode', 30)->default('institutional')->after('department_id')->index();
                }
                if (! Schema::hasColumn('courses', 'owner_user_id')) {
                    $table->foreignId('owner_user_id')->nullable()->after('access_mode')->constrained('users')->nullOnDelete()->index();
                }
            });
        }

        if (Schema::hasTable('research_projects') && ! Schema::hasColumn('research_projects', 'workspace_mode')) {
            Schema::table('research_projects', function (Blueprint $table): void {
                $table->string('workspace_mode', 30)->default('institutional')->after('department_id')->index();
            });
        }

        // Initial launch profile. These remain editable from Admin > Feature & Module Management.
        $disabled = [
            'attendance', 'course_discussions', 'group_collaboration', 'research_to_knowledge_hub',
            'siwes_module', 'seminar_module', 'final_year_project', 'knowledge_communities',
            'learning_paths', 'reading_lists', 'academic_events', 'academic_challenges',
            'course_certificates', 'knowledge_ai_companion', 'knowledge_hub_premium',
            'commerce_marketplace', 'creator_payouts', 'advanced_analytics', 'push_notifications', 'institution_self_service',
        ];
        $enabled = [
            'dashboard', 'courses', 'course_materials', 'assignments', 'submissions', 'research_studio',
            'knowledge_hub', 'ai_assistant', 'notifications', 'monetization_commerce', 'wallet_billing',
            'documents_exports', 'pwa_enabled',
        ];

        foreach ($disabled as $name) {
            $this->setFeature($name, false, 'disabled');
        }
        foreach ($enabled as $name) {
            $this->setFeature($name, true, 'enabled');
        }

        // Repair public lecturer accounts created before independent-first onboarding.
        if (Schema::hasTable('users')) {
            DB::table('users')->where('account_type', 'lecturer')->where('role', 'member')->update(['role' => 'lecturer', 'updated_at' => now()]);
            DB::table('users')->where('account_type', 'student')->where('role', 'member')->update(['role' => 'student', 'updated_at' => now()]);
        }

        // Classroom adoption features start free. Admin can publish new prices later.
        if (Schema::hasTable('pricing_rules')) {
            DB::table('pricing_rules')
                ->whereIn('key', ['feature.allow_group_submissions', 'feature.allow_rubrics', 'feature.attendance_tracking'])
                ->where('scope_type', 'global')
                ->where('scope_id', 0)
                ->update(['enabled' => false, 'updated_at' => now()]);
        }
    }

    private function setFeature(string $name, bool $enabled, string $status): void
    {
        if (! Schema::hasTable('feature_flags')) {
            return;
        }

        $row = DB::table('feature_flags')->where('name', $name)->first();
        $settings = [];
        if ($row && isset($row->settings) && $row->settings) {
            $decoded = json_decode((string) $row->settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        $settings['access_status'] = $status;
        $settings['admin_note'] = 'Independent-first launch profile. This module remains installed and can be re-enabled by Super Admin.';

        $payload = [
            'is_enabled' => $enabled,
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
            'description' => $row?->description ?: 'AcadFlow feature managed by centralized Feature & Module Management.',
            'updated_at' => now(),
        ];

        if ($row) {
            DB::table('feature_flags')->where('id', $row->id)->update($payload);
        } else {
            DB::table('feature_flags')->insert(array_merge($payload, [
                'name' => $name,
                'enabled_at' => $enabled ? now() : null,
                'enabled_by' => null,
                'created_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        // Do not revert feature choices: administrators may have changed them after deployment.
        if (Schema::hasTable('research_projects') && Schema::hasColumn('research_projects', 'workspace_mode')) {
            Schema::table('research_projects', fn (Blueprint $table) => $table->dropColumn('workspace_mode'));
        }
        if (Schema::hasTable('courses')) {
            $hasOwner = Schema::hasColumn('courses', 'owner_user_id');
            $hasMode = Schema::hasColumn('courses', 'access_mode');
            Schema::table('courses', function (Blueprint $table) use ($hasOwner, $hasMode): void {
                if ($hasOwner) {
                    $table->dropConstrainedForeignId('owner_user_id');
                }
                if ($hasMode) {
                    $table->dropColumn('access_mode');
                }
            });
        }
    }
};
