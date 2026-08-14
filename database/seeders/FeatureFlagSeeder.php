<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('features.definitions', []) as $name => $definition) {
            $defaultStatus = (string) ($definition['default_status'] ?? 'enabled');

            // Never overwrite an existing installation's administrator-selected
            // release state when seeders are re-run.
            FeatureFlag::query()->firstOrCreate(
                ['name' => $name],
                [
                    'description' => $definition['description'] ?? $definition['title'] ?? Str::headline($name),
                    'is_enabled' => $defaultStatus === 'enabled',
                    'settings' => [
                        'access_status' => $defaultStatus,
                        'maintenance_message' => '',
                        'admin_note' => '',
                    ],
                    'enabled_at' => $defaultStatus === 'enabled' ? now() : null,
                ],
            );
        }
    }
}
