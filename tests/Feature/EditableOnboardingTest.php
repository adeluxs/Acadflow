<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\ResearchProject;
use App\Models\ResearchType;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Models\UserOnboardingState;
use App\Services\AcademicMembershipService;
use App\Services\FeatureAccessService;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationReminderService;
use App\Services\OnboardingService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditableOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
        FeatureAccessService::setStatus('notifications', 'enabled');
        SettingService::set('email_notifications_enabled', true, 'boolean');
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['username' => 'member_'.fake()->unique()->numerify('########')], $attributes));
    }

    private function interests(): array
    {
        return ['programme' => 'Computer Science', 'academic_level' => 'doctoral', 'research_interests' => ['Data science'],
            'skills' => ['Statistics'], 'topic_interests' => ['Education'], 'community_interests' => ['Open research'], 'event_interests' => ['Workshops']];
    }

    public function test_all_fields_can_be_completed_without_skipping_and_percentage_uses_applicable_fields(): void
    {
        $user = $this->user(['onboarding_completed_at' => null, 'avatar' => 'existing-avatar.png']);
        $service = app(OnboardingService::class);
        $department = Department::factory()->create();
        $service->saveStep($user, 'path', ['path' => 'student']);
        $service->saveStep($user, 'profile', ['first_name' => 'Ada', 'last_name' => 'Okafor', 'username' => $user->username,
            'headline' => 'Doctoral researcher', 'biography' => 'Education researcher', 'country_code' => 'ng', 'location' => 'Lagos']);
        $service->saveStep($user, 'affiliation', ['university_id' => $department->faculty->university_id, 'faculty_id' => $department->faculty_id, 'department_id' => $department->id]);
        $service->saveStep($user, 'interests', $this->interests());
        $service->saveStep($user, 'preferences', ['profile_visibility' => 'public', 'email_notifications' => true, 'in_app_notifications' => true]);
        $service->complete($user);
        $progress = $service->progress($user->fresh());
        $this->assertSame(100, $progress['percentage']);
        $this->assertTrue($progress['fully_completed']);
        $this->assertSame($progress['total_fields'], $progress['filled_fields']);
        $this->assertSame([], $user->onboardingState()->sole()->skipped_steps);
        $this->assertSame('NG', $user->fresh()->country_code);
    }

    public function test_optional_skips_allow_dashboard_access_and_can_be_filled_later_without_restart(): void
    {
        $user = $this->user(['account_type' => 'independent_professional', 'role' => 'member', 'onboarding_completed_at' => null]);
        $service = app(OnboardingService::class);
        foreach (['affiliation', 'interests', 'preferences'] as $step) {
            $service->skip($user, $step);
        }
        $service->complete($user);
        $completedAt = $user->fresh()->onboarding_completed_at;
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Complete your profile');
        $this->get(route('onboarding.manage'))->assertOk()->assertSee('Skipped')->assertSee('Manage onboarding');
        $this->get(route('onboarding.show', ['edit' => 1, 'step' => 4]))->assertOk()->assertSee('Save changes');
        $this->post(route('onboarding.save', 'interests'), $this->interests())->assertRedirect(route('onboarding.manage'));
        $this->assertSame(['Data science'], $user->fresh()->research_interests);
        $this->assertNotContains('interests', $user->onboardingState()->sole()->skipped_steps);
        $this->assertTrue($completedAt->equalTo($user->fresh()->onboarding_completed_at));
        $this->assertNull(app(NotificationReminderService::class)->onboardingStage($user->fresh()));
    }

    public function test_profile_edits_preserve_affiliation_other_profile_fields_and_completion_timestamp(): void
    {
        $department = Department::factory()->create();
        $user = $this->user(['university_id' => $department->faculty->university_id, 'faculty_id' => $department->faculty_id,
            'department_id' => $department->id, 'programme' => 'Engineering', 'research_interests' => ['Robotics']]);
        $user->creatorProfile()->create(['headline' => 'Current headline', 'biography' => 'Current biography', 'position' => 'Professor']);
        $completedAt = $user->onboarding_completed_at;
        UserOnboardingState::create(['user_id' => $user->id, 'path' => 'student', 'data' => ['headline' => 'Stale headline', 'university_id' => null, 'research_interests' => []], 'completed_at' => now()]);
        $this->actingAs($user)->get(route('onboarding.show', ['edit' => 1, 'step' => 2]))->assertOk()->assertSee('Current headline')->assertDontSee('Stale headline');
        $this->post(route('onboarding.save', 'profile'), ['first_name' => 'Updated', 'last_name' => $user->last_name, 'username' => $user->username, 'headline' => 'Updated headline'])->assertRedirect(route('onboarding.manage'));
        $fresh = $user->fresh();
        $this->assertSame($department->id, $fresh->department_id);
        $this->assertSame(['Robotics'], $fresh->research_interests);
        $this->assertSame('Current biography', $fresh->creatorProfile->biography);
        $this->assertSame('Professor', $fresh->creatorProfile->position);
        $this->assertTrue($completedAt->equalTo($fresh->onboarding_completed_at));
        $this->post(route('onboarding.complete'))->assertRedirect(route('onboarding.manage'));
        $this->assertSame('Updated headline', $user->fresh()->creatorProfile->headline);
        $this->assertDatabaseCount('user_onboarding_states', 1);
        $this->assertDatabaseCount('creator_profiles', 1);
    }

    public function test_existing_affiliation_cannot_be_overwritten_and_failed_saves_are_atomic(): void
    {
        $department = Department::factory()->create();
        $other = Department::factory()->create();
        $user = $this->user(['university_id' => $department->faculty->university_id, 'faculty_id' => $department->faculty_id, 'department_id' => $department->id]);
        $this->actingAs($user)->post(route('onboarding.save', 'affiliation'), ['university_id' => $other->faculty->university_id, 'faculty_id' => $other->faculty_id, 'department_id' => $other->id])->assertSessionHasErrors('university_id');
        $this->assertSame($department->id, $user->fresh()->department_id);
        $this->assertArrayNotHasKey('department_id', $user->onboardingState()->sole()->data);
        $this->post(route('onboarding.save', 'path'), ['path' => 'lecturer'])->assertSessionHasNoErrors();
        $this->assertSame($department->id, $user->fresh()->department_id);
    }

    public function test_independent_user_can_add_missing_department_without_granting_access_or_losing_work(): void
    {
        $user = $this->user();
        $department = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $department->id, 'access_mode' => 'independent', 'owner_user_id' => $user->id]);
        $project = ResearchProject::create(['university_id' => $department->faculty->university_id, 'department_id' => $department->id, 'owner_id' => $user->id, 'title' => 'Existing research', 'workspace_mode' => 'independent', 'research_type_id' => ResearchType::create(['name' => 'Independent research', 'slug' => 'independent-research', 'is_active' => true])->id, 'status' => 'draft']);
        $this->actingAs($user)->post(route('onboarding.save', 'affiliation'), ['department_id' => $department->id])->assertSessionHasNoErrors();
        $this->assertSame($department->faculty->university_id, $user->fresh()->university_id);
        $this->assertSame($department->faculty_id, $user->fresh()->faculty_id);
        $this->assertSame($department->id, $user->fresh()->department_id);
        $this->assertDatabaseHas('user_institution_affiliations', ['user_id' => $user->id, 'department_id' => $department->id, 'status' => 'self_declared']);
        $this->assertFalse($user->institutionAffiliations()->approved()->exists());
        $this->assertSame($user->id, $project->fresh()->owner_id);
        $this->assertSame($user->id, $course->fresh()->owner_user_id);
    }

    public function test_mandatory_affiliation_cannot_be_skipped_and_can_be_completed_after_a_failed_review(): void
    {
        $user = $this->user(['account_type' => 'academic_staff', 'onboarding_completed_at' => null]);
        $this->actingAs($user)->post(route('onboarding.skip', 'affiliation'))->assertSessionHasErrors('university_id');
        $this->post(route('onboarding.complete'))->assertSessionHasErrors('university_id');
        $department = Department::factory()->create();
        $this->post(route('onboarding.save', 'affiliation'), ['university_id' => $department->faculty->university_id, 'department_id' => $department->id])->assertSessionHasNoErrors();
        $this->post(route('onboarding.complete'))->assertSessionHasNoErrors();
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
    }

    public function test_reminders_target_actual_missing_requirements_and_stop_when_requirement_is_filled(): void
    {
        $user = $this->user(['username' => null, 'created_at' => now()->subDays(2), 'onboarding_completed_at' => null]);
        UserOnboardingState::create(['user_id' => $user->id, 'path' => 'student', 'current_step' => 6, 'skipped_steps' => ['affiliation', 'interests'], 'data' => []]);
        $reminders = app(NotificationReminderService::class);
        $reminders->remindOnboarding($user);
        $notice = Notification::where('type', 'onboarding_reminder')->sole();
        $this->assertStringContainsString('step=2', $notice->data['url']);
        app(OnboardingService::class)->saveStep($user, 'profile', ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'username' => 'completed-requirement']);
        $this->assertNull($reminders->onboardingStage($user->fresh()));
        $this->assertFalse($reminders->stillRelevant($notice, $user->fresh()));
        $delivery = NotificationLog::where('notification_id', $notice->id)->where('channel', 'email')->sole();
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
    }

    public function test_optional_reminders_require_consent_respect_quiet_hours_and_stop_after_completion(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $user = $this->user(['onboarding_completed_at' => now()->subDays(30), 'avatar' => 'avatar.png', 'country_code' => 'NG', 'location' => 'Lagos']);
        $user->creatorProfile()->create(['headline' => 'Researcher', 'biography' => 'Academic profile']);
        app(OnboardingService::class)->skip($user, 'affiliation');
        $reminders = app(NotificationReminderService::class);
        $reminders->remindProfile($user);
        $this->assertDatabaseCount('notifications', 0);
        $settings = NotificationSetting::create(['user_id' => $user->id, 'engagement_reminders' => true, 'timezone' => config('app.timezone'), 'quiet_hours_start' => '11:00', 'quiet_hours_end' => '13:00']);
        $reminders->remindProfile($user);
        $this->assertDatabaseCount('notifications', 0);
        $settings->update(['quiet_hours_start' => null, 'quiet_hours_end' => null]);
        $reminders->remindProfile($user);
        $notice = Notification::where('type', 'profile_completion_suggestion')->sole();
        $this->assertSame('engagement', $notice->category);
        $this->assertSame('affiliation', $notice->data['profile_step']);
        $this->assertStringContainsString('step=3', $notice->data['url']);
        $reminders->remindProfile($user);
        $this->assertDatabaseCount('notifications', 1);
        $department = Department::factory()->create();
        app(OnboardingService::class)->saveStep($user, 'affiliation', ['university_id' => $department->faculty->university_id, 'faculty_id' => $department->faculty_id, 'department_id' => $department->id]);
        $this->assertFalse($reminders->stillRelevant($notice, $user->fresh()));
        $reminders->remindProfile($user->fresh());
        $this->assertDatabaseCount('notifications', 1);
        $delivery = NotificationLog::where('notification_id', $notice->id)->where('channel', 'email')->sole();
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertDatabaseMissing('notifications', ['type' => 'onboarding_reminder']);
    }

    public function test_dismissal_is_saved_across_devices_and_suppresses_optional_suggestions(): void
    {
        $user = $this->user(['onboarding_completed_at' => now()->subDays(30)]);
        $this->actingAs($user)->post(route('onboarding.dismiss'))->assertRedirect();
        $otherDevice = User::withoutGlobalScopes()->findOrFail($user->id);
        $this->assertTrue(app(OnboardingService::class)->progress($otherDevice)['banner_dismissed']);
        $this->assertNull(app(NotificationReminderService::class)->profileSuggestion($otherDevice));
        $this->actingAs($otherDevice)->get(route('onboarding.manage'))->assertOk()->assertSee('Manage onboarding');
    }

    public function test_api_edits_and_fresh_sessions_share_canonical_progress_and_bound_directory_payloads(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user, ['platform:access']);
        $this->putJson('/api/v1/onboarding/interests', $this->interests())->assertOk()->assertJsonPath('progress.steps.interests.status', 'completed');
        University::factory()->count(25)->create(['name' => 'Campus search match']);
        $this->actingAs(User::withoutGlobalScopes()->findOrFail($user->id))->getJson('/api/v1/onboarding')
            ->assertOk()->assertJsonPath('data.research_interests', ['Data science'])->assertJsonCount(0, 'universities');
        $this->getJson('/api/v1/onboarding/directory?q=Campus')->assertOk()->assertJsonCount(12, 'institutions');
        $this->assertDatabaseCount('user_onboarding_states', 1);
    }

    public function test_lecturer_fields_are_role_specific_and_do_not_erase_existing_student_history(): void
    {
        $lecturer = $this->user(['role' => 'lecturer', 'account_type' => 'lecturer', 'programme' => 'Historic programme']);
        $this->actingAs($lecturer)->get(route('onboarding.show', ['edit' => 1, 'step' => 4]))->assertOk()->assertSee('Academic position')->assertSee('Supervision interests')->assertDontSee('name="academic_level"', false);
        $this->post(route('onboarding.save', 'interests'), ['academic_position' => 'Senior lecturer', 'specialisation' => 'Computer vision', 'supervision_interests_text' => 'Imaging, Robotics', 'programme' => 'Forged overwrite'])->assertSessionHasNoErrors();
        $this->assertSame('Senior lecturer', $lecturer->fresh()->creatorProfile->position);
        $this->assertSame('Historic programme', $lecturer->fresh()->programme);
        $this->get(route('onboarding.show', ['edit' => 1, 'step' => 4]))->assertOk()->assertSee('Computer vision')->assertSee('Imaging, Robotics');
        $this->actingAs($this->user())->get(route('onboarding.show', ['edit' => 1, 'step' => 4]))->assertOk()->assertSee('Academic level')->assertDontSee('name="academic_position"', false);
    }

    public function test_representatives_and_administrators_can_reopen_role_specific_setup(): void
    {
        foreach (['department_representative' => 'Department setup', 'university_representative' => 'Institution setup'] as $path => $label) {
            $this->actingAs($this->user(['account_type' => $path, 'role' => 'member']))->get(route('onboarding.manage'))->assertOk()->assertSee($label);
        }
        foreach (['department_admin' => 'Department setup', 'university_admin' => 'Institution setup'] as $role => $label) {
            $department = Department::factory()->create();
            $this->actingAs($this->user(['role' => $role, 'university_id' => $department->faculty->university_id, 'department_id' => $department->id]))->get(route('onboarding.manage'))->assertOk()->assertSee($label);
        }
    }

    public function test_membership_request_and_invitation_reuse_existing_account_and_approved_memberships(): void
    {
        FeatureAccessService::setStatus('institution_self_service', 'enabled');
        $user = $this->user();
        $institution = University::factory()->create(['adoption_status' => 'active']);
        $department = Department::factory()->create(['faculty_id' => Faculty::factory()->create(['university_id' => $institution->id])->id, 'adoption_status' => 'institution_managed']);
        $this->actingAs($user)->post(route('institution.access.store'), ['university_id' => $institution->id, 'department_id' => $department->id, 'requested_role' => 'student'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_institution_affiliations', ['user_id' => $user->id, 'status' => 'pending_verification']);
        $this->get(route('onboarding.manage'))->assertOk()->assertSee('Pending Verification');
        $admin = $this->user(['role' => 'super_admin']);
        $invitation = app(AcademicMembershipService::class)->invite($admin, $institution, $department, 'student', $user->email);
        $this->post($invitation['url'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 2);
        $this->assertSame(1, $user->institutionAffiliations()->count());
        $this->assertTrue($user->institutionAffiliations()->sole()->isVerified());
        $this->assertSame($department->id, $user->fresh()->department_id);
    }

    public function test_management_uses_approved_admin_context_without_changing_personal_identity(): void
    {
        $user = $this->user(['role' => 'member', 'account_type' => 'independent_professional']);
        $department = Department::factory()->create(['adoption_status' => 'independent_active']);
        $membership = UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $department->faculty->university_id,
            'faculty_id' => $department->faculty_id, 'department_id' => $department->id, 'affiliation_type' => 'department_admin', 'status' => 'verified']);
        $this->actingAs($user)->withSession(['academic_affiliation_id' => $membership->id])
            ->get(route('onboarding.manage'))->assertOk()->assertSee('Department setup')->assertSee('Modules and academic calendar');
        $this->get(route('academic.organisations'))->assertOk()->assertSee('Department configuration')
            ->assertSee(route('academic.departments.modules', $department))->assertSee(route('academic.departments.calendar', $department));
        $this->assertNull($user->fresh()->university_id);
        $this->get(route('onboarding.show', ['edit' => 1, 'step' => 3]))->assertOk()->assertViewHas('data', fn ($data) => $data['university_id'] === null);
        $membership->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->get(route('onboarding.manage'))->assertOk()->assertDontSee('Department setup');
        $this->get(route('academic.organisations'))->assertOk()->assertDontSee('Department configuration');
    }

    public function test_preferences_autosave_preserves_other_settings_and_uses_current_channel_preferences(): void
    {
        $user = $this->user();
        $settings = NotificationSetting::create(['user_id' => $user->id, 'email_enabled' => false, 'push_enabled' => false,
            'onboarding_reminders' => false, 'engagement_reminders' => true, 'quiet_hours_start' => '22:00', 'quiet_hours_end' => '06:00', 'timezone' => 'Africa/Lagos']);
        $this->actingAs($user)->get(route('onboarding.show', ['edit' => 1, 'step' => 5]))->assertOk()
            ->assertViewHas('data', fn ($data) => $data['notification_preferences']['email'] === false);
        $this->postJson(route('onboarding.save', 'preferences'), ['profile_visibility' => 'private', 'in_app_notifications' => 1])
            ->assertOk()->assertJsonPath('progress.steps.preferences.status', 'completed');
        $this->assertFalse($settings->fresh()->email_enabled);
        $this->assertFalse($settings->fresh()->push_enabled);
        $this->assertFalse($settings->fresh()->onboarding_reminders);
        $this->assertTrue($settings->fresh()->engagement_reminders);
        $this->assertSame('22:00', substr($settings->fresh()->quiet_hours_start, 0, 5));
        $this->assertSame('private', $user->fresh()->profile_visibility);
    }

    public function test_progress_does_not_write_state_and_has_constant_query_cost_as_directory_grows(): void
    {
        $user = $this->user();
        DB::enableQueryLog();
        app(OnboardingService::class)->progress($user->fresh());
        $before = count(DB::getQueryLog());
        University::factory()->count(25)->create();
        DB::flushQueryLog();
        app(OnboardingService::class)->progress($user->fresh());
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($before, $after);
        $this->assertLessThanOrEqual(3, $after); // User refresh, state lookup, creator profile.
        $this->assertDatabaseMissing('user_onboarding_states', ['user_id' => $user->id]);
    }
}
