<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendTable('users', function (Blueprint $table, array $existing): void {
            if (! isset($existing['last_meaningful_activity_at'])) {
                $table->timestamp('last_meaningful_activity_at')->nullable();
            }
            if (! isset($existing['last_onboarding_reminder_at'])) {
                $table->timestamp('last_onboarding_reminder_at')->nullable();
            }
        });
        $this->extendTable('notification_settings', function (Blueprint $table, array $existing): void {
            foreach (['in_app_enabled', 'academic_alerts', 'organisation_updates', 'onboarding_reminders', 'research_updates'] as $field) {
                if (! isset($existing[$field])) {
                    $table->boolean($field)->default(true);
                }
            }
            if (! isset($existing['engagement_reminders'])) {
                $table->boolean('engagement_reminders')->default(false);
            }
            if (! isset($existing['email_open_tracking'])) {
                $table->boolean('email_open_tracking')->default(false);
            }
            if (! isset($existing['timezone'])) {
                $table->string('timezone', 64)->default('UTC');
            }
            if (! isset($existing['quiet_hours_start'])) {
                $table->time('quiet_hours_start')->nullable();
            }
            if (! isset($existing['quiet_hours_end'])) {
                $table->time('quiet_hours_end')->nullable();
            }
            if (! isset($existing['push_permission'])) {
                $table->string('push_permission', 20)->nullable();
            }
            if (! isset($existing['push_prompt_snoozed_until'])) {
                $table->timestamp('push_prompt_snoozed_until')->nullable();
            }
            if (! isset($existing['email_suppressed_at'])) {
                $table->timestamp('email_suppressed_at')->nullable();
            }
            if (! isset($existing['email_suppression_reason'])) {
                $table->string('email_suppression_reason', 40)->nullable();
            }
        });
        $this->extendTable('notifications', function (Blueprint $table, array $existing): void {
            if (! isset($existing['event_key'])) {
                $table->string('event_key', 64)->nullable();
            }
            if (! isset($existing['category'])) {
                $table->string('category', 40)->default('academic');
            }
            if (! isset($existing['priority'])) {
                $table->string('priority', 20)->default('normal');
            }
            if (! isset($existing['in_app_visible'])) {
                $table->boolean('in_app_visible')->default(true);
            }
            if (! isset($existing['university_id'])) {
                $table->unsignedBigInteger('university_id')->nullable();
            }
            if (! isset($existing['department_id'])) {
                $table->unsignedBigInteger('department_id')->nullable();
            }
            if (! isset($existing['clicked_at'])) {
                $table->timestamp('clicked_at')->nullable();
            }
        });
        $this->extendTable('notification_logs', function (Blueprint $table, array $existing): void {
            if (! isset($existing['uuid'])) {
                $table->uuid('uuid')->nullable();
            }
            if (! isset($existing['delivery_key'])) {
                $table->string('delivery_key', 64)->nullable();
            }
            if (! isset($existing['push_subscription_id'])) {
                $table->unsignedBigInteger('push_subscription_id')->nullable();
            }
            if (! isset($existing['queued_at'])) {
                $table->timestamp('queued_at')->nullable();
            }
            if (! isset($existing['accepted_at'])) {
                $table->timestamp('accepted_at')->nullable();
            }
            if (! isset($existing['delivered_at'])) {
                $table->timestamp('delivered_at')->nullable();
            }
            if (! isset($existing['next_attempt_at'])) {
                $table->timestamp('next_attempt_at')->nullable();
            }
            if (! isset($existing['processing_started_at'])) {
                $table->timestamp('processing_started_at')->nullable();
            }
            if (! isset($existing['processing_token'])) {
                $table->uuid('processing_token')->nullable();
            }
            if (! isset($existing['clicked_at'])) {
                $table->timestamp('clicked_at')->nullable();
            }
            if (! isset($existing['opened_at'])) {
                $table->timestamp('opened_at')->nullable();
            }
            if (! isset($existing['latency_ms'])) {
                $table->unsignedInteger('latency_ms')->nullable();
            }
            if (! isset($existing['provider_message_id'])) {
                $table->string('provider_message_id')->nullable();
            }
        });
        if (! Schema::hasTable('notification_events')) {
            Schema::create('notification_events', function (Blueprint $table): void {
                $table->id();
                $table->string('event_key', 64);
                $table->unsignedBigInteger('notification_id')->nullable();
                $table->unsignedBigInteger('delivery_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('university_id')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('role', 40)->nullable();
                $table->string('account_type', 60)->nullable();
                $table->string('kind', 50);
                $table->string('channel', 20)->nullable();
                $table->string('notification_type', 100)->nullable();
                $table->string('category', 40)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at');
            });
        }
        if (! Schema::hasTable('user_activity_days')) {
            Schema::create('user_activity_days', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->date('activity_date');
                // Application supplies both times; DATETIME avoids legacy implicit TIMESTAMP defaults.
                $table->dateTime('first_activity_at');
                $table->dateTime('last_activity_at');
            });
        }
        if (! Schema::hasTable('notification_reminder_states')) {
            Schema::create('notification_reminder_states', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('kind', 30);
                $table->string('cycle_key', 64);
                $table->string('stage', 80)->nullable();
                $table->unsignedSmallInteger('sent_count')->default(0);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('next_due_at')->nullable();
                $table->timestamp('completed_at')->nullable();
            });
        }
        if (! Schema::hasTable('notification_event_templates')) {
            Schema::create('notification_event_templates', function (Blueprint $table): void {
                $table->id();
                $table->string('event_type', 100);
                $table->boolean('enabled')->default(true);
                $table->string('title');
                $table->text('message');
                $table->json('channels')->nullable();
                $table->timestamps();
            });
        }
        $this->ensureIndexes('users', [
            'users_meaningful_activity' => ['index', ['last_meaningful_activity_at']],
        ]);
        $this->ensureIndexes('notifications', [
            'notifications_event_key' => ['unique', ['event_key']],
            'notifications_category_index' => ['index', ['category']],
            'notification_event_date' => ['index', ['created_at', 'type']],
        ]);
        $this->ensureIndexes('notification_logs', [
            'notification_logs_uuid_unique' => ['unique', ['uuid']],
            'notification_logs_delivery_key_unique' => ['unique', ['delivery_key']],
            'notification_logs_push_subscription_id_index' => ['index', ['push_subscription_id']],
            'notification_provider_message' => ['index', ['provider_message_id']],
            'notification_delivery_dispatch' => ['index', ['status', 'next_attempt_at', 'id']],
            'notification_delivery_metrics' => ['index', ['created_at', 'channel', 'status']],
            'notification_user_channel_limit' => ['index', ['user_id', 'channel', 'accepted_at']],
        ]);
        $this->ensureIndexes('notification_events', [
            'notification_events_event_key_unique' => ['unique', ['event_key']],
            'notification_events_notification_id_index' => ['index', ['notification_id']],
            'notification_events_reporting' => ['index', ['created_at', 'kind', 'channel']],
            'notification_events_user_kind' => ['index', ['user_id', 'kind', 'created_at']],
            'notification_events_scope' => ['index', ['university_id', 'department_id', 'created_at']],
        ]);
        $this->ensureIndexes('user_activity_days', [
            'user_activity_days_user_id_activity_date_unique' => ['unique', ['user_id', 'activity_date']],
            'user_activity_days_activity_date_user_id_index' => ['index', ['activity_date', 'user_id']],
        ]);
        $this->ensureIndexes('notification_reminder_states', [
            'notification_reminder_states_user_id_index' => ['index', ['user_id']],
            'notification_reminder_cycle' => ['unique', ['user_id', 'kind', 'cycle_key']],
            'notification_reminders_due' => ['index', ['kind', 'next_due_at']],
        ]);
        $this->ensureIndexes('notification_event_templates', [
            'notification_event_templates_event_type_unique' => ['unique', ['event_type']],
        ]);
    }

    /** MySQL DDL commits before the migration is recorded, so every addition must be resumable. */
    private function extendTable(string $name, Closure $definition): void
    {
        $existing = array_fill_keys(Schema::getColumnListing($name), true);
        Schema::table($name, fn (Blueprint $table) => $definition($table, $existing));
    }

    private function ensureIndexes(string $name, array $definitions): void
    {
        $existing = array_column(Schema::getIndexes($name), 'name');
        Schema::table($name, function (Blueprint $table) use ($definitions, $existing): void {
            foreach ($definitions as $index => [$type, $columns]) {
                if (! in_array($index, $existing, true)) {
                    $table->$type($columns, $index);
                }
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Notification delivery and engagement history must be preserved. Use a reviewed forward migration or a backup.');
    }
};
