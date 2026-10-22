<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
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
            Schema::create('notification_settings', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->boolean('email_enabled');
                $t->boolean('push_enabled');
            });
            Schema::create('notifications', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('title');
                $t->text('message');
                $t->string('type');
                $t->timestamp('created_at');
            });
            Schema::create('notification_logs', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->unsignedBigInteger('notification_id');
                $t->string('channel');
                $t->string('status');
                $t->timestamps();
            });
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
            $migration->up();
            $this->assertSame(1, DB::table('notifications')->count());
            $this->assertSame(1, DB::table('notification_settings')->count());
            $this->assertSame(1, DB::table('notification_logs')->count());
            $this->assertSame(0, DB::table('notification_settings')->where('user_id', 7)->value('email_enabled'));
            $this->assertSame('success', DB::table('notification_logs')->where('id', 12)->value('status'));
            DB::table('user_activity_days')->insert(['user_id' => 7, 'activity_date' => '2026-10-22',
                'first_activity_at' => '2026-10-22 09:00:00', 'last_activity_at' => '2026-10-22 09:00:00']);
            DB::table('user_activity_days')->upsert([['user_id' => 7, 'activity_date' => '2026-10-22',
                'first_activity_at' => '2026-10-22 10:00:00', 'last_activity_at' => '2026-10-22 10:00:00']],
                ['user_id', 'activity_date'], ['last_activity_at']);
            $this->assertSame(1, DB::table('user_activity_days')->count());
            $this->assertSame('2026-10-22 09:00:00', DB::table('user_activity_days')->value('first_activity_at'));
            $this->assertSame('2026-10-22 10:00:00', DB::table('user_activity_days')->value('last_activity_at'));
            $columns = collect(Schema::getColumns('user_activity_days'))->keyBy('name');
            $this->assertSame('datetime', $columns['first_activity_at']['type_name']);
            $this->assertSame('datetime', $columns['last_activity_at']['type_name']);
            $this->assertTrue(Schema::hasIndex('notification_logs', 'notification_delivery_dispatch'));
            $plan = DB::select("EXPLAIN QUERY PLAN SELECT id FROM notification_logs WHERE status = 'queued' AND next_attempt_at <= ? ORDER BY id LIMIT 100", [now()]);
            $this->assertStringContainsString('notification_delivery_dispatch', json_encode($plan));
            try {
                $migration->down();
                $this->fail('A destructive collapse must be refused.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('preserved', $exception->getMessage());
            }
            $this->assertSame(1, DB::table('notifications')->count());
        } finally {
            DB::purge('notification_upgrade');
            DB::setDefaultConnection($connection);
            Schema::swap($schema);
        }
    }

    public function test_upgrade_resumes_after_activity_table_failure_and_repairs_missing_indexes(): void
    {
        $connection = DB::getDefaultConnection();
        $schema = Schema::getFacadeRoot();
        config(['database.connections.notification_resume' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('notification_resume');
        $builder = DB::connection()->getSchemaBuilder();
        Schema::swap($builder);
        try {
            Schema::create('users', fn (Blueprint $table) => $table->id());
            Schema::create('notification_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->boolean('email_enabled');
                $table->boolean('push_enabled');
            });
            Schema::create('notifications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('title');
                $table->text('message');
                $table->string('type');
                $table->timestamp('created_at');
            });
            Schema::create('notification_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('notification_id');
                $table->string('channel');
                $table->string('status');
                $table->timestamps();
            });
            // Reproduce the committed DDL left behind by the reported MySQL failure.
            Schema::swap(new class(DB::connection()) extends SQLiteBuilder
            {
                public function create($table, \Closure $callback)
                {
                    if ($table === 'user_activity_days') {
                        throw new \RuntimeException('Simulated invalid timestamp default');
                    }

                    return parent::create($table, $callback);
                }
            });
            $migration = require database_path('migrations/2026_10_21_210000_extend_notification_delivery_and_engagement.php');
            try {
                $migration->up();
                $this->fail('The migration should stop before creating the activity table.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated invalid timestamp default', $exception->getMessage());
            }
            Schema::swap($builder);
            $this->assertTrue(Schema::hasColumn('users', 'last_meaningful_activity_at'));
            $this->assertTrue(Schema::hasColumn('notification_logs', 'delivery_key'));
            $this->assertTrue(Schema::hasTable('notification_events'));
            $this->assertFalse(Schema::hasTable('user_activity_days'));
            DB::table('notification_settings')->insert(['id' => 1, 'user_id' => 7, 'email_enabled' => false, 'push_enabled' => true, 'engagement_reminders' => true, 'quiet_hours_start' => '22:00:00']);
            DB::table('notification_events')->insert(['event_key' => 'keep-me', 'kind' => 'delivered', 'created_at' => now()]);
            $migration->up();
            $this->assertSame(1, DB::table('notification_events')->count());
            $this->assertSame('keep-me', DB::table('notification_events')->value('event_key'));
            $this->assertSame(0, DB::table('notification_settings')->value('email_enabled'));
            $this->assertSame(1, DB::table('notification_settings')->value('engagement_reminders'));
            $this->assertSame('22:00:00', DB::table('notification_settings')->value('quiet_hours_start'));
            $this->assertTrue(Schema::hasTable('notification_reminder_states'));
            $this->assertTrue(Schema::hasTable('notification_event_templates'));
            Schema::table('notification_logs', fn (Blueprint $table) => $table->dropIndex('notification_delivery_dispatch'));
            Schema::table('user_activity_days', fn (Blueprint $table) => $table->dropUnique('user_activity_days_user_id_activity_date_unique'));
            $migration->up();
            $this->assertTrue(Schema::hasIndex('notification_logs', 'notification_delivery_dispatch'));
            $this->assertTrue(Schema::hasIndex('user_activity_days', 'user_activity_days_user_id_activity_date_unique', 'unique'));
            $this->assertSame(1, DB::table('notification_events')->count());
        } finally {
            DB::purge('notification_resume');
            DB::setDefaultConnection($connection);
            Schema::swap($schema);
        }
    }
}
