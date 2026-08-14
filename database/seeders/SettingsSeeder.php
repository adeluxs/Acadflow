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

            // Subscription Settings
            'currency' => ['value' => 'USD', 'type' => 'string', 'group' => 'subscription', 'description' => 'Default currency'],
            'tax_rate' => ['value' => 0, 'type' => 'integer', 'group' => 'subscription', 'description' => 'Tax rate percentage'],
            'grace_period_days' => ['value' => 7, 'type' => 'integer', 'group' => 'subscription', 'description' => 'Payment grace period in days'],
            'trial_days' => ['value' => 14, 'type' => 'integer', 'group' => 'subscription', 'description' => 'Free trial period in days'],
            'allow_department_licenses' => ['value' => true, 'type' => 'boolean', 'group' => 'subscription', 'description' => 'Allow department-level licensing'],
            'allow_plan_downgrade' => ['value' => true, 'type' => 'boolean', 'group' => 'subscription', 'description' => 'Allow users to downgrade plans'],
            'prorate_downgrades' => ['value' => false, 'type' => 'boolean', 'group' => 'subscription', 'description' => 'Prorate downgrade refunds'],

            // Security Settings
            'password_min_length' => ['value' => 8, 'type' => 'integer', 'group' => 'security', 'description' => 'Minimum password length'],
            'password_require_uppercase' => ['value' => true, 'type' => 'boolean', 'group' => 'security', 'description' => 'Require uppercase in passwords'],
            'password_require_numbers' => ['value' => true, 'type' => 'boolean', 'group' => 'security', 'description' => 'Require numbers in passwords'],
            'password_require_special' => ['value' => false, 'type' => 'boolean', 'group' => 'security', 'description' => 'Require special characters in passwords'],
            'session_timeout_minutes' => ['value' => 120, 'type' => 'integer', 'group' => 'security', 'description' => 'Session timeout in minutes'],
            'enable_two_factor' => ['value' => false, 'type' => 'boolean', 'group' => 'security', 'description' => 'Enable two-factor authentication'],
            'max_login_attempts' => ['value' => 5, 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum login attempts before lockout'],
            'lockout_duration_minutes' => ['value' => 15, 'type' => 'integer', 'group' => 'security', 'description' => 'Account lockout duration in minutes'],
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
            'ai_mode' => ['value' => 'rule_based', 'type' => 'string', 'group' => 'ai', 'description' => 'AI mode: rule_based, provider, hybrid, disabled'],
            'ai_default_provider' => ['value' => 'rule_based', 'type' => 'string', 'group' => 'ai', 'description' => 'Default AI provider'],
            'ai_fallback_provider' => ['value' => 'rule_based', 'type' => 'string', 'group' => 'ai', 'description' => 'Fallback AI provider'],
            'ai_similarity_threshold' => ['value' => 20, 'type' => 'integer', 'group' => 'ai', 'description' => 'Plagiarism similarity threshold (%)'],
            'ai_request_timeout' => ['value' => 30, 'type' => 'integer', 'group' => 'ai', 'description' => 'Provider request timeout (seconds)'],
            'ai_max_tokens' => ['value' => 2048, 'type' => 'integer', 'group' => 'ai', 'description' => 'Maximum tokens per request'],
            'ai_daily_request_limit' => ['value' => 1000, 'type' => 'integer', 'group' => 'ai', 'description' => 'Daily AI request limit'],
            'ai_monthly_request_limit' => ['value' => 30000, 'type' => 'integer', 'group' => 'ai', 'description' => 'Monthly AI request limit'],
            'ai_max_cost' => ['value' => 100, 'type' => 'integer', 'group' => 'ai', 'description' => 'Maximum monthly AI cost (USD)'],
            'ai_enable_rule_engine' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable rule-based engine'],
            'ai_enable_external_ai' => ['value' => false, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable external AI providers'],
            'ai_enable_hybrid_mode' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable hybrid AI mode'],
            'ai_enable_cache' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI response caching'],
            'ai_enable_logging' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI usage logging'],
            'ai_feature_submission_validator' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable submission validator'],
            'ai_feature_plagiarism' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable plagiarism detection'],
            'ai_feature_writing_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable writing assistant'],
            'ai_editor_suggestions_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Show reviewable AI writing suggestions while using rich-text editors'],
            'ai_editor_suggestion_min_chars' => ['value' => 60, 'type' => 'integer', 'group' => 'ai', 'description' => 'Minimum editor characters before AI suggestions are requested'],
            'ai_editor_suggestion_delay_ms' => ['value' => 1600, 'type' => 'integer', 'group' => 'ai', 'description' => 'Debounce delay for AI editor suggestions in milliseconds'],
            'ai_feature_citation_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable citation assistant'],
            'ai_feature_project_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable project assistant'],
            'ai_feature_siwes_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable SIWES assistant'],
            'ai_feature_study_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable study assistant'],
            'ai_feature_material_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable material assistant'],
            'ai_feature_lecturer_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable lecturer assistant'],
            'ai_feature_discussion_assistant' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable discussion assistant'],
            'ai_feature_ai_search' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI semantic search'],
            'ai_feature_ai_analytics' => ['value' => true, 'type' => 'boolean', 'group' => 'ai', 'description' => 'Enable AI analytics'],
        ];

        $settings = array_merge($settings, $aiSettings);

        foreach ($settings as $key => $data) {
            Setting::updateOrCreate(
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
