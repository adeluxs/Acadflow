<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General Settings
            'site_name' => ['value' => 'AcadFlow', 'type' => 'string', 'group' => 'general', 'description' => 'Platform display name'],
            'site_tagline' => ['value' => 'Academic research, publishing and collaboration platform', 'type' => 'string', 'group' => 'general', 'description' => 'Tagline or slogan'],
            'support_email' => ['value' => 'support@uniflow.edu', 'type' => 'string', 'group' => 'general', 'description' => 'Support contact email'],
            'timezone' => ['value' => 'UTC', 'type' => 'string', 'group' => 'general', 'description' => 'Default timezone'],
            'maintenance_mode' => ['value' => false, 'type' => 'boolean', 'group' => 'general', 'description' => 'Enable maintenance mode'],
            'maintenance_mode_bypass_routes' => ['value' => 'login,logout,api/*', 'type' => 'string', 'group' => 'general', 'description' => 'Comma-separated routes allowed during maintenance mode'],
            'site_logo' => ['value' => '', 'type' => 'string', 'group' => 'general', 'description' => 'Site logo URL or path'],
            'site_favicon' => ['value' => '', 'type' => 'string', 'group' => 'general', 'description' => 'Favicon URL or path'],
            'default_language' => ['value' => 'en', 'type' => 'string', 'group' => 'general', 'description' => 'Default language code'],
            'primary_color' => ['value' => '#4f46e5', 'type' => 'string', 'group' => 'general', 'description' => 'Primary interface color used for focus, active navigation, buttons, and accents'],

            // Academic Settings
            'default_submission_late_penalty' => ['value' => 10, 'type' => 'integer', 'group' => 'academic', 'description' => 'Late submission penalty percentage per day'],
            'allow_late_submissions' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Allow students to submit after deadline'],
            'max_attempts_per_assignment' => ['value' => 3, 'type' => 'integer', 'group' => 'academic', 'description' => 'Maximum submission attempts'],
            'auto_grade_assignments' => ['value' => false, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Automatically grade objective assignments'],
            'allow_resubmission' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Allow students to resubmit after grading'],
            'max_resubmission_attempts' => ['value' => 2, 'type' => 'integer', 'group' => 'academic', 'description' => 'Maximum resubmission attempts'],
            'require_correction_workflow' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Enable correction request workflow'],
            'enable_group_submissions' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Allow group submissions'],
            'default_grading_scale' => ['value' => '100', 'type' => 'string', 'group' => 'academic', 'description' => 'Default grading scale (100, 4.0, etc.)'],
            'gpa_scale' => ['value' => 5, 'type' => 'integer', 'group' => 'academic', 'description' => 'Maximum GPA/CGPA scale'],
            'gpa_grade_bands' => ['value' => json_encode([['min'=>70,'point'=>5],['min'=>60,'point'=>4],['min'=>50,'point'=>3],['min'=>45,'point'=>2],['min'=>40,'point'=>1],['min'=>0,'point'=>0]]), 'type' => 'json', 'group' => 'academic', 'description' => 'Institution GPA conversion bands from percentage to grade point'],
            'show_grades_immediately' => ['value' => false, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Show grades to students immediately after grading'],
            'lecturer_self_assignment_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Allow lecturers to add themselves to courses in their own institution and department'],
            'restrict_course_membership_to_department' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Restrict lecturer and student course membership to their assigned department'],
            'course_invitation_expiry_days' => ['value' => 7, 'type' => 'integer', 'group' => 'academic', 'description' => 'Number of days before lecturer course invitations expire'],

            // Notification Settings
            'email_notifications_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Send email notifications'],
            'push_notifications_enabled' => ['value' => false, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Send push notifications'],
            'in_app_notifications_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Show in-app notifications'],
            'digest_frequency' => ['value' => 'daily', 'type' => 'string', 'group' => 'notification', 'description' => 'Notification digest frequency (daily, weekly)'],
            'reminder_before_deadline_hours' => ['value' => 24, 'type' => 'integer', 'group' => 'notification', 'description' => 'Send deadline reminder hours before'],
            'notify_on_submission' => ['value' => true, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Notify lecturer on new submission'],
            'notify_on_grade' => ['value' => true, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Notify student when graded'],
            'notify_on_correction_request' => ['value' => true, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Notify on correction requests'],
            'admin_announcement_channels' => ['value' => 'email,push,in_app', 'type' => 'string', 'group' => 'notification', 'description' => 'Admin announcement channels (comma-separated)'],

            // Monetization defaults (Nigeria-first; runtime values remain Admin-configurable)
            'currency' => ['value' => 'NGN', 'type' => 'string', 'group' => 'monetization', 'description' => 'Default commercial currency'],
            'tax_rate' => ['value' => 0, 'type' => 'integer', 'group' => 'monetization', 'description' => 'Default tax rate percentage where applicable'],
            'wallet_minimum_funding_amount' => ['value' => '500', 'type' => 'decimal', 'group' => 'monetization', 'description' => 'Minimum wallet funding amount in the configured commercial currency'],
            'minimum_withdrawal_amount' => ['value' => '1000', 'type' => 'decimal', 'group' => 'monetization', 'description' => 'Minimum creator withdrawal amount in the configured commercial currency'],
            'withdrawal_fee_percentage' => ['value' => '0', 'type' => 'decimal', 'group' => 'monetization', 'description' => 'Withdrawal service fee percentage'],
            'knowledge_platform_commission_percentage' => ['value' => '15', 'type' => 'decimal', 'group' => 'monetization', 'description' => 'Marketplace platform commission percentage'],
            'knowledge_institution_revenue_percentage' => ['value' => '0', 'type' => 'decimal', 'group' => 'monetization', 'description' => 'Marketplace institution revenue-share percentage'],
            'creator_earnings_hold_days' => ['value' => 3, 'type' => 'integer', 'group' => 'monetization', 'description' => 'Settlement hold before creator earnings become withdrawable'],
            'ai_monetization_enabled' => ['value' => false, 'type' => 'boolean', 'group' => 'monetization', 'description' => 'Charge for provider-backed AI after the free allowance'],
            'ai_free_daily_requests' => ['value' => 3, 'type' => 'integer', 'group' => 'monetization', 'description' => 'Daily provider-backed AI requests included at no charge'],
            'ai_request_charge_amount' => ['value' => '25', 'type' => 'decimal', 'group' => 'monetization', 'description' => 'Default AI request charge in the configured commercial currency'],
            'ai_local_currency_per_usd_reporting' => ['value' => '1500', 'type' => 'decimal', 'group' => 'monetization', 'description' => 'Local-currency/USD reporting conversion rate for provider cost estimates'],

            // Academic grace window (not a billing/subscription entitlement)
            'grace_period_days' => ['value' => 7, 'type' => 'integer', 'group' => 'academic', 'description' => 'Academic semester/submission grace period in days'],

            // Security Settings
            'password_min_length' => ['value' => 8, 'type' => 'integer', 'group' => 'security', 'description' => 'Minimum password length'],
            'password_require_uppercase' => ['value' => true, 'type' => 'boolean', 'group' => 'security', 'description' => 'Require uppercase in passwords'],
            'password_require_numbers' => ['value' => true, 'type' => 'boolean', 'group' => 'security', 'description' => 'Require numbers in passwords'],
            'password_require_special' => ['value' => false, 'type' => 'boolean', 'group' => 'security', 'description' => 'Require special characters in passwords'],
            'session_timeout_minutes' => ['value' => 120, 'type' => 'integer', 'group' => 'security', 'description' => 'Session timeout in minutes'],
            'enable_two_factor' => ['value' => false, 'type' => 'boolean', 'group' => 'security', 'description' => 'Enable two-factor authentication'],
            'max_login_attempts' => ['value' => 5, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum failed login attempts before temporary lockout'],
            'lockout_duration_minutes' => ['value' => 15, 'type' => 'integer', 'group' => 'security', 'description' => 'Temporary login lockout duration in minutes'],
            'login_requests_per_minute' => ['value' => 10, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum login requests per email/IP combination per minute'],
            'registration_requests_per_hour' => ['value' => 5, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum registration requests per IP address per hour'],
            'password_reset_requests_per_minute' => ['value' => 5, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum password-reset requests per email/IP combination per minute'],
            'verification_requests_per_minute' => ['value' => 6, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum email-verification requests per user or IP address per minute'],
            'two_factor_attempts_per_minute' => ['value' => 5, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum two-factor verification attempts per user or IP address per minute'],
            'enable_audit_logs' => ['value' => true, 'type' => 'boolean', 'group' => 'security', 'description' => 'Enable audit logging'],
            'audit_log_retention_days' => ['value' => 365, 'type' => 'integer', 'group' => 'security', 'description' => 'Audit log retention period in days'],
            'max_concurrent_sessions' => ['value' => 3, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum concurrent sessions per user'],

            // PWA Settings
            'pwa_cache_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'pwa', 'description' => 'Enable service worker caching'],
            'pwa_cache_duration_hours' => ['value' => 24, 'type' => 'integer', 'group' => 'pwa', 'description' => 'Cache duration in hours'],
            'pwa_offline_sync' => ['value' => true, 'type' => 'boolean', 'group' => 'pwa', 'description' => 'Enable offline sync'],
            'pwa_sync_retry_attempts' => ['value' => 3, 'type' => 'integer', 'group' => 'pwa', 'description' => 'Sync retry attempts'],
            'pwa_sync_retry_delay_seconds' => ['value' => 60, 'type' => 'integer', 'group' => 'pwa', 'description' => 'Delay between sync retries in seconds'],
            'pwa_background_updates' => ['value' => true, 'type' => 'boolean', 'group' => 'pwa', 'description' => 'Enable background updates'],
            'pwa_cache_assets' => ['value' => true, 'type' => 'boolean', 'group' => 'pwa', 'description' => 'Cache static assets'],
            'pwa_theme_color' => ['value' => '#4f46e5', 'type' => 'string', 'group' => 'pwa', 'description' => 'PWA theme color'],
            'pwa_background_color' => ['value' => '#ffffff', 'type' => 'string', 'group' => 'pwa', 'description' => 'PWA background color'],
            'pwa_display' => ['value' => 'standalone', 'type' => 'string', 'group' => 'pwa', 'description' => 'PWA display mode (fullscreen, standalone, minimal-ui, browser)'],
            'pwa_orientation' => ['value' => 'portrait-primary', 'type' => 'string', 'group' => 'pwa', 'description' => 'PWA orientation'],

            // Storage Settings
            'max_file_upload_size_mb' => ['value' => 50, 'type' => 'integer', 'group' => 'storage', 'description' => 'Maximum file upload size in MB'],
            'allowed_file_extensions' => ['value' => 'pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,png,mp4,mp3', 'type' => 'string', 'group' => 'storage', 'description' => 'Comma-separated allowed extensions'],
            'max_storage_gb_per_user' => ['value' => 5, 'type' => 'integer', 'group' => 'storage', 'description' => 'Max storage per user in GB'],
            'enable_file_retention' => ['value' => true, 'type' => 'boolean', 'group' => 'storage', 'description' => 'Enable file retention policy'],
            'file_retention_days' => ['value' => 180, 'type' => 'integer', 'group' => 'storage', 'description' => 'File retention period in days'],
            'enable_archive' => ['value' => false, 'type' => 'boolean', 'group' => 'storage', 'description' => 'Enable automatic archiving'],
            'archive_after_days' => ['value' => 365, 'type' => 'integer', 'group' => 'storage', 'description' => 'Archive files after days'],
        ];

        // AI Academic Assistant Settings (Phase 6)
        $aiSettings = [
            'ai_mode' => ['value' => config('ai.default_mode', 'rule_based'), 'type' => 'string', 'group' => 'ai', 'description' => 'AI mode: rule_based, provider, hybrid, disabled'],
            'ai_default_provider' => ['value' => config('ai.default_provider', 'rule_based'), 'type' => 'string', 'group' => 'ai', 'description' => 'Default AI provider'],
            'ai_fallback_provider' => ['value' => config('ai.fallback_provider', '') ?: 'none', 'type' => 'string', 'group' => 'ai', 'description' => 'Fallback AI provider'],
            'ai_similarity_threshold' => ['value' => config('ai.similarity_threshold', 20), 'type' => 'integer', 'group' => 'ai', 'description' => 'Plagiarism similarity threshold (%)'],
            'ai_request_timeout' => ['value' => config('ai.request_timeout', 30), 'type' => 'integer', 'group' => 'ai', 'description' => 'Provider request timeout (seconds)'],
            'ai_max_tokens' => ['value' => config('ai.max_tokens', 2048), 'type' => 'integer', 'group' => 'ai', 'description' => 'Maximum tokens per request'],
            'ai_daily_request_limit' => ['value' => config('ai.daily_request_limit', 1000), 'type' => 'integer', 'group' => 'ai', 'description' => 'Daily AI request limit'],
            'ai_monthly_request_limit' => ['value' => config('ai.monthly_request_limit', 30000), 'type' => 'integer', 'group' => 'ai', 'description' => 'Monthly AI request limit'],
            'ai_max_cost' => ['value' => config('ai.max_cost', 100.0), 'type' => 'decimal', 'group' => 'ai', 'description' => 'Maximum monthly AI cost (USD)'],
            'ai_enable_cache' => ['value' => config('ai.enable_cache', true), 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI response caching'],
            'ai_enable_logging' => ['value' => config('ai.enable_logging', true), 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI usage logging'],
            'ai_hybrid_escalate_when_clean' => ['value' => config('ai.hybrid_escalate_when_clean', false), 'type' => 'boolean', 'group' => 'ai', 'description' => 'Allow clean Hybrid checks to escalate to configured provider where appropriate'],
            'ai_layout_required_fonts' => ['value' => json_encode(config('ai.layout_requirements.required_fonts', ['Times New Roman','Arial'])), 'type' => 'json', 'group' => 'ai', 'description' => 'Institution default fonts used by AI layout validation'],
            'ai_layout_page_size' => ['value' => config('ai.layout_requirements.page_size', 'A4'), 'type' => 'string', 'group' => 'ai', 'description' => 'Institution default page size used by AI layout validation'],
            'ai_layout_min_margin_inches' => ['value' => config('ai.layout_requirements.min_margin_inches', 1.0), 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum margin used by AI layout validation'],
            'ai_layout_line_spacing' => ['value' => config('ai.layout_requirements.line_spacing', '1.5'), 'type' => 'string', 'group' => 'ai', 'description' => 'Required line spacing used by AI layout validation'],
            'ai_layout_min_font_size' => ['value' => config('ai.layout_requirements.min_font_size_pt', 10), 'type' => 'integer', 'group' => 'ai', 'description' => 'Minimum font size used by AI layout validation'],
            'ai_layout_require_page_numbering' => ['value' => config('ai.layout_requirements.require_page_numbering', false), 'type' => 'boolean', 'group' => 'ai', 'description' => 'Require page numbering during AI layout validation'],
            'ai_layout_require_branding' => ['value' => config('ai.layout_requirements.require_institution_branding', false), 'type' => 'boolean', 'group' => 'ai', 'description' => 'Require institution branding during AI layout validation'],
            'ai_editor_suggestions_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Show reviewable AI writing suggestions while using rich-text editors'],
            'ai_editor_suggestion_min_chars' => ['value' => 60, 'type' => 'integer', 'group' => 'ai', 'description' => 'Minimum editor characters before AI suggestions are requested'],
            'ai_editor_suggestion_delay_ms' => ['value' => 1600, 'type' => 'integer', 'group' => 'ai', 'description' => 'Debounce delay for AI editor suggestions in milliseconds'],
            'ai_min_word_count' => ['value' => 200, 'type' => 'integer', 'group' => 'ai', 'description' => 'Minimum expected academic document word count for deterministic validation'],
            'ai_max_word_count' => ['value' => 20000, 'type' => 'integer', 'group' => 'ai', 'description' => 'Maximum expected academic document word count for deterministic validation'],
            'ai_institution_required_sections' => ['value' => '', 'type' => 'string', 'group' => 'ai', 'description' => 'Optional comma-separated sections required by institution rule validation'],
            'ai_grounded_pattern_learning_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Learn conservative retrieval patterns from successful grounded companion sessions'],
            'ai_grounded_min_question_chars' => ['value' => 3, 'type' => 'integer', 'group' => 'ai', 'description' => 'Minimum normalized question length for the Grounded AI Companion'],
            'ai_grounded_gibberish_threshold' => ['value' => 0.60, 'type' => 'decimal', 'group' => 'ai', 'description' => 'Maximum tolerated ratio of likely gibberish tokens before a grounded question is rejected'],
            'ai_grounded_relevance_threshold' => ['value' => 0.18, 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum publication-scoped retrieval relevance score for specific grounded questions'],
            'ai_grounded_lexical_floor' => ['value' => 0.20, 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum lexical evidence floor for a specific grounded question'],
            'ai_grounded_citation_coverage_min' => ['value' => 0.85, 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum fraction of substantive provider answer sentences that must contain valid source citations'],
            'ai_grounded_support_threshold' => ['value' => 0.20, 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum cited-sentence support score against the referenced source excerpt'],
            'ai_grounded_support_coverage_min' => ['value' => 0.70, 'type' => 'decimal', 'group' => 'ai', 'description' => 'Minimum fraction of cited sentences that must be supported by their referenced excerpts'],
            'ai_default_model' => ['value' => config('ai.default_model', ''), 'type' => 'string', 'group' => 'ai', 'description' => 'Default model; blank uses the selected provider model'],
            'ai_fallback_model' => ['value' => config('ai.fallback_model', ''), 'type' => 'string', 'group' => 'ai', 'description' => 'Fallback model; blank uses fallback provider model'],
            'ai_secondary_fallback_provider' => ['value' => config('ai.secondary_fallback_provider', ''), 'type' => 'string', 'group' => 'ai', 'description' => 'Optional secondary fallback provider'],
            'ai_secondary_fallback_model' => ['value' => config('ai.secondary_fallback_model', ''), 'type' => 'string', 'group' => 'ai', 'description' => 'Optional secondary fallback model'],
            'ai_automatic_failover' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Automatically fail over through configured external providers'],
            'ai_provider_health_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable cached provider health visibility'],
            'ai_retry_count' => ['value' => config('ai.retry_count', 1), 'type' => 'integer', 'group' => 'ai', 'description' => 'Retry count for retryable provider errors'],
            'ai_retry_delay_ms' => ['value' => config('ai.retry_delay_ms', 300), 'type' => 'integer', 'group' => 'ai', 'description' => 'Base provider retry delay in milliseconds'],
            'ai_fast_failover' => ['value' => config('ai.fast_failover', true), 'type' => 'boolean', 'group' => 'ai', 'description' => 'Advance to configured fallback providers quickly after retryable interactive provider failures'],
            'ai_temperature' => ['value' => config('ai.temperature', 0.2), 'type' => 'decimal', 'group' => 'ai', 'description' => 'Global default AI generation temperature'],
            'ai_context_limit' => ['value' => config('ai.context_limit', 16000), 'type' => 'integer', 'group' => 'ai', 'description' => 'Global AI context budget'],
            'ai_grounding_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable grounded AI where supported'],
            'ai_web_research_enabled' => ['value' => false, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Live external web research is unavailable until a dedicated web-search adapter is configured'],
            'ai_global_system_prompt' => ['value' => 'You are AcadFlow AI Academic Assistant. Respect authorization, tenant boundaries, academic integrity, source grounding, and uncertainty.', 'type' => 'string', 'group' => 'ai', 'description' => 'Global instruction composed before feature prompts'],
            'ai_rate_limit_per_minute' => ['value' => config('ai.rate_limit_per_minute', 20), 'type' => 'integer', 'group' => 'ai', 'description' => 'Per-user AI requests per minute'],
        ];

        foreach (['academic','assignment','research','project','siwes','seminar','citation','formatting','template','knowledge_hub','layout','deadline','institution','discussion','plagiarism'] as $rulePack) {
            $aiSettings['ai_rulepack_'.$rulePack] = ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable deterministic '.$rulePack.' AI rule pack'];
        }

        foreach (\App\Enums\AiProviderName::cases() as $providerCase) {
            $provider = $providerCase->value;
            if ($provider === \App\Enums\AiProviderName::RULE_BASED->value) continue;
            $bootstrap = (array) config('ai.providers.'.$provider, []);
            $model = (string) ($bootstrap['model'] ?? '');
            $configured = $provider === 'ollama'
                ? trim((string) ($bootstrap['endpoint'] ?? '')) !== ''
                : ($provider === 'azure_openai'
                    ? trim((string) ($bootstrap['api_key'] ?? '')) !== '' && trim((string) ($bootstrap['endpoint'] ?? '')) !== ''
                    : trim((string) ($bootstrap['api_key'] ?? '')) !== '');
            $aiSettings['ai_provider_'.$provider.'_enabled'] = ['value' => $configured, 'type' => 'boolean', 'group' => 'ai', 'description' => $providerCase->label().' is available for central routing'];
            $aiSettings['ai_provider_'.$provider.'_model'] = ['value' => $model, 'type' => 'string', 'group' => 'ai', 'description' => 'Default configured model/deployment for '.$providerCase->label()];
            $aiSettings['ai_provider_'.$provider.'_models'] = ['value' => json_encode(array_values(array_filter([$model]))), 'type' => 'json', 'group' => 'ai', 'description' => 'Allowed configured models/deployments for '.$providerCase->label()];
            $aiSettings['ai_provider_'.$provider.'_temperature'] = ['value' => $bootstrap['temperature'] ?? config('ai.temperature', 0.2), 'type' => 'decimal', 'group' => 'ai', 'description' => 'Provider-specific temperature'];
            $aiSettings['ai_provider_'.$provider.'_base_url'] = ['value' => '', 'type' => 'string', 'group' => 'ai', 'description' => 'Optional provider endpoint/base URL override'];
            $aiSettings['ai_provider_'.$provider.'_api_key'] = ['value' => '', 'type' => 'string', 'group' => 'ai', 'description' => 'Encrypted provider credential override; blank uses secure environment bootstrap'];
        }

        foreach ((array) config('ai.features', []) as $feature) {
            $aiSettings['ai_feature_'.$feature] ??= ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable '.$feature.' AI capability'];
            $aiSettings['ai_feature_'.$feature.'_provider'] = ['value' => 'global', 'type' => 'string', 'group' => 'ai', 'description' => 'Provider route; global inherits the default provider'];
            $aiSettings['ai_feature_'.$feature.'_model'] = ['value' => 'global', 'type' => 'string', 'group' => 'ai', 'description' => 'Model route; global inherits provider/default model'];
            $aiSettings['ai_feature_'.$feature.'_rule_fallback'] = ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Allow explicit Rule Engine fallback in Hybrid mode'];
        }

        $settings = array_merge($settings, $aiSettings);

        foreach ($settings as $key => $data) {
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $data['value'],
                    'type' => $data['type'],
                    'group' => $data['group'],
                    'description' => $data['description'] ?? null,
                ]
            );
        }
    }
}
