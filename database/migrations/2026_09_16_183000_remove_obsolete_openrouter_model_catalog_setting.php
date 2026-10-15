<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) return;

        // The full OpenRouter discovery payload is diagnostic/cache data, not
        // runtime configuration. Older builds tried to persist it in the
        // generic TEXT settings.value column and could exceed MySQL's limit.
        DB::table('settings')
            ->where('key', 'ai_provider_openrouter_model_catalog')
            ->delete();
    }

    public function down(): void
    {
        // Intentionally no-op: the obsolete oversized setting must not be
        // recreated. Runtime model IDs remain in ai_provider_openrouter_models.
    }
};
