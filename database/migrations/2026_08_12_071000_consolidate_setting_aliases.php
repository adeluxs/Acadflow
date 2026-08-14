<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) return;

        $aliases = [
            'logo_url' => 'site_logo',
            'favicon_url' => 'site_favicon',
            'notifications_email_enabled' => 'email_notifications_enabled',
            'enable_email_notifications' => 'email_notifications_enabled',
            'notifications_push_enabled' => 'push_notifications_enabled',
            'enable_push_notifications' => 'push_notifications_enabled',
            'notifications_in_app_enabled' => 'in_app_notifications_enabled',
            'enable_in_app_notifications' => 'in_app_notifications_enabled',
            'deadline_reminder_hours' => 'reminder_before_deadline_hours',
            'require_2fa' => 'enable_two_factor',
            'require_password_uppercase' => 'password_require_uppercase',
            'require_password_number' => 'password_require_numbers',
            'require_password_special' => 'password_require_special',
            'retention_days' => 'file_retention_days',
            'enable_offline_mode' => 'pwa_cache_enabled',
            'offline_page_cache' => 'pwa_cache_enabled',
            'background_sync_enabled' => 'pwa_offline_sync',
        ];

        DB::transaction(function () use ($aliases): void {
            foreach ($aliases as $legacy => $canonical) {
                $old = DB::table('settings')->where('key', $legacy)->first();
                if (! $old) continue;

                $target = DB::table('settings')->where('key', $canonical)->first();
                if (! $target) {
                    DB::table('settings')->where('id', $old->id)->update(['key' => $canonical]);
                    continue;
                }

                if (Schema::hasTable('setting_overrides')) {
                    $overrides = DB::table('setting_overrides')->where('setting_id', $old->id)->get();
                    foreach ($overrides as $override) {
                        $exists = DB::table('setting_overrides')
                            ->where('setting_id', $target->id)
                            ->where('university_id', $override->university_id)
                            ->exists();
                        if (! $exists) {
                            DB::table('setting_overrides')->where('id', $override->id)->update(['setting_id' => $target->id]);
                        } else {
                            DB::table('setting_overrides')->where('id', $override->id)->delete();
                        }
                    }
                }

                DB::table('settings')->where('id', $old->id)->delete();
            }

            // Academic session/semester selection is already authoritative in the
            // academic_sessions/semesters tables. These legacy settings created a
            // second source of truth and could show stale dates in the admin UI.
            $obsolete = ['default_academic_year', 'current_semester', 'semester_start_date', 'semester_end_date'];
            $obsoleteIds = DB::table('settings')->whereIn('key', $obsolete)->pluck('id');
            if (Schema::hasTable('setting_overrides') && $obsoleteIds->isNotEmpty()) {
                DB::table('setting_overrides')->whereIn('setting_id', $obsoleteIds)->delete();
            }
            DB::table('settings')->whereIn('key', $obsolete)->delete();
        });
    }

    public function down(): void
    {
        // Intentional no-op: aliases were duplicate configuration paths. The
        // canonical keys remain backward compatible through SettingService.
    }
};
