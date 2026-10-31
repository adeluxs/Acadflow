<?php

namespace Tests\Feature;

use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReport;
use App\Models\AmbassadorTask;
use App\Models\University;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Homepage/public visibility, the application lifecycle (withdraw, resubmit,
 * eligibility), admin-published activities and private evidence uploads.
 */
class AmbassadorProgrammeCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
    }

    private function applicationData(): array
    {
        return ['institution' => 'Joseph Ayo Babalola University', 'department' => 'Computer Science', 'study_level' => '300 level',
            'leadership_role' => 'course_rep', 'phone' => '+2348012345678', 'community_size' => 80,
            'statement' => 'I will run a short study tools demo for my class and collect feedback.'];
    }

    private function application(?User $user = null, string $status = 'approved'): AmbassadorApplication
    {
        return AmbassadorApplication::forceCreate($this->applicationData() + [
            'user_id' => ($user ?? User::factory()->create())->id, 'status' => $status, 'terms_accepted_at' => now(),
        ]);
    }

    private function report(array $overrides = []): array
    {
        return $overrides + ['submission_key' => (string) Str::uuid(), 'kind' => 'demo', 'title' => 'Level 300 demo',
            'occurred_on' => now()->toDateString(), 'participants' => 20,
            'summary' => 'We tried flashcards and practice quizzes together and noted questions.'];
    }

    public function test_homepage_and_navigation_promote_a_public_programme_page(): void
    {
        $home = $this->get(route('home'))->assertOk();
        $home->assertSee('Become an AcadSyntra Campus Ambassador')
            ->assertSee('Bring smarter academic collaboration to your campus.')
            ->assertSee(route('ambassadors.join'), false)
            ->assertSee('href="'.route('public.ambassadors').'"', false);
        // Main navigation (desktop and mobile) links to the public page.
        $this->assertGreaterThanOrEqual(2, substr_count($home->getContent(), '>Campus Ambassador</a>') + substr_count($home->getContent(), '>Campus Ambassador <span'));

        $page = $this->get(route('public.ambassadors'))->assertOk();
        foreach (['What is the AcadSyntra Campus Ambassador Programme?', 'What do Campus Ambassadors do?', 'Why become a Campus Ambassador?',
            'How to become an ambassador', 'Who can apply', 'Frequently asked questions', 'Can I reapply?', 'How are referrals tracked?'] as $text) {
            $page->assertSee($text);
        }
        $page->assertSee('"@type":"FAQPage"', false)->assertSee('property="og:title"', false);
        // No unsupported promises.
        $page->assertDontSee('guaranteed commission')->assertSee('There is no fixed commission');
    }

    public function test_programme_links_disappear_when_the_feature_is_disabled(): void
    {
        FeatureAccessService::setStatus('campus_ambassadors', 'disabled');
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
        $this->get(route('home'))->assertOk()->assertDontSee('Become an AcadSyntra Campus Ambassador')->assertDontSee(route('public.ambassadors'), false);
    }

    public function test_call_to_action_matches_each_visitor_state(): void
    {
        $this->get(route('public.ambassadors'))->assertSee('Become a Campus Ambassador')->assertSee(route('ambassadors.join'), false);
        $student = User::factory()->create();
        $this->actingAs($student)->get(route('public.ambassadors'))->assertSee('Become a Campus Ambassador');
        $application = $this->application($student, 'pending');
        $this->get(route('public.ambassadors'))->assertSee('View application status');
        $application->forceFill(['status' => 'approved'])->save();
        $this->get(route('public.ambassadors'))->assertSee('Open ambassador dashboard')->assertDontSee('Become a Campus Ambassador</a>', false);
        $application->forceFill(['status' => 'rejected'])->save();
        $this->get(route('public.ambassadors'))->assertSee('Update and resubmit your application');
    }

    public function test_join_preserves_the_destination_through_sign_in(): void
    {
        $this->get(route('ambassadors.join'))->assertRedirect(route('register'))
            ->assertSessionHas('url.intended', route('ambassadors.index'))->assertSessionHas('ambassador_join_intent', true);
        $this->get(route('ambassadors.join', ['account' => 'existing']))->assertRedirect(route('login'));
        $student = User::factory()->create(['password' => 'Strong!Pass1234']);
        $this->post(route('login'), ['email' => $student->email, 'password' => 'Strong!Pass1234'])->assertRedirect(route('ambassadors.index'));
        $this->get(route('ambassadors.join'))->assertRedirect(route('ambassadors.index').'#apply');
    }

    public function test_application_reuses_profile_details_without_trusting_form_institution_links(): void
    {
        $university = University::factory()->create(['name' => 'Joseph Ayo Babalola University']);
        $student = User::factory()->create(['university_id' => $university->id, 'academic_level' => '300 level', 'phone' => '+2348011111111']);
        $this->actingAs($student)->get(route('ambassadors.index'))->assertOk()
            ->assertSee('value="Joseph Ayo Babalola University"', false)->assertSee('value="300 level"', false);
        // university_id comes from the account, never from form input.
        $this->post(route('ambassadors.apply'), $this->applicationData() + ['terms' => 1, 'university_id' => 999999, 'faculty' => 'Natural Sciences',
            'social_profile_url' => 'https://example.org/me', 'status' => 'approved', 'referral_code' => 'aaaaaaaaaaaaaaaa'])->assertRedirect();
        $application = AmbassadorApplication::firstOrFail();
        // Linked only through an approved institution membership; self-declared profile data is not enough.
        $this->assertNull($application->university_id);
        $this->assertSame('pending', $application->status);
        $this->assertNotSame('aaaaaaaaaaaaaaaa', $application->referral_code);
        $this->assertSame('Natural Sciences', $application->faculty);
        $this->assertNotNull($application->submitted_at);
    }

    public function test_only_student_accounts_can_apply_and_unsafe_profile_links_are_rejected(): void
    {
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $this->actingAs($lecturer)->postJson(route('ambassadors.apply'), $this->applicationData() + ['terms' => 1])->assertUnprocessable()->assertJsonValidationErrors('application');
        $admin = User::factory()->create(['role' => 'university_admin']);
        $this->actingAs($admin)->postJson(route('ambassadors.apply'), $this->applicationData() + ['terms' => 1])->assertForbidden();
        $student = User::factory()->create();
        $this->actingAs($student)->postJson(route('ambassadors.apply'), $this->applicationData() + ['terms' => 1, 'social_profile_url' => 'javascript:alert(1)'])
            ->assertUnprocessable()->assertJsonValidationErrors('social_profile_url');
        $this->assertDatabaseCount('ambassador_applications', 0);
    }

    public function test_pending_application_can_be_withdrawn_and_resubmitted_by_its_owner_only(): void
    {
        $student = User::factory()->create();
        $application = $this->application($student, 'pending');
        $this->actingAs($student)->post(route('ambassadors.withdraw'))->assertRedirect(route('ambassadors.index'));
        $this->assertSame('withdrawn', $application->fresh()->status);
        $this->assertNotNull($application->fresh()->withdrawn_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ambassador.application.withdrawn', 'user_id' => $student->id]);
        $this->postJson(route('ambassadors.withdraw'))->assertUnprocessable();
        $this->get(route('ambassadors.index'))->assertSee('Withdrawn')->assertSee('Update and resubmit your application');
        $this->post(route('ambassadors.apply'), $this->applicationData() + ['terms' => 1])->assertRedirect();
        $this->assertSame('pending', $application->fresh()->status);
        $this->assertNull($application->fresh()->withdrawn_at);

        // An approved ambassador cannot withdraw into a re-application, and admins cannot act on withdrawn records.
        $approved = $this->application(null, 'approved');
        $this->actingAs($approved->user)->postJson(route('ambassadors.withdraw'))->assertUnprocessable();
        $this->assertSame('approved', $approved->fresh()->status);
        $application->forceFill(['status' => 'withdrawn'])->save();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)->patchJson(route('admin.ambassadors.review', $application), ['status' => 'approved', 'review_note' => 'Looks fine.'])->assertUnprocessable();
    }

    public function test_super_admin_manages_activities_and_others_cannot(): void
    {
        $payload = ['title' => 'Host a 10-minute study tools demo', 'kind' => 'demo', 'summary' => 'Show classmates flashcards and practice quizzes.',
            'resource_url' => 'https://acadsyntra.com/features', 'requires_evidence' => '1', 'is_active' => '1'];
        foreach ([User::factory()->create(), User::factory()->create(['role' => 'university_admin'])] as $user) {
            $this->actingAs($user)->post(route('admin.ambassadors.tasks.store'), $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('ambassador_tasks', 0);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.ambassadors.tasks.store'), $payload + ['created_by' => 1])->assertRedirect(route('admin.ambassadors.tasks.index'));
        $task = AmbassadorTask::firstOrFail();
        $this->assertSame($admin->id, $task->created_by);
        $this->assertTrue($task->requires_evidence);
        $this->postJson(route('admin.ambassadors.tasks.store'), ['title' => 'Bad link', 'kind' => 'demo', 'summary' => 'Unsafe resource link here', 'resource_url' => 'http://insecure.example'])
            ->assertUnprocessable()->assertJsonValidationErrors('resource_url');
        $this->get(route('admin.ambassadors.tasks.index'))->assertOk()->assertSee('Host a 10-minute study tools demo');
        $this->patch(route('admin.ambassadors.tasks.update', $task), ['toggle' => 1])->assertRedirect();
        $this->assertFalse($task->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ambassador.task.created', 'user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ambassador.task.updated', 'user_id' => $admin->id]);
    }

    public function test_ambassadors_see_only_open_activities_and_reports_respect_activity_rules(): void
    {
        Storage::fake('local');
        $open = AmbassadorTask::create(['title' => 'Open campaign', 'kind' => 'demo', 'summary' => 'Run a demo this month.', 'requires_evidence' => true, 'is_active' => true]);
        AmbassadorTask::create(['title' => 'Closed campaign', 'kind' => 'demo', 'summary' => 'No longer running.', 'is_active' => false]);
        AmbassadorTask::create(['title' => 'Future campaign', 'kind' => 'demo', 'summary' => 'Starts later.', 'is_active' => true, 'starts_on' => now()->addWeek()->toDateString()]);
        $expired = AmbassadorTask::create(['title' => 'Expired campaign', 'kind' => 'demo', 'summary' => 'Already ended.', 'is_active' => true, 'ends_on' => now()->subDay()->toDateString()]);

        $pendingStudent = $this->application(null, 'pending')->user;
        $this->actingAs($pendingStudent)->get(route('ambassadors.index'))->assertDontSee('Open campaign');

        $ambassador = $this->application()->user;
        $this->actingAs($ambassador)->get(route('ambassadors.index'))->assertSee('Open campaign')
            ->assertDontSee('Closed campaign')->assertDontSee('Future campaign')->assertDontSee('Expired campaign');
        $this->postJson(route('ambassadors.reports.store'), $this->report(['task' => $expired->uuid]))->assertUnprocessable()->assertJsonValidationErrors('task');
        $this->postJson(route('ambassadors.reports.store'), $this->report(['task' => $open->uuid]))->assertUnprocessable()->assertJsonValidationErrors('evidence_file');
        $this->post(route('ambassadors.reports.store'), $this->report(['task' => $open->uuid, 'evidence_url' => 'https://example.org/photos']))->assertRedirect();
        $this->assertSame($open->id, AmbassadorReport::firstOrFail()->ambassador_task_id);
        $this->assertSame('pending', AmbassadorReport::firstOrFail()->status);
    }

    public function test_evidence_files_are_validated_private_and_never_duplicated(): void
    {
        Storage::fake('local');
        $application = $this->application();
        $owner = $application->user;
        $this->actingAs($owner);
        // Content is sniffed: a script renamed to .png is refused.
        // Files need a multipart request; Accept: JSON returns validation errors as JSON.
        // A real temporary file (not a fake) so the server sniffs its actual content.
        $disguised = tempnam(sys_get_temp_dir(), 'amb');
        file_put_contents($disguised, '<html><script>alert(1)</script></html>');
        $this->post(route('ambassadors.reports.store'), $this->report(['evidence_file' => new UploadedFile($disguised, 'photo.png', 'image/png', null, true)]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('evidence_file');
        $this->post(route('ambassadors.reports.store'), $this->report(['evidence_file' => UploadedFile::fake()->image('big.png')->size(6000)]), ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('evidence_file');
        $this->assertDatabaseCount('ambassador_reports', 0);

        $key = (string) Str::uuid();
        $this->post(route('ambassadors.reports.store'), $this->report(['submission_key' => $key, 'evidence_file' => UploadedFile::fake()->image('../../demo photo.png', 40, 40)]))->assertRedirect();
        $this->post(route('ambassadors.reports.store'), $this->report(['submission_key' => $key, 'evidence_file' => UploadedFile::fake()->image('again.png', 40, 40)]))->assertRedirect();
        $this->assertDatabaseCount('ambassador_reports', 1);
        $report = AmbassadorReport::firstOrFail();
        $this->assertStringStartsWith('ambassador-evidence/'.$application->uuid.'/', $report->evidence_path);
        $this->assertStringNotContainsString('..', $report->evidence_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('ambassador-evidence'));
        $this->assertSame('image/png', $report->evidence_mime);
        $this->assertArrayNotHasKey('evidence_path', $report->toArray());

        $this->get(route('ambassadors.reports.evidence', $report))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create())->get(route('ambassadors.reports.evidence', $report))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'university_admin']))->get(route('admin.ambassadors.reports.evidence', $report))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('admin.ambassadors.reports.evidence', $report))->assertOk();
    }

    public function test_admin_filters_applications_by_institution_and_date_and_shows_audit_trail(): void
    {
        $first = $this->application(null, 'pending');
        $other = AmbassadorApplication::forceCreate(array_merge($this->applicationData(), ['institution' => 'University of Lagos', 'user_id' => User::factory()->create()->id, 'status' => 'pending', 'terms_accepted_at' => now()]));
        $other->forceFill(['created_at' => now()->subMonths(2)])->save();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->patch(route('admin.ambassadors.review', $first), ['status' => 'approved', 'review_note' => 'Verified course rep.']);
        $this->get(route('admin.ambassadors.index', ['status' => 'all', 'q' => 'Lagos']))->assertOk()->assertSee('University of Lagos')->assertDontSee('Joseph Ayo Babalola University</td>', false);
        $this->get(route('admin.ambassadors.index', ['status' => 'all', 'from' => now()->subWeek()->toDateString()]))->assertSee('Joseph Ayo Babalola University')->assertDontSee('University of Lagos');
        $this->get(route('admin.ambassadors.index', ['status' => 'all', 'q' => '%']))->assertOk();
        $this->getJson(route('admin.ambassadors.index', ['from' => '2026-10-10', 'to' => '2026-10-01']))->assertUnprocessable();
        $this->get(route('admin.ambassadors.index', ['status' => 'all']))->assertSee('Recent programme audit trail')->assertSee('application · approved');
    }

    public function test_unknown_referral_codes_behave_like_valid_ones_without_attribution(): void
    {
        $this->get('/r/'.str_repeat('z', 16))->assertRedirect(route('register'))->assertSessionMissing('ambassador_referral');
        $application = $this->application();
        $this->get(route('ambassadors.referral', $application->referral_code))->assertRedirect(route('register'))
            ->assertSessionHas('ambassador_referral.code', $application->referral_code);
    }
}
