<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) return;

        $now = now();
        $rows = [];
        $add = static function (string $key, mixed $value, string $type, string $description) use (&$rows, $now): void {
            $rows[] = [
                'key' => $key,
                'value' => is_array($value) ? json_encode($value) : ($type === 'boolean' ? ($value ? '1' : '0') : (string) $value),
                'type' => $type,
                'group' => 'ai',
                'description' => $description,
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        };

        $bootstrap = (array) config('ai.providers.grok', []);
        $model = trim((string) ($bootstrap['model'] ?? 'grok-4.5'));
        $configured = trim((string) ($bootstrap['api_key'] ?? '')) !== '';

        // insertOrIgnore means an upgraded production installation never has
        // an existing administrator value or credential reset by this migration.
        $add('ai_provider_grok_enabled', $configured, 'boolean', 'Grok (xAI) is available for central provider routing');
        $add('ai_provider_grok_model', $model, 'string', 'Default configured model for Grok (xAI)');
        $add('ai_provider_grok_models', array_values(array_filter([$model])), 'json', 'Allowed configured Grok (xAI) models');
        $add('ai_provider_grok_temperature', $bootstrap['temperature'] ?? config('ai.temperature', 0.2), 'decimal', 'Grok (xAI) generation temperature');
        $add('ai_provider_grok_base_url', '', 'string', 'Optional Grok (xAI) base URL override; blank uses secure bootstrap configuration');
        $add('ai_provider_grok_api_key', '', 'string', 'Encrypted Grok (xAI) API credential override; blank uses XAI_API_KEY');
        $add('ai_fast_failover', config('ai.fast_failover', true), 'boolean', 'Move to the next configured provider immediately after retryable provider/network failures instead of waiting on repeated interactive attempts');

        DB::table('settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        // Deliberately non-destructive. Provider settings may have been edited
        // after deployment; rollback must not delete administrator configuration.
    }
};
