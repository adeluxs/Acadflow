<?php

namespace Tests\Feature;

use App\Http\Controllers\AcademicOrganisationController;
use App\Models\AcademicMembershipInvitation;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentInstitutionAffiliation;
use App\Models\DepartmentOnboardingDraft;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Services\AcademicOrganisationService;
use App\Services\InstitutionAdoptionService;
use App\Services\SocialNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicAdoptionPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function campus(): array
    {
        $institution = University::factory()->create(['code' => 'PERF'.(University::count() + 1), 'adoption_status' => 'active']);
        $faculty = Faculty::factory()->create(['university_id' => $institution->id, 'code' => 'BASE']);
        $department = Department::factory()->create(['faculty_id' => $faculty->id, 'code' => 'BASE', 'adoption_status' => 'independent_active']);

        return [$institution, $faculty, $department];
    }

    private function membership(User $user, University $institution, Department $department, string $role = 'student', array $attributes = []): UserInstitutionAffiliation
    {
        return UserInstitutionAffiliation::create(array_merge(['user_id' => $user->id, 'university_id' => $institution->id, 'faculty_id' => $department->faculty_id, 'department_id' => $department->id, 'affiliation_type' => $role, 'status' => 'verified', 'verified_at' => now(), 'source' => 'test_review'], $attributes));
    }

    private function readsDuring(callable $action): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $action();

            return array_values(array_filter(DB::getQueryLog(), fn ($q) => str_starts_with(strtolower($q['query']), 'select')));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_department_notices_use_two_reads_for_more_than_two_hundred_recipients_and_remain_idempotent(): void
    {
        [$institution, , $department] = $this->campus();
        $users = User::factory()->count(207)->create(['department_id' => $department->id]);
        $this->membership($users[0], $institution, $department);
        $this->membership($users[1], $institution, $department, attributes: ['expires_at' => now()->subDay()]);
        app(SocialNotificationService::class)->send($users[2], 'department_available', 'Already notified', 'Existing notice', ['department_id' => $department->id]);

        $reads = $this->readsDuring(fn () => app(AcademicOrganisationService::class)->notifyAvailable($department));
        $recipientReads = array_filter($reads, fn ($q) => str_contains($q['query'], 'from "users" where "department_id" = ?'));
        $this->assertCount(2, $recipientReads, 'Recipient checks must be batched rather than queried per user; notification outbox writes are tracked separately.');
        $this->assertDatabaseCount('notifications', 206);
        $this->assertDatabaseMissing('notifications', ['user_id' => $users[0]->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $users[1]->id, 'type' => 'department_available']);
        $this->assertCount(1, $this->readsDuring(fn () => app(AcademicOrganisationService::class)->notifyAvailable($department)));
        $this->assertDatabaseCount('notifications', 206);
        $this->assertSame(1, UserInstitutionAffiliation::approved()->count(), 'Notifications cannot approve memberships.');
    }

    public function test_institution_notices_batch_admin_lookups_and_do_not_duplicate_multi_department_representatives(): void
    {
        [$institution, $faculty, $department] = $this->campus();
        $super = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create();
        $this->membership($admin, $institution, $department, 'department_admin');
        for ($i = 0; $i < 8; $i++) {
            $other = Department::factory()->create(['faculty_id' => $faculty->id, 'code' => 'NOTICE'.$i, 'adoption_status' => 'independent_active']);
            $this->membership($admin, $institution, $other, 'department_admin');
            $this->membership(User::factory()->create(), $institution, $other, 'department_admin');
        }
        $revoked = User::factory()->create();
        $this->membership($revoked, $institution, $department, 'department_admin', ['status' => 'revoked', 'revoked_at' => now()]);
        [$foreignInstitution, , $foreignDepartment] = $this->campus();
        $foreign = User::factory()->create();
        $this->membership($foreign, $foreignInstitution, $foreignDepartment, 'department_admin');

        $reads = $this->readsDuring(function () use ($institution, $super): void {
            $this->assertSame(9, app(InstitutionAdoptionService::class)->migrateIndependentDepartments($institution, $super));
        });
        $domainReads = array_filter($reads, fn ($q) => preg_match('/from "(departments|user_institution_affiliations|users)"/', $q['query']) && ! str_contains($q['query'], 'select exists'));
        $this->assertCount(3, $domainReads, 'Department count, eligible memberships and recipient eager-load should be the only domain reads.');
        $this->assertDatabaseCount('notifications', 9);
        $this->assertDatabaseMissing('notifications', ['user_id' => $revoked->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $foreign->id]);
        $this->assertSame(1, $admin->notifications()->count());
        app(InstitutionAdoptionService::class)->migrateIndependentDepartments($institution, $super);
        $this->assertDatabaseCount('notifications', 9);
        $this->assertSame('independent_active', $department->fresh()->adoption_status);
    }

    public function test_directory_and_resumed_wizard_load_only_public_selector_fields(): void
    {
        [$institution, $faculty, $department] = $this->campus();
        $department->update(['adoption_settings' => ['private_marker' => 'PRIVATE_DEPARTMENT_TERMS']]);
        Department::factory()->create(['faculty_id' => $faculty->id, 'code' => 'INACTIVE', 'is_active' => false]);
        $selected = null;
        $reads = $this->readsDuring(function () use ($institution, &$selected): void {
            $selected = app(AcademicOrganisationService::class)->directoryInstitution($institution->id);
        });
        $this->assertCount(3, $reads);
        $this->assertSame(['id', 'name', 'adoption_status'], array_keys($selected->getAttributes()));
        $this->assertSame(['id', 'university_id', 'name'], array_keys($selected->faculties->first()->getAttributes()));
        $this->assertCount(1, $selected->faculties->first()->departments);
        $this->assertSame(['id', 'faculty_id', 'name', 'adoption_status'], array_keys($selected->faculties->first()->departments->first()->getAttributes()));

        $applicant = User::factory()->create(['role' => 'member', 'account_type' => 'department_representative']);
        DepartmentOnboardingDraft::create(['user_id' => $applicant->id, 'data' => ['university_id' => $institution->id, 'faculty_id' => $faculty->id, 'department_id' => $department->id]]);
        $this->actingAs($applicant)->get(route('department.onboarding'))->assertOk()->assertDontSee('PRIVATE_DEPARTMENT_TERMS')
            ->assertViewHas('selected', fn ($record) => $record->faculties->first()->departments->first()->id === $department->id);
    }

    public function test_affiliation_api_pages_histories_without_loading_html_department_catalogues(): void
    {
        [$institution, , $department] = $this->campus();
        $super = User::factory()->create(['role' => 'super_admin']);
        for ($i = 0; $i < 27; $i++) {
            $this->membership(User::factory()->create(), $institution, $department);
            AcademicMembershipInvitation::create(['university_id' => $institution->id, 'department_id' => $department->id, 'invited_by' => $super->id, 'email' => "member{$i}@example.test", 'role' => 'student', 'token_hash' => hash('sha256', Str::random(40)), 'expires_at' => now()->addWeek()]);
            DepartmentInstitutionAffiliation::create(['university_id' => $institution->id, 'department_id' => $department->id, 'requested_by' => $super->id, 'status' => 'ended']);
        }
        $first = $this->actingAs($super)->getJson(route('academic.organisations'))->assertOk()
            ->assertJsonCount(25, 'affiliations')->assertJsonCount(25, 'managedMembers')->assertJsonCount(25, 'invitations')
            ->assertJsonPath('pagination.affiliations.has_more_pages', true);
        $second = $this->getJson(route('academic.organisations', ['agreements_page' => 2, 'members_page' => 2, 'invitations_page' => 2]))->assertOk()
            ->assertJsonCount(2, 'affiliations')->assertJsonCount(2, 'managedMembers')->assertJsonCount(2, 'invitations')
            ->assertJsonPath('pagination.affiliations.has_more_pages', false);
        foreach (['affiliations', 'managedMembers', 'invitations'] as $key) {
            $this->assertEmpty(array_intersect(array_column($first->json($key), 'id'), array_column($second->json($key), 'id')));
        }
        $request = Request::create('/academic/affiliations', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => $super);
        $reads = $this->readsDuring(fn () => app(AcademicOrganisationController::class)->index($request, app(AcademicOrganisationService::class)));
        $this->assertFalse(collect($reads)->contains(fn ($q) => str_contains($q['query'], 'from "faculties"')), 'API history requests must not load unused HTML form catalogues.');
        $this->get(route('academic.organisations'))->assertOk()->assertSee('members_page=2', false)->assertSee('agreements_page=2', false)->assertSee('invitations_page=2', false);
    }

    public function test_second_membership_page_cannot_leak_unrelated_department_members(): void
    {
        [$institution, $faculty, $department] = $this->campus();
        $admin = User::factory()->create(['role' => 'department_admin', 'university_id' => $institution->id, 'faculty_id' => $faculty->id, 'department_id' => $department->id]);
        $this->membership($admin, $institution, $department, 'department_admin');
        foreach (User::factory()->count(26)->create() as $student) {
            $this->membership($student, $institution, $department);
        }
        $foreignDepartment = Department::factory()->create(['faculty_id' => $faculty->id, 'code' => 'FOREIGN', 'adoption_status' => 'independent_active']);
        $foreign = User::factory()->create();
        $foreignMembership = $this->membership($foreign, $institution, $foreignDepartment);
        $this->actingAs($admin)->getJson(route('academic.organisations', ['members_page' => 2]))->assertOk()
            ->assertJsonCount(2, 'managedMembers')->assertJsonMissing(['id' => $foreignMembership->id, 'user_id' => $foreign->id]);
    }

    public function test_invitation_scope_selector_is_bounded_searchable_and_keeps_later_departments_reachable(): void
    {
        [$institution, $faculty] = $this->campus();
        Department::factory()->count(50)->sequence(fn ($sequence) => ['code' => 'SCOPE'.$sequence->index])->create(['faculty_id' => $faculty->id]);
        $last = Department::factory()->create(['faculty_id' => $faculty->id, 'code' => 'ROBOTICS', 'name' => 'Zzz Applied Robotics']);
        $super = User::factory()->create(['role' => 'super_admin']);
        $first = $this->actingAs($super)->get(route('academic.organisations'))->assertOk()
            ->assertViewHas('departments', fn ($rows) => $rows->count() === 50 && $rows->hasMorePages())
            ->assertSee('scope_page=2', false);
        $second = $this->get(route('academic.organisations', ['scope_page' => 2]))->assertOk()
            ->assertViewHas('departments', fn ($rows) => $rows->count() === 2);
        $this->assertEqualsCanonicalizing(Department::pluck('id')->all(), array_merge($first->viewData('departments')->pluck('id')->all(), $second->viewData('departments')->pluck('id')->all()));
        $this->get(route('academic.organisations', ['scope_q' => 'applied robotics']))->assertOk()
            ->assertViewHas('departments', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $last->id);
    }

    public function test_affiliation_candidates_are_paginated_within_the_institutions_directory_scope(): void
    {
        [$institution, $faculty] = $this->campus();
        Department::factory()->count(21)->sequence(fn ($sequence) => ['code' => 'CAND'.$sequence->index])->create(['faculty_id' => $faculty->id, 'adoption_status' => 'independent_active']);
        [, , $foreign] = $this->campus();
        $admin = User::factory()->create(['role' => 'university_admin', 'university_id' => $institution->id]);
        UserInstitutionAffiliation::create(['user_id' => $admin->id, 'university_id' => $institution->id, 'affiliation_type' => 'university_admin', 'status' => 'verified', 'verified_at' => now(), 'source' => 'test_review']);
        $this->actingAs($admin)->get(route('academic.organisations'))->assertOk()
            ->assertViewHas('candidates', fn ($rows) => $rows->count() === 20 && $rows->hasMorePages())->assertSee('candidates_page=2', false);
        $this->get(route('academic.organisations', ['candidates_page' => 2]))->assertOk()
            ->assertViewHas('candidates', fn ($rows) => $rows->count() === 2 && ! $rows->contains('id', $foreign->id));
    }

    public function test_course_checks_skip_unused_approval_reads_and_still_observe_revocation_immediately(): void
    {
        [$institution, $faculty, $department] = $this->campus();
        $student = User::factory()->create(['role' => 'student']);
        $membership = $this->membership($student, $institution, $department);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $course->load('department.faculty');
        $reads = $this->readsDuring(fn () => $student->canAccessCourse($course));
        $membershipReads = array_filter($reads, fn ($q) => str_contains($q['query'], 'from "user_institution_affiliations"'));
        $this->assertCount(1, $membershipReads);
        $session = AcademicSession::factory()->create(['university_id' => $institution->id, 'department_id' => $department->id]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id]);
        $student->enrollments()->create(['course_id' => $course->id, 'status' => 'enrolled', 'semester_id' => $semester->id, 'enrolled_at' => now()]);
        $this->assertTrue($student->canAccessCourse($course));
        $membership->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->assertFalse($student->canAccessCourse($course));
        $this->membership($student, $institution, $department, 'lecturer');
        $this->assertTrue($student->canAccessCourse($course));
    }

    public function test_lookup_index_migration_can_be_reversed_without_losing_records(): void
    {
        [$institution, , $department] = $this->campus();
        $user = User::factory()->create();
        $membership = $this->membership($user, $institution, $department);
        app(SocialNotificationService::class)->send($user, 'department_available', 'Existing notice', 'Keep this record');
        $migration = require database_path('migrations/2026_10_21_180000_index_academic_adoption_lookups.php');
        $this->assertTrue(Schema::hasIndex('notifications', 'academic_notice_recipient'));
        $this->assertTrue(Schema::hasIndex('user_institution_affiliations', 'academic_department_members'));
        $migration->down();
        $this->assertDatabaseHas('user_institution_affiliations', ['id' => $membership->id, 'status' => 'verified']);
        $this->assertDatabaseCount('notifications', 1);
        $migration->up();
        $this->assertTrue(Schema::hasIndex('notifications', 'academic_notice_recipient'));
        $this->assertTrue(Schema::hasIndex('user_institution_affiliations', 'academic_department_members'));
    }
}
