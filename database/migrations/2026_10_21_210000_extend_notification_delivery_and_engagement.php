<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_meaningful_activity_at')->nullable()->index('users_meaningful_activity');
            $table->timestamp('last_onboarding_reminder_at')->nullable();
        });
        Schema::table('notification_settings', function (Blueprint $table): void {
            foreach (['in_app_enabled', 'academic_alerts', 'organisation_updates', 'onboarding_reminders', 'research_updates'] as $field) $table->boolean($field)->default(true);
            $table->boolean('engagement_reminders')->default(false);
            $table->boolean('email_open_tracking')->default(false);
            $table->string('timezone', 64)->default('UTC');
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('push_permission', 20)->nullable();
            $table->timestamp('push_prompt_snoozed_until')->nullable();
            $table->timestamp('email_suppressed_at')->nullable();
            $table->string('email_suppression_reason', 40)->nullable();
        });
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('event_key', 64)->nullable()->unique('notifications_event_key');
            $table->string('category', 40)->default('academic')->index();
            $table->string('priority', 20)->default('normal');
            $table->boolean('in_app_visible')->default(true);
            $table->unsignedBigInteger('university_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->index(['created_at', 'type'], 'notification_event_date');
        });
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique();
            $table->string('delivery_key', 64)->nullable()->unique();
            $table->unsignedBigInteger('push_subscription_id')->nullable()->index();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->uuid('processing_token')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('provider_message_id')->nullable()->index('notification_provider_message');
            $table->index(['status', 'next_attempt_at', 'id'], 'notification_delivery_dispatch');
            $table->index(['created_at', 'channel', 'status'], 'notification_delivery_metrics');
            $table->index(['user_id', 'channel', 'accepted_at'], 'notification_user_channel_limit');
        });
        Schema::create('notification_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->unsignedBigInteger('notification_id')->nullable()->index();
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
            $table->index(['created_at', 'kind', 'channel'], 'notification_events_reporting');
            $table->index(['user_id', 'kind', 'created_at'], 'notification_events_user_kind');
            $table->index(['university_id', 'department_id', 'created_at'], 'notification_events_scope');
        });
        Schema::create('user_activity_days', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('activity_date');
            $table->timestamp('first_activity_at');
            $table->timestamp('last_activity_at');
            $table->unique(['user_id', 'activity_date']);
            $table->index(['activity_date', 'user_id']);
        });
        Schema::create('notification_reminder_states', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('kind', 30);
            $table->string('cycle_key', 64);
            $table->string('stage', 80)->nullable();
            $table->unsignedSmallInteger('sent_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unique(['user_id', 'kind', 'cycle_key'], 'notification_reminder_cycle');
            $table->index(['kind', 'next_due_at'], 'notification_reminders_due');
        });
        Schema::create('notification_event_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 100)->unique();
            $table->boolean('enabled')->default(true);
            $table->string('title');
            $table->text('message');
            $table->json('channels')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Notification delivery and engagement history must be preserved. Use a reviewed forward migration or a backup.');
    }
};
