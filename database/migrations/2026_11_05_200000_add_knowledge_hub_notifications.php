<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge Hub notifications: per-user switches for "new from creators I
 * follow" and the weekly roundup, plus editable event templates so admins can
 * turn each one, and its email/push channels, on or off. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('notification_settings', 'knowledge_updates')) {
                $table->boolean('knowledge_updates')->default(true);
            }
            if (! Schema::hasColumn('notification_settings', 'knowledge_digest')) {
                $table->boolean('knowledge_digest')->default(true);
            }
        });

        if (Schema::hasTable('notification_event_templates')) {
            foreach ([
                'knowledge_new_from_followed' => 'New posts from creators a user follows',
                'knowledge_weekly_digest' => 'Weekly "New on the Knowledge Hub" roundup',
            ] as $type => $label) {
                if (! DB::table('notification_event_templates')->where('event_type', $type)->exists()) {
                    DB::table('notification_event_templates')->insert([
                        'event_type' => $type, 'enabled' => true, 'title' => '{title}', 'message' => '{message}',
                        'channels' => json_encode(['email', 'push']), 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table): void {
            foreach (['knowledge_updates', 'knowledge_digest'] as $column) {
                if (Schema::hasColumn('notification_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        if (Schema::hasTable('notification_event_templates')) {
            DB::table('notification_event_templates')->whereIn('event_type', ['knowledge_new_from_followed', 'knowledge_weekly_digest'])->delete();
        }
    }
};
