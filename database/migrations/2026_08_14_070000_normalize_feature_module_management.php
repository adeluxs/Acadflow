<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_flags') || ! Schema::hasColumn('feature_flags', 'settings')) {
            return;
        }

        $definitions = (array) config('features.definitions', []);
        $now = now();

        foreach ($definitions as $name => $definition) {
            $existing = DB::table('feature_flags')->where('name', $name)->first();

            if ($existing) {
                $settings = $this->decodeSettings($existing->settings ?? null);
                if (! array_key_exists('access_status', $settings)) {
                    // Preserve the exact production state that existed before this
                    // upgrade instead of replacing it with a new default.
                    $settings['access_status'] = (bool) $existing->is_enabled ? 'enabled' : 'disabled';
                }
                $settings['maintenance_message'] ??= '';
                $settings['admin_note'] ??= '';

                DB::table('feature_flags')->where('id', $existing->id)->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => $now,
                ]);
                continue;
            }

            $defaultStatus = (string) ($definition['default_status'] ?? 'enabled');
            $legacyStatus = $this->legacyRuntimeStatus($name);
            $status = $legacyStatus ?? $defaultStatus;
            $enabled = $status === 'enabled';

            DB::table('feature_flags')->insert([
                'name' => $name,
                'is_enabled' => $enabled,
                'description' => $definition['description'] ?? $definition['title'] ?? Str::headline($name),
                'settings' => json_encode([
                    'access_status' => $status,
                    'maintenance_message' => '',
                    'admin_note' => '',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'enabled_at' => $enabled ? $now : null,
                'enabled_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. This migration normalizes existing
        // production feature records and seeds missing registry entries. A
        // rollback must not delete administrator-selected feature states.
    }

    private function decodeSettings(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function legacyRuntimeStatus(string $feature): ?string
    {
        if (! Schema::hasTable('settings')) {
            return null;
        }

        $legacyKey = match ($feature) {
            'pwa_enabled' => 'pwa_enabled',
            'knowledge_hub_premium' => 'knowledge_hub_premium_enabled',
            default => null,
        };

        if ($legacyKey === null) {
            return null;
        }

        $setting = DB::table('settings')->where('key', $legacyKey)->first();
        if (! $setting) {
            return null;
        }

        return $this->toBoolean($setting->value ?? null) ? 'enabled' : 'disabled';
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'enabled'], true);
    }
};
