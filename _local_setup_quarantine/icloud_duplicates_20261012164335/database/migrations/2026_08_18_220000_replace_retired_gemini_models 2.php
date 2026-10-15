<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace only upstream model identifiers that Google has already shut down.
     * All unrelated/custom administrator model selections are preserved.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) return;

        $replacements = (array) config('ai.retired_model_replacements.gemini', []);
        if ($replacements === []) return;

        $modelSettings = DB::table('settings')
            ->where(function ($query): void {
                $query->where('key', 'ai_provider_gemini_model')
                    ->orWhere('key', 'ai_default_model')
                    ->orWhere('key', 'ai_fallback_model')
                    ->orWhere('key', 'ai_secondary_fallback_model')
                    ->orWhere('key', 'like', 'ai_feature_%_model');
            })
            ->get(['id', 'key', 'value']);

        foreach ($modelSettings as $setting) {
            $current = trim((string) $setting->value);
            $replacement = $replacements[$current] ?? null;
            if (! is_string($replacement) || $replacement === '' || $replacement === $current) continue;

            DB::table('settings')->where('id', $setting->id)->update([
                'value' => $replacement,
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('setting_overrides')) {
                DB::table('setting_overrides')
                    ->where('setting_id', $setting->id)
                    ->where('value', $current)
                    ->update([
                        'value' => $replacement,
                        'updated_at' => now(),
                    ]);
            }
        }

        $modelsSetting = DB::table('settings')->where('key', 'ai_provider_gemini_models')->first(['id', 'value']);
        if ($modelsSetting) {
            $models = json_decode((string) $modelsSetting->value, true);
            if (is_array($models)) {
                $normalized = array_values(array_unique(array_filter(array_map(
                    static fn ($model) => $replacements[trim((string) $model)] ?? trim((string) $model),
                    $models
                ))));

                DB::table('settings')->where('id', $modelsSetting->id)->update([
                    'value' => json_encode($normalized, JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. Retired upstream model IDs must not be
        // restored during rollback because those endpoints no longer function.
    }
};
