<?php

use App\Enums\AiProviderName;
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
        $add = function (string $key, mixed $value, string $type, string $description) use (&$rows, $now): void {
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

        // Canonical runtime/global AI settings. insertOrIgnore preserves any
        // administrator values already present on upgraded installations.
        $add('ai_mode', config('ai.default_mode', 'rule_based'), 'string', 'AI operating mode: provider, hybrid, rule_based, or disabled');
        $add('ai_default_provider', config('ai.default_provider', 'rule_based'), 'string', 'Default provider used by features configured as Use Global Default');
        $add('ai_fallback_provider', config('ai.fallback_provider', ''), 'string', 'First external fallback provider; none disables first fallback');
        $add('ai_similarity_threshold', config('ai.similarity_threshold', 20), 'integer', 'Academic similarity threshold used by plagiarism/integrity validation');
        $add('ai_request_timeout', config('ai.request_timeout', 30), 'integer', 'External provider request timeout in seconds');
        $add('ai_max_tokens', config('ai.max_tokens', 2048), 'integer', 'Global maximum generated token budget per provider request');
        $add('ai_daily_request_limit', config('ai.daily_request_limit', 1000), 'integer', 'Per-user daily AI request limit');
        $add('ai_monthly_request_limit', config('ai.monthly_request_limit', 30000), 'integer', 'Per-user monthly AI request limit');
        $add('ai_max_cost', config('ai.max_cost', 100), 'decimal', 'Maximum tracked monthly AI cost limit');
        $add('ai_enable_cache', config('ai.enable_cache', true), 'boolean', 'Enable safe AI response caching');
        $add('ai_enable_logging', config('ai.enable_logging', true), 'boolean', 'Enable AI usage/routing observability logging');
        $add('ai_hybrid_escalate_when_clean', config('ai.hybrid_escalate_when_clean', false), 'boolean', 'In Hybrid mode, allow clean deterministic checks to escalate to configured provider where appropriate');

        $add('ai_default_model', config('ai.default_model', ''), 'string', 'Default model for the global provider; blank uses the provider default model');
        $add('ai_fallback_model', config('ai.fallback_model', ''), 'string', 'Model used by the first fallback provider; blank uses provider default');
        $add('ai_secondary_fallback_provider', config('ai.secondary_fallback_provider', ''), 'string', 'Optional secondary external fallback provider');
        $add('ai_secondary_fallback_model', config('ai.secondary_fallback_model', ''), 'string', 'Model used by the secondary fallback provider');
        $add('ai_automatic_failover', true, 'boolean', 'Automatically use configured fallback providers when the primary fails');
        $add('ai_provider_health_enabled', true, 'boolean', 'Cache and expose provider health checks to authorized administrators');
        $add('ai_retry_count', config('ai.retry_count', 1), 'integer', 'Retry count for retryable provider/network errors');
        $add('ai_retry_delay_ms', config('ai.retry_delay_ms', 300), 'integer', 'Base retry delay in milliseconds');
        $add('ai_temperature', config('ai.temperature', 0.2), 'decimal', 'Global default AI generation temperature');
        $add('ai_context_limit', config('ai.context_limit', 16000), 'integer', 'Maximum context budget used by AI features');
        $add('ai_grounding_enabled', true, 'boolean', 'Enable grounded/RAG behavior for features that support grounding');
        $add('ai_web_research_enabled', false, 'boolean', 'External live-web research availability. Current build has no live web-search adapter.');
        $add('ai_global_system_prompt', 'You are AcadFlow AI Academic Assistant. Respect authorization, tenant boundaries, academic integrity, source grounding, and uncertainty.', 'string', 'Global AI instruction composed before feature-specific prompts');
        $add('ai_rate_limit_per_minute', config('ai.rate_limit_per_minute', 20), 'integer', 'Per-user AI request rate limit per minute');
        $add('ai_editor_suggestions_enabled', true, 'boolean', 'Enable reviewable AI suggestions in rich-text editors');
        $add('ai_editor_suggestion_min_chars', 60, 'integer', 'Minimum editor characters before requesting a writing suggestion');
        $add('ai_editor_suggestion_delay_ms', 1600, 'integer', 'Debounce delay for editor AI suggestions');
        $add('ai_min_word_count', 200, 'integer', 'Minimum expected academic document word count for deterministic validation');
        $add('ai_max_word_count', 20000, 'integer', 'Maximum expected academic document word count for deterministic validation');
        $add('ai_institution_required_sections', '', 'string', 'Optional comma-separated institution-required sections for rule validation');

        $add('ai_layout_required_fonts', config('ai.layout_requirements.required_fonts', ['Times New Roman','Arial']), 'json', 'Institution default fonts used by AI layout validation');
        $add('ai_layout_page_size', config('ai.layout_requirements.page_size', 'A4'), 'string', 'Institution default page size used by AI layout validation');
        $add('ai_layout_min_margin_inches', config('ai.layout_requirements.min_margin_inches', 1.0), 'decimal', 'Minimum margin used by AI layout validation');
        $add('ai_layout_line_spacing', config('ai.layout_requirements.line_spacing', '1.5'), 'string', 'Required line spacing used by AI layout validation');
        $add('ai_layout_min_font_size', config('ai.layout_requirements.min_font_size_pt', 10), 'integer', 'Minimum font size used by AI layout validation');
        $add('ai_layout_require_page_numbering', config('ai.layout_requirements.require_page_numbering', false), 'boolean', 'Require page numbering during AI layout validation');
        $add('ai_layout_require_branding', config('ai.layout_requirements.require_institution_branding', false), 'boolean', 'Require institution branding during AI layout validation');

        foreach (['academic','assignment','research','project','siwes','seminar','citation','formatting','template','knowledge_hub','layout','deadline','institution','discussion','plagiarism'] as $rulePack) {
            $add('ai_rulepack_'.$rulePack, true, 'boolean', 'Enable deterministic '.$rulePack.' AI rule pack');
        }

        foreach (AiProviderName::cases() as $case) {
            $provider = $case->value;
            if ($provider === AiProviderName::RULE_BASED->value) continue;
            $bootstrap = (array) config('ai.providers.'.$provider, []);
            $model = (string) ($bootstrap['model'] ?? '');
            $configured = $provider === AiProviderName::OLLAMA->value
                ? trim((string) ($bootstrap['endpoint'] ?? '')) !== ''
                : ($provider === AiProviderName::AZURE_OPENAI->value
                    ? trim((string) ($bootstrap['api_key'] ?? '')) !== '' && trim((string) ($bootstrap['endpoint'] ?? '')) !== ''
                    : trim((string) ($bootstrap['api_key'] ?? '')) !== '');

            $add('ai_provider_'.$provider.'_enabled', $configured, 'boolean', $case->label().' is available for central provider routing');
            $add('ai_provider_'.$provider.'_model', $model, 'string', 'Default configured model/deployment for '.$case->label());
            $add('ai_provider_'.$provider.'_models', array_values(array_filter([$model])), 'json', 'Allowed configured models/deployments for '.$case->label());
            $add('ai_provider_'.$provider.'_temperature', $bootstrap['temperature'] ?? config('ai.temperature', 0.2), 'decimal', 'Provider-specific generation temperature');
            $add('ai_provider_'.$provider.'_base_url', '', 'string', 'Optional provider endpoint/base URL override; blank uses secure bootstrap configuration');
            $add('ai_provider_'.$provider.'_api_key', '', 'string', 'Encrypted provider credential override; blank uses secure environment bootstrap credential');
        }

        foreach ((array) config('ai.features', []) as $feature) {
            $add('ai_feature_'.$feature, true, 'boolean', 'Enable '.$feature.' AI capability');
            $add('ai_feature_'.$feature.'_provider', 'global', 'string', 'Provider routing for '.$feature.'; global inherits the AI default provider');
            $add('ai_feature_'.$feature.'_model', 'global', 'string', 'Model routing for '.$feature.'; global inherits provider/default model');
            $add('ai_feature_'.$feature.'_rule_fallback', true, 'boolean', 'Allow explicit deterministic fallback in Hybrid mode for '.$feature);
        }

        foreach (array_chunk($rows, 100) as $chunk) DB::table('settings')->insertOrIgnore($chunk);

        // Any canonical AI rows created by older controllers as `general`
        // settings are moved into the one AI Settings area without changing their
        // values. Legacy keys are moved again to ai_legacy below.
        DB::table('settings')->where('key', 'like', 'ai_%')->update([
            'group' => 'ai',
            'updated_at' => $now,
        ]);

        // Normalize known legacy metadata without changing configured values.
        DB::table('settings')->where('key', 'ai_max_cost')->update([
            'type' => 'decimal',
            'updated_at' => $now,
        ]);

        // Preserve legacy values for audit/backward compatibility but remove them
        // from the authoritative AI Settings UI/runtime path.
        $legacy = [
            'ai_enable_external_ai' => 'Deprecated: AI mode and provider enablement now control external AI usage.',
            'ai_enable_hybrid_mode' => 'Deprecated: ai_mode=hybrid is the authoritative Hybrid setting.',
            'ai_enable_rule_engine' => 'Deprecated: ai_mode=rule_based or explicit Hybrid fallback controls the Rule Engine.',
            'ai_provider_priority' => 'Deprecated: routing is now primary -> fallback -> secondary fallback so Default Provider is always honored.',
            'ai_max_document_size_mb' => 'Deprecated: no active AcadFlow AI document-upload feature consumes this setting; normal upload limits remain in the storage/media configuration.',
            'ai_document_formats' => 'Deprecated: no active AcadFlow AI document-upload feature consumes this setting; accepted upload types remain controlled by the actual upload feature.',
        ];
        foreach ($legacy as $key => $description) {
            DB::table('settings')->where('key', $key)->update([
                'group' => 'ai_legacy',
                'description' => $description,
                'updated_at' => $now,
            ]);
        }

        // Older releases exposed several AI feature switches that had no
        // application entry point. Preserve the values for audit/history, but
        // remove them from the active AI Settings matrix so every visible switch
        // has a real runtime consumer.
        foreach ([
            'project_assistant', 'siwes_assistant', 'material_assistant',
            'discussion_assistant', 'ai_search', 'ai_analytics',
            'research_assistant', 'literature_review', 'moderation_assistant',
            'recommendation_assistant', 'semantic_discovery',
        ] as $inactiveFeature) {
            DB::table('settings')
                ->whereIn('key', [
                    'ai_feature_'.$inactiveFeature,
                    'ai_feature_'.$inactiveFeature.'_provider',
                    'ai_feature_'.$inactiveFeature.'_model',
                    'ai_feature_'.$inactiveFeature.'_rule_fallback',
                ])
                ->update([
                    'group' => 'ai_legacy',
                    'description' => 'Deprecated inactive feature configuration retained for backward compatibility; this build has no live AI entry point for '.$inactiveFeature.'.',
                    'updated_at' => $now,
                ]);
        }

        // Older seeders used rule_based as the fallback provider. In the new
        // architecture deterministic fallback is an explicit Hybrid per-feature
        // setting, so translate that legacy value to no external fallback.
        DB::table('settings')
            ->where('key', 'ai_fallback_provider')
            ->where('value', 'rule_based')
            ->update(['value' => 'none', 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Production-safe: do not delete settings or restore competing legacy
        // runtime controls. Rollback intentionally leaves values in place.
    }
};
