<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationDeliveryUpgradeTest extends TestCase
{
    public function test_upgrade_preserves_existing_notifications_preferences_and_delivery_records(): void
    {
        $connection = DB::getDefaultConnection();
        $schema = Schema::getFacadeRoot();
        config(['database.connections.notification_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('notification_upgrade');
        Schema::swap(DB::connection()->getSchemaBuilder());
        try {
            Schema::create('users', fn (Blueprint $t) => $t->id());
            Schema::create('notification_settings', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('user_id'); $t->boolean('email_enabled'); $t->boolean('push_enabled'); });
            Schema::create('notifications', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('user_id'); $t->string('title'); $t->text('message'); $t->string('type'); $t->timestamp('created_at'); });
            Schema::create('notification_logs', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('notification_id'); $t->string('channel'); $t->string('status'); $t->timestamps(); });
            DB::table('users')->insert(['id' => 7]);
            DB::table('notification_settings')->insert(['id' => 8, 'user_id' => 7, 'email_enabled' => false, 'push_enabled' => true]);
            DB::table('notifications')->insert(['id' => 10, 'user_id' => 7, 'title' => 'Existing grade', 'message' => 'Retained private academic update', 'type' => 'grade_posted', 'created_at' => now()]);
            DB::table('notification_logs')->insert(['id' => 12, 'user_id' => 7, 'notification_id' => 10, 'channel' => 'email', 'status' => 'success', 'created_at' => now(), 'updated_at' => now()]);
            $migration = require database_path('migrations/2026_10_21_210000_extend_notification_delivery_and_engagement.php');
            $migration->up();
            $this->assertSame('Retained private academic update', DB::table('notifications')->where('id', 10)->value('message'));
            $this->assertSame(1, DB::table('notifications')->where('id', 10)->value('in_app_visible'));
            $this->assertNull(DB::table('notifications')->where('id', 10)->value('event_key'));
            $this->assertSame(0, DB::table('notification_settings')->where('user_id', 7)->value('email_enabled'));
            $this->assertSame(0, DB::table('notification_settings')->where('user_id', 7)->value('engagement_reminders'));
            $this->assertSame(0, DB::table('notification_settings')->where('user_id', 7)->value('email_open_tracking'));
            $this->assertSame('success', DB::table('notification_logs')->where('id', 12)->value('status'));
            $this->assertNull(DB::table('notification_logs')->where('id', 12)->value('accepted_at'), 'Legacy success cannot be fabricated into a provider acceptance timestamp.');
            $this->assertSame(0, DB::table('notification_events')->count());
            $this->assertTrue(Schema::hasIndex('notification_logs', 'notification_delivery_dispatch'));
            $plan = DB::select("EXPLAIN QUERY PLAN SELECT id FROM notification_logs WHERE status = 'queued' AND next_attempt_at <= ? ORDER BY id LIMIT 100", [now()]);
            $this->assertStringContainsString('notification_delivery_dispatch', json_encode($plan));
            try { $migration->down(); $this->fail('A destructive collapse must be refused.'); } catch (\RuntimeException $exception) { $this->assertStringContainsString('preserved', $exception->getMessage()); }
            $this->assertSame(1, DB::table('notifications')->count());
        } finally {
            DB::purge('notification_upgrade');
            DB::setDefaultConnection($connection);
            Schema::swap($schema);
        }
    }
}
