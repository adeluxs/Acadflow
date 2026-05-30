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
            'site_name' => ['value' => 'UniFlow', 'type' => 'string', 'group' => 'general', 'description' => 'Platform display name'],
            'site_tagline' => ['value' => 'University Academic Workflow Platform', 'type' => 'string', 'group' => 'general', 'description' => 'Tagline or slogan'],
            'support_email' => ['value' => 'support@uniflow.edu', 'type' => 'string', 'group' => 'general', 'description' => 'Support contact email'],
            'timezone' => ['value' => 'UTC', 'type' => 'string', 'group' => 'general', 'description' => 'Default timezone'],
            'maintenance_mode' => ['value' => false, 'type' => 'boolean', 'group' => 'general', 'description' => 'Enable maintenance mode'],
            'site_logo' => ['value' => '', 'type' => 'string', 'group' => 'general', 'description' => 'Site logo URL or path'],
            'site_favicon' => ['value' => '', 'type' => 'string', 'group' => 'general', 'description' => 'Favicon URL or path'],
            'default_language' => ['value' => 'en', 'type' => 'string', 'group' => 'general', 'description' => 'Default language code'],

            // Academic Settings
            'default_submission_late_penalty' => ['value' => 10, 'type' => 'integer', 'group' => 'academic', 'description' => 'Late submission penalty percentage per day'],
            'allow_late_submissions' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Allow students to submit after deadline'],
            'max_attempts_per_assignment' => ['value' => 3, 'type' => 'integer', 'group' => 'academic', 'description' => 'Maximum submission attempts'],
            'auto_grade_assignments' => ['value' => false, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Automatically grade objective assignments'],
            'default_academic_year' => ['value' => '2025/2026', 'type' => 'string', 'group' => 'academic', 'description' => 'Default academic year format: YYYY/YYYY'],
            'current_semester' => ['value' => 'first', 'type' => 'string', 'group' => 'academic', 'description' => 'Current semester: first, second, or summer'],
            'semester_start_date' => ['value' => '2025-09-01', 'type' => 'string', 'group' => 'academic', 'description' => 'Current semester start date'],
            'semester_end_date' => ['value' => '2026-01-31', 'type' => 'string', 'group' => 'academic', 'description' => 'Current semester end date'],
            'allow_resubmission' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Allow students to resubmit after grading'],
            'max_resubmission_attempts' => ['value' => 2, 'type' => 'integer', 'group' => 'academic', 'description' => 'Maximum resubmission attempts'],
            'require_correction_workflow' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Enable correction request workflow'],
            'enable_group_submissions' => ['value' => true, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Allow group submissions'],
            'default_grading_scale' => ['value' => '100', 'type' => 'string', 'group' => 'academic', 'description' => 'Default grading scale (100, 4.0, etc.)'],
            'show_grades_immediately' => ['value' => false, 'type' => 'boolean', 'group' => 'academic', 'description' => 'Show grades to students immediately after grading'],

            // Notification Settings
            'email_notifications_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Send email notifications'],
            'push_notifications_enabled' => ['value' => false, 'type' => 'boolean', 'group' => 'notification', 'description' => 'Send push notifications'],
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

            // PWA Settings
            'pwa_enabled' => ['value' => true, 'type' => 'boolean', 'group' => 'pwa', 'description' => 'Enable PWA installation'],
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
