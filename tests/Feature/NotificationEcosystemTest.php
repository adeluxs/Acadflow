<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotification;
use App\Jobs\RecordAcademicNotification;
use App\Mail\AcademicNotificationMail;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentOnboardingDraft;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\NotificationEventTemplate;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionGrade;
use App\Models\SubmissionTask;
use App\Models\User;
use App\Models\UserOnboardingState;
use App\Services\MeaningfulActivityService;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationPreferences;
use App\Services\NotificationReminderService;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use App\Services\SettingService;
use App\Services\FeatureAccessService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

class NotificationEcosystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        config(['notification_delivery.connection' => 'database']);
        FeatureAccessService::setStatus('notifications', 'enabled');
        SettingService::set('email_notifications_enabled', true, 'boolean');
    }

    private function publish(User $user, string $type = 'assignment_created', array $data = []): Notification
    {
        return app(NotificationService::class)->publish($user, $type, 'Academic update', 'PRIVATE_ACADEMIC_DETAIL', $data);
    }

    public function test_worker_exhaustion_during_a_send_preserves_uncertainty_and_prevents_duplicate_retry(): void
    {
        $notice = $this->publish(User::factory()->create());
        $delivery = NotificationLog::where('notification_id', $notice->id)->where('channel', 'email')->sole();
        $delivery->update(['status' => 'processing', 'processing_started_at' => now(), 'processing_token' => 'worker-lease']);

        (new DeliverNotification($delivery->id))->failed(new \RuntimeException('Worker terminated'));
        $this->assertSame('unknown', $delivery->fresh()->status);
        $this->assertNull(app(NotificationDeliveryService::class)->deliver($delivery->id));
        Mail::assertNothingSent();
    }

    private function draft(User $user): array
    {
        $department = Department::factory()->create();
        $semester = Semester::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id, 'access_mode' => 'independent', 'owner_user_id' => $user->id]);
        $submission = Submission::factory()->create(['user_id' => $user->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'draft']);

        return [$submission, $course, $semester];
    }

    private function enablePush(User $user, array $statuses = [201]): array
    {
        $auth = ['subject' => 'mailto:push@example.test'] + VAPID::createVapidKeys();
        config(['services.webpush.vapid_public_key' => $auth['publicKey'], 'services.webpush.vapid_private_key' => $auth['privateKey'], 'services.webpush.vapid_subject' => $auth['subject']]);
        FeatureAccessService::setStatus('push_notifications', 'enabled');
        SettingService::set('push_notifications_enabled', true, 'boolean');
        $keys = VAPID::createVapidKeys();
        $data = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/lifecycle-'. $user->id, 'keys' => ['p256dh' => $keys['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
        app(PushNotificationService::class)->subscribe($user, $data['endpoint'], $data['keys'], 'Chrome Mobile');
        $handler = HandlerStack::create(new MockHandler(array_map(fn ($status) => new Response($status), $statuses)));
        $this->app->instance(WebPush::class, new WebPush(['VAPID' => $auth], ['TTL' => 60], 5, ['handler' => $handler, 'allow_redirects' => false]));

        return $data;
    }

    public function test_publish_is_idempotent_and_queues_channels_without_sending_email_during_the_request(): void
    {
        $user = User::factory()->create();
        $first = $this->publish($user, data: ['event_key' => 'assignment:1']);
        $second = $this->publish($user, data: ['event_key' => 'assignment:1']);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_logs', 2);
        Queue::assertPushed(DeliverNotification::class, 1);
        Mail::assertNothingSent();
        $this->assertDatabaseHas('notification_events', ['kind' => 'created', 'notification_id' => $first->id]);
    }

    public function test_log_mail_is_classified_as_simulated_and_does_not_expose_academic_content_in_email(): void
    {
        $user = User::factory()->create();
        $notice = $this->publish($user);
        $delivery = NotificationLog::where('channel', 'email')->firstOrFail();
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('simulated', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->accepted_at);
        Mail::assertSent(AcademicNotificationMail::class, function ($mail): bool {
            $html = $mail->render();
            $this->assertStringNotContainsString('PRIVATE_ACADEMIC_DETAIL', $html);
            $this->assertStringContainsString('AcadSyntra', $html);
            $this->assertStringNotContainsString('notification-events/pixel', $html);
            return true;
        });
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        Mail::assertSentCount(1);
    }

    public function test_real_webpush_transport_acceptance_is_logged_once_and_repeated_jobs_do_not_resend(): void
    {
        $user = User::factory()->create();
        $this->enablePush($user);
        $notice = $this->publish($user);
        $delivery = NotificationLog::where('channel', 'push')->firstOrFail();
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('accepted', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->accepted_at);
        $this->assertNull($delivery->fresh()->delivered_at);
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame(1, $delivery->fresh()->attempt_count);
        $this->assertSame(1, NotificationEvent::where('kind', 'accepted')->where('delivery_id', $delivery->id)->count());
    }

    public function test_expired_push_device_is_removed_and_recorded_as_a_permanent_failure(): void
    {
        $user = User::factory()->create();
        $this->enablePush($user, [410]);
        $this->publish($user);
        $delivery = NotificationLog::where('channel', 'push')->firstOrFail();
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertDatabaseHas('notification_events', ['kind' => 'push_expired', 'user_id' => $user->id]);
    }

    public function test_temporary_push_failure_retries_with_backoff_and_preserves_a_single_delivery(): void
    {
        $user = User::factory()->create();
        $this->enablePush($user, [503, 201]);
        $this->publish($user);
        $delivery = NotificationLog::where('channel', 'push')->firstOrFail();
        try { app(NotificationDeliveryService::class)->deliver($delivery->id); $this->fail('A retryable provider failure must be reported to the queue.'); } catch (\RuntimeException $error) { $this->assertStringContainsString('temporarily', $error->getMessage()); }
        $this->assertSame('retrying', $delivery->fresh()->status);
        $this->travel(61)->seconds();
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('accepted', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempt_count);
        $this->assertSame(1, NotificationLog::where('channel', 'push')->count());
    }

    public function test_changed_preferences_cancel_queued_delivery_and_quiet_hours_defer_it(): void
    {
        $user = User::factory()->create();
        $this->publish($user);
        $delivery = NotificationLog::where('channel', 'email')->firstOrFail();
        NotificationSetting::create(['user_id' => $user->id, 'email_enabled' => false]);
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Mail::assertNothingSent();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-22 22:00:00', 'Africa/Lagos')->utc());
        $settings = NotificationSetting::where('user_id', $user->id)->first();
        $settings->update(['email_enabled' => true, 'timezone' => 'Africa/Lagos', 'quiet_hours_start' => '21:00', 'quiet_hours_end' => '07:00']);
        $notice = $this->publish($user, data: ['event_key' => 'quiet-hours']);
        $next = NotificationLog::where('notification_id', $notice->id)->where('channel', 'email')->firstOrFail();
        $delay = app(NotificationDeliveryService::class)->deliver($next->id);
        $this->assertGreaterThan(0, $delay);
        $this->assertSame('deferred', $next->fresh()->status);
        $this->assertSame(0, $next->fresh()->attempt_count);
        $this->assertSame('07:00', $next->fresh()->next_attempt_at->timezone('Africa/Lagos')->format('H:i'));
    }

    public function test_profile_email_opt_out_is_preserved_before_a_settings_record_exists(): void
    {
        $user = User::factory()->create(['notification_preferences' => ['email' => false]]);
        $this->publish($user);
        $this->assertDatabaseMissing('notification_logs', ['channel' => 'email']);
        $this->actingAs($user)->get(route('notifications.settings'))->assertOk()->assertViewHas('settings', fn ($settings) => ! $settings->email_enabled);
    }

    public function test_incomplete_personal_setup_reminds_after_twelve_hours_and_stops_on_completion(): void
    {
        $user = User::factory()->create(['created_at' => now()->subHours(13), 'onboarding_completed_at' => null]);
        UserOnboardingState::create(['user_id' => $user->id, 'path' => 'student', 'current_step' => 3, 'started_at' => now()->subHours(13), 'data' => []]);
        $reminders = app(NotificationReminderService::class);
        $reminders->remindOnboarding($user);
        $reminders->remindOnboarding($user->fresh());
        $this->assertSame(1, Notification::where('category', 'onboarding')->count());
        $notice = Notification::where('category', 'onboarding')->firstOrFail();
        // The cursor points at optional affiliation, but the username is genuinely missing.
        $this->assertStringContainsString('step=2', $notice->data['url']);
        $this->travel(11)->hours();
        $reminders->remindOnboarding($user->fresh());
        $this->assertSame(1, Notification::where('category', 'onboarding')->count());
        $this->travel(1)->hours();
        $reminders->remindOnboarding($user->fresh());
        $this->assertSame(2, Notification::where('category', 'onboarding')->count());
        $user->forceFill(['onboarding_completed_at' => now()])->saveQuietly();
        app(MeaningfulActivityService::class)->onboardingCompleted($user->fresh());
        foreach (NotificationLog::where('channel', 'email')->get() as $delivery) app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame(2, NotificationLog::where('channel', 'email')->where('status', 'cancelled')->count());
        $this->assertDatabaseHas('notification_events', ['kind' => 'onboarding_completed', 'user_id' => $user->id]);
    }

    public function test_campaign_includes_verified_institution_administrators_with_existing_personal_accounts(): void
    {
        $user = User::factory()->create(['account_type' => 'academic_staff', 'created_at' => now()->subDays(2), 'onboarding_completed_at' => now()->subDay()]);
        $institution = \App\Models\University::factory()->create(['adoption_status' => 'setup_in_progress']);
        \App\Models\UserInstitutionAffiliation::create([
            'user_id' => $user->id, 'university_id' => $institution->id,
            'affiliation_type' => 'university_admin', 'status' => 'verified',
        ]);

        app(NotificationReminderService::class)->scanOnboarding();
        $notice = Notification::where('user_id', $user->id)->where('category', 'onboarding')->sole();
        $this->assertSame('Institution administrator setup', $notice->data['stage']);
        $institution->update(['adoption_status' => 'active']);
        $this->assertFalse(app(NotificationReminderService::class)->stillRelevant($notice, $user->fresh()));
    }

    public function test_completed_setup_before_the_first_reminder_produces_no_reminder(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(2), 'onboarding_completed_at' => now()->subDay()]);
        app(NotificationReminderService::class)->remindOnboarding($user);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_department_wizard_reminders_resume_the_existing_draft(): void
    {
        $user = User::factory()->create(['account_type' => 'department_representative', 'created_at' => now()->subDay()]);
        DepartmentOnboardingDraft::create(['user_id' => $user->id, 'step' => 2, 'data' => []]);
        app(NotificationReminderService::class)->remindOnboarding($user);
        $notice = Notification::firstOrFail();
        $this->assertSame(route('department.onboarding'), $notice->data['url']);
        $this->assertStringContainsString('Department', $notice->data['stage']);
        DepartmentOnboardingDraft::where('user_id', $user->id)->delete();
        $this->assertFalse(app(NotificationReminderService::class)->stillRelevant($notice, $user));
    }

    public function test_repeated_ignored_onboarding_reminders_slow_down_and_respect_the_limit(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(2), 'onboarding_completed_at' => null]);
        $service = app(NotificationReminderService::class);
        foreach (range(1, 3) as $number) { $service->remindOnboarding($user->fresh()); $this->travel(12)->hours(); }
        $service->remindOnboarding($user->fresh());
        $this->assertSame(3, Notification::where('category', 'onboarding')->count());
        SettingService::set('notification_onboarding_max_reminders', 3, 'integer');
        $this->travel(72)->hours();
        $service->remindOnboarding($user->fresh());
        $this->assertSame(3, Notification::where('category', 'onboarding')->count());
    }

    public function test_inactivity_requires_consent_and_unfinished_work_and_stops_on_return(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(3)]);
        $user->forceFill(['last_meaningful_activity_at' => now()->subHours(25)])->saveQuietly();
        [$draft] = $this->draft($user);
        $reminders = app(NotificationReminderService::class);
        $reminders->scanInactivity();
        $this->assertSame(0, Notification::where('category', 'engagement')->count());
        NotificationSetting::create(['user_id' => $user->id, 'engagement_reminders' => true]);
        $reminders->scanInactivity();
        $reminders->scanInactivity();
        $notice = Notification::where('category', 'engagement')->firstOrFail();
        $this->assertSame(1, Notification::where('category', 'engagement')->count());
        app(MeaningfulActivityService::class)->record($user->fresh());
        $this->assertFalse($reminders->stillRelevant($notice, $user->fresh()));
        $this->assertDatabaseHas('notification_events', ['kind' => 'returned', 'user_id' => $user->id]);
    }

    public function test_background_requests_do_not_count_as_activity_but_a_page_visit_does(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('notifications.unread-count'))->assertOk();
        $this->assertNull($user->fresh()->last_meaningful_activity_at);
        $this->get(route('notifications.settings'), ['Purpose' => 'prefetch'])->assertOk();
        $this->assertNull($user->fresh()->last_meaningful_activity_at);
        $this->get(route('notifications.settings'))->assertOk();
        $this->assertNotNull($user->fresh()->last_meaningful_activity_at);
        $this->assertDatabaseCount('user_activity_days', 1);
    }

    public function test_clicks_are_owner_authorised_deduplicated_and_clear_keeps_delivery_history(): void
    {
        $user = User::factory()->create();
        $notice = $this->publish($user, data: ['url' => route('notifications.settings')]);
        $this->actingAs(User::factory()->create())->get(route('notifications.open', $notice))->assertForbidden();
        $this->actingAs($user)->get(route('notifications.open', $notice))->assertRedirect(route('notifications.settings'));
        $this->get(route('notifications.open', $notice))->assertRedirect();
        $this->assertSame(1, NotificationEvent::where('kind', 'clicked')->count());
        $this->delete(route('notifications.clear'))->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $notice->id, 'in_app_visible' => false]);
        $this->assertDatabaseCount('notification_logs', 2);
        $this->getJson(route('notifications.unread-count'))->assertJsonPath('count', 0);
    }

    public function test_optional_unsubscribe_requires_a_valid_signature_and_does_not_disable_security_messages(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null, 'created_at' => now()->subDay()]);
        app(NotificationReminderService::class)->remindOnboarding($user);
        $delivery = NotificationLog::where('channel', 'email')->firstOrFail();
        $url = URL::temporarySignedRoute('notification.unsubscribe', now()->addHour(), ['delivery' => $delivery->uuid]);
        $this->get($url)->assertOk()->assertSee('Manage optional reminders');
        $this->assertTrue(app(NotificationPreferences::class)->settings($user)->onboarding_reminders ?? true);
        $this->post($url)->assertOk();
        $this->assertFalse(NotificationSetting::where('user_id', $user->id)->first()->onboarding_reminders);
        $this->post(route('notification.unsubscribe', $delivery->uuid))->assertForbidden();
        \Illuminate\Support\Facades\Notification::fake();
        $user->sendPasswordResetNotification('example-token');
        \Illuminate\Support\Facades\Notification::assertSentTo($user, \App\Notifications\QueuedResetPassword::class);
    }

    public function test_provider_confirmation_requires_hmac_and_bounce_suppresses_future_email(): void
    {
        $user = User::factory()->create();
        $this->publish($user);
        $delivery = NotificationLog::where('channel', 'email')->firstOrFail();
        $delivery->update(['status' => 'accepted', 'accepted_at' => now()]);
        config(['notification_delivery.provider_webhook_secret' => 'fixture-secret']);
        $payload = json_encode(['delivery_id' => $delivery->uuid, 'event_id' => 'event-123', 'event' => 'bounced']);
        $timestamp = (string) time();
        $this->postJson(route('notification.provider.events'), json_decode($payload, true))->assertForbidden();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_ACADSYNTRA_TIMESTAMP' => $timestamp, 'HTTP_X_ACADSYNTRA_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$payload, 'fixture-secret')];
        $this->call('POST', route('notification.provider.events'), server: $headers, content: $payload)->assertOk();
        $this->call('POST', route('notification.provider.events'), server: $headers, content: $payload)->assertOk();
        $this->assertSame('bounced', $delivery->fresh()->status);
        $this->assertNotNull(NotificationSetting::where('user_id', $user->id)->first()->email_suppressed_at);
        $this->assertSame(1, NotificationEvent::where('kind', 'bounced')->count());
    }

    public function test_new_users_can_enrol_and_record_denied_permission_before_onboarding(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => null]);
        $data = $this->enablePush($user);
        $this->actingAs($user)->postJson(route('browser-push.subscribe'), $data)->assertOk();
        $this->postJson(route('browser-push.permission'), ['permission' => 'denied'])->assertOk();
        $settings = NotificationSetting::where('user_id', $user->id)->first();
        $this->assertSame('denied', $settings->push_permission);
        $this->assertTrue($settings->push_prompt_snoozed_until->isFuture());
        $this->get(route('notifications.settings'))->assertOk();
    }

    public function test_assignment_deadlines_notify_non_submitters_once_and_cancel_after_submission(): void
    {
        $user = User::factory()->create();
        [$draft, $course, $semester] = $this->draft($user);
        $task = SubmissionTask::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'close_at' => now()->addHours(8), 'due_date' => now()->addDay()]);
        Enrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        $service = app(NotificationReminderService::class);
        $service->scanDeadlines(); $service->scanDeadlines();
        $notice = Notification::where('type', 'deadline_approaching')->firstOrFail();
        $this->assertSame(1, Notification::where('type', 'deadline_approaching')->count());
        $draft->update(['submission_task_id' => $task->id, 'status' => 'submitted']);
        $this->assertFalse($service->stillRelevant($notice, $user));
    }

    public function test_grade_events_create_queued_notifications_for_the_student(): void
    {
        $student = User::factory()->create();
        [$submission] = $this->draft($student);
        $grader = User::factory()->create(['role' => 'lecturer']);
        $grade = SubmissionGrade::create(['submission_id' => $submission->id, 'user_id' => $grader->id, 'score' => 80, 'max_score' => 100, 'is_final' => true]);
        Queue::assertPushed(RecordAcademicNotification::class, fn ($job) => $job->modelClass === SubmissionGrade::class);
        (new RecordAcademicNotification(SubmissionGrade::class, $grade->id, 'grade-1'))->handle(app(NotificationService::class));
        $this->assertDatabaseHas('notifications', ['user_id' => $student->id, 'type' => 'grade_posted']);
    }

    public function test_analytics_and_csv_use_recorded_values_and_enforce_super_admin_access(): void
    {
        $user = User::factory()->create();
        $this->enablePush($user);
        $this->publish($user);
        app(NotificationDeliveryService::class)->deliver(NotificationLog::where('channel', 'push')->value('id'));
        $this->actingAs($user)->getJson(route('admin.notifications.analytics'))->assertForbidden();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->getJson(route('admin.notifications.analytics'))->assertOk()->assertJsonPath('report.generated', 1)->assertJsonPath('report.channels.push.accepted', 1)->assertJsonPath('report.channels.push.confirmed', 0);
        $this->get(route('admin.notifications.analytics'))->assertOk()->assertSee('Notification Analytics')->assertSee('observational');
        $csv = $this->get(route('admin.notifications.analytics.export', ['channel' => 'push']))->assertOk()->streamedContent();
        $this->assertStringContainsString('accepted', $csv);
        $this->assertStringNotContainsString('PRIVATE_ACADEMIC_DETAIL', $csv);
        $this->assertStringNotContainsString('fcm.googleapis', $csv);
        $this->assertStringNotContainsString($user->email, $csv);
    }

    public function test_analytics_remains_bounded_as_notification_history_grows(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        $counts = [];
        foreach ([0, 1200] as $size) {
            if ($size) foreach (range(1, 6) as $batch) {
                $rows = [];
                foreach (range(1, 200) as $i) $rows[] = ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $admin->id, 'type' => 'academic_update', 'category' => 'academic', 'title' => 'Fixture', 'message' => 'Fixture update', 'created_at' => now()];
                DB::table('notifications')->insert($rows);
            }
            DB::enableQueryLog(); DB::flushQueryLog();
            $this->getJson(route('admin.notifications.analytics', ['fresh' => 1]))->assertOk()->assertJsonPath('report.generated', $size);
            $counts[] = count(DB::getQueryLog()); DB::disableQueryLog();
        }
        $this->assertLessThanOrEqual($counts[0] + 2, $counts[1], 'Notification volume must not create a query per record.');
        $this->assertLessThan(70, $counts[1]);
    }

    public function test_admin_templates_limits_and_retry_controls_are_secure(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->put(route('admin.notifications.templates'), ['event_type' => 'assignment_created', 'title' => 'Hello {first_name}', 'message' => 'Review {title}', 'enabled' => false, 'channels' => ['email']])->assertRedirect();
        $this->assertNull(app(NotificationService::class)->publish($admin, 'assignment_created', 'New task', 'Body'));
        $notice = $this->publish($admin, 'grade_posted');
        $delivery = NotificationLog::where('channel', 'email')->firstOrFail();
        $delivery->update(['status' => 'accepted', 'accepted_at' => now()]);
        $this->post(route('admin.notifications.delivery.retry', $delivery))->assertUnprocessable();
        $delivery->update(['status' => 'failed', 'accepted_at' => null, 'attempt_count' => 1]);
        $this->post(route('admin.notifications.delivery.retry', $delivery))->assertRedirect();
        $this->assertSame('queued', $delivery->fresh()->status);
    }

    public function test_processing_outcome_is_not_automatically_resent_and_unknown_is_visible(): void
    {
        $user = User::factory()->create();
        $this->publish($user);
        $delivery = NotificationLog::where('channel', 'email')->firstOrFail();
        $delivery->update(['status' => 'processing', 'processing_started_at' => now()->subMinutes(5), 'attempt_count' => 1]);
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('unknown', $delivery->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_help_centre_contains_all_adoption_guides_and_real_routes(): void
    {
        $this->get(route('public.faq'))->assertOk()->assertSee('Departmental onboarding')->assertSee('Institutional onboarding')->assertSee('Department affiliation')
            ->assertSee(route('department.onboarding'), false)->assertSee(route('institution.onboarding.request'), false)->assertSee('recurring-subscription')->assertSee('<ol', false);
    }
}
