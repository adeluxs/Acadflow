<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now();
        $settings = [
            ['key' => 'ai_grounded_pattern_learning_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'ai', 'description' => 'Learn conservative retrieval patterns from successful grounded companion sessions'],
            ['key' => 'ai_grounded_min_question_chars', 'value' => '3', 'type' => 'integer', 'group' => 'ai', 'description' => 'Minimum normalized question length for the Grounded AI Companion'],
            ['key' => 'ai_grounded_gibberish_threshold', 'value' => '0.60', 'type' => 'decimal', 'group' => 'ai', 'description' => 'Maximum tolerated ratio of likely gibberish tokens before a grounded question is rejected'],
            ['key' => 'ai_grounded_relevance_threshold', 'value' => '0.18', 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum publication-scoped retrieval relevance score for specific grounded questions'],
            ['key' => 'ai_grounded_lexical_floor', 'value' => '0.20', 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum lexical evidence floor for a specific grounded question'],
            ['key' => 'ai_grounded_citation_coverage_min', 'value' => '0.85', 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum fraction of substantive provider answer sentences that must contain valid source citations'],
            ['key' => 'ai_grounded_support_threshold', 'value' => '0.20', 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum cited-sentence support score against the referenced source excerpt'],
            ['key' => 'ai_grounded_support_coverage_min', 'value' => '0.70', 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum fraction of cited sentences that must be supported by their referenced excerpts'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore(array_merge($setting, [
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // Intentionally preserve runtime settings on rollback. Some installations
        // may have created or customized these keys before this migration ran;
        // deleting them would destroy administrator configuration.
    }

};
