<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'name' => 'pwa_enabled',
                'description' => 'Enable Progressive Web App features (offline mode, installable)',
                'is_enabled' => true,
            ],
            [
                'name' => 'push_notifications',
                'description' => 'Enable browser push notifications',
                'is_enabled' => false,
            ],
            [
                'name' => 'advanced_analytics',
                'description' => 'Show detailed analytics dashboard for lecturers',
                'is_enabled' => false,
            ],
            [
                'name' => 'course_certificates',
                'description' => 'Allow generation of course completion certificates',
                'is_enabled' => false,
            ],
            [
                'name' => 'siwes_module',
                'description' => 'Enable SIWES (Student Industrial Work Experience Scheme) module',
                'is_enabled' => false,
            ],
            [
                'name' => 'final_year_project',
                'description' => 'Enable final year project management features',
                'is_enabled' => true,
            ],
            [
                'name' => 'group_collaboration',
                'description' => 'Enable student group creation and management',
                'is_enabled' => true,
            ],
            [
                'name' => 'ai_assistant',
                'description' => 'Enable AI-powered learning assistant (integration with OpenAI)',
                'is_enabled' => false,
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::firstOrCreate(
                ['name' => $flag['name']],
                $flag
            );
        }
    }
}
