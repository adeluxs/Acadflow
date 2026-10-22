<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\CommercialAccount;
use App\Models\ContentDocument;
use App\Models\Course;
use App\Models\Department;
use App\Models\DepartmentAdoptionRequest;
use App\Models\Faculty;
use App\Models\FeatureEntitlement;
use App\Models\InstitutionOnboardingRequest;
use App\Models\LecturerCourseAssignment;
use App\Models\MediaAsset;
use App\Models\PricingRule;
use App\Models\ResearchProject;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Policies\ResearchProjectPolicy;
use App\Services\AcademicContextService;
use App\Services\AcademicMembershipService;
use App\Services\AcademicOrganisationService;
use App\Services\Commerce\AcademicSponsorshipService;
use App\Services\Commerce\EntitlementService;
use App\Services\Commerce\FeatureBillingService;
use App\Services\DepartmentAffiliationService;
use App\Services\FeatureAccessService;
use App\Services\IndependentAcademicContextService;
use App\Services\InstitutionAdoptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AcademicAdoptionEcosystemTest extends TestCase
{
    use RefreshDatabase;

    private function campus(string $state = 'unclaimed'): array
    {
        $university = University::factory()->create(['adoption_status' => $state]);
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id, 'adoption_status' => 'independent_active', 'adopted_at' => now()->subMonths(6)]);

        return [$university, $faculty, $department];
    }

    private function member(University $institution, ?Department $department, string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'account_type' => in_array($role, ['student', 'lecturer']) ? $role : 'academic_staff', 'university_id' => $institution->id, 'faculty_id' => $department?->faculty_id, 'department_id' => $department?->id]);
        UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $institution->id, 'faculty_id' => $department?->faculty_id, 'department_id' => $department?->id, 'affiliation_type' => $role, 'status' => 'verified', 'verified_at' => now(), 'source' => 'test_review']);

        return $user;
    }

    public function test_department_wizard_saves_only_the_current_users_draft_and_renders(): void
    {
        [$institution,,$department] = $this->campus();
        $applicant = User::factory()->create(['role' => 'member', 'account_type' => 'department_representative']);
        $this->actingAs($applicant)->get(route('department.onboarding'))->assertOk()->assertSee('Your department. Ready to connect.');
        $this->putJson(route('department.onboarding.draft'), ['step' => 2, 'university_id' => $institution->id, 'department_name' => 'Draft department', 'faculty_name' => 'New school'])->assertOk();
        $this->assertDatabaseHas('department_onboarding_drafts', ['user_id' => $applicant->id, 'step' => 2]);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('department.onboarding'))->assertOk()->assertDontSee('Draft department');
        $this->getJson(route('academic.directory', ['university_id' => $institution->id]))->assertOk()->assertJsonFragment(['name' => $department->name]);
    }

    public function test_missing_institution_addition_does_not_grant_institution_adoption_or_admin_role(): void
    {
        $applicant = User::factory()->create(['role' => 'member', 'account_type' => 'department_representative']);
        $this->actingAs($applicant)->post(route('department.onboarding.institution'), ['institution_name' => 'Requested New College', 'institution_type' => 'college'])->assertRedirect();
        $request = InstitutionOnboardingRequest::firstOrFail();
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->post(route('admin.institution-onboarding.approve', $request))->assertRedirect();
        $this->assertDatabaseHas('universities', ['name' => 'Requested New College', 'adoption_status' => 'unclaimed']);
        $this->assertSame('member', $applicant->fresh()->role);
        $this->assertFalse($applicant->institutionAffiliations()->approved()->exists());
    }

    public function test_department_adoption_rejects_a_faculty_from_another_institution(): void
    {
        [$institution] = $this->campus();
        [, $otherFaculty] = $this->campus();
        $this->expectException(ValidationException::class);
        app(InstitutionAdoptionService::class)->createDepartmentAdoption(User::factory()->create(), $institution, $otherFaculty, null, ['department_name' => 'New department', 'representative_title' => 'Head']);
    }

    public function test_separate_departments_can_apply_in_the_same_institution(): void
    {
        [$institution,$faculty] = $this->campus();
        $service = app(InstitutionAdoptionService::class);
        foreach (['Mathematics', 'Physics'] as $name) {
            $service->createDepartmentAdoption(User::factory()->create(), $institution, $faculty, null, ['department_name' => $name, 'representative_title' => 'Head']);
        }
        $this->assertDatabaseCount('department_adoption_requests', 2);
    }

    public function test_department_adoption_is_independent_even_if_parent_institution_is_active(): void
    {
        [$institution,$faculty] = $this->campus('active');
        $user = User::factory()->create(['role' => 'member', 'account_type' => 'department_representative']);
        $super = User::factory()->create(['role' => 'super_admin']);
        $service = app(InstitutionAdoptionService::class);
        $request = $service->createDepartmentAdoption($user, $institution, $faculty, null, ['department_name' => 'Fresh department', 'representative_title' => 'Head']);
        $department = $service->approveDepartmentAdoption($request, $super);
        $this->assertSame('independent_active', $department->adoption_status);
        $this->assertDatabaseHas('user_institution_affiliations', ['user_id' => $user->id, 'department_id' => $department->id, 'status' => 'verified', 'affiliation_type' => 'department_admin']);
        $this->assertDatabaseHas('commercial_account_requests', ['department_id' => $department->id, 'request_type' => 'account_setup', 'status' => 'pending']);
        $this->assertDatabaseCount('commercial_accounts', 0);
        $this->assertDatabaseCount('user_subscriptions', 0);
    }

    public function test_reapplication_never_downgrades_verified_membership(): void
    {
        [$institution,,$department] = $this->campus();
        $student = $this->member($institution, $department, 'student');
        try {
            app(InstitutionAdoptionService::class)->requestAffiliationVerification($student, $institution, $department, 'student');
            $this->fail('Expected duplicate approved membership to be rejected.');
        } catch (ValidationException) {
        }
        $this->assertTrue($student->institutionAffiliations()->first()->isVerified());
        app(InstitutionAdoptionService::class)->recordSelfDeclared($student);
        $this->assertTrue($student->institutionAffiliations()->first()->isVerified());
    }

    public function test_independent_student_and_lecturer_connect_existing_accounts_by_approval(): void
    {
        [$institution,,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        foreach (['student', 'lecturer'] as $role) {
            $user = User::factory()->create(['role' => $role, 'account_type' => $role]);
            $request = app(InstitutionAdoptionService::class)->requestAffiliationVerification($user, $institution, $department, $role);
            $this->assertNull($user->fresh()->university_id);
            $affiliation = app(InstitutionAdoptionService::class)->approveAccessRequest($request, $admin);
            $this->assertSame($user->id, $affiliation->user_id);
            $this->assertTrue($affiliation->isVerified());
            $this->assertSame($role, $user->fresh()->role);
        }
        $this->assertDatabaseCount('users', 3);
    }

    public function test_requests_for_different_departments_and_roles_remain_separate(): void
    {
        [$institution,$faculty,$first] = $this->campus();
        $second = Department::factory()->create(['faculty_id' => $faculty->id, 'adoption_status' => 'independent_active']);
        $user = User::factory()->create();
        $service = app(InstitutionAdoptionService::class);
        $a = $service->requestAffiliationVerification($user, $institution, $first, 'student');
        $b = $service->requestAffiliationVerification($user, $institution, $second, 'lecturer');
        $this->assertNotSame($a->id, $b->id);
        $this->assertDatabaseCount('user_institution_affiliations', 2);
    }

    public function test_secure_invitation_connects_existing_account_once_and_rejects_other_email(): void
    {
        [$institution,,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        $student = User::factory()->create();
        $other = User::factory()->create();
        $service = app(AcademicMembershipService::class);
        $result = $service->invite($admin, $institution, $department, 'student', $student->email);
        $token = basename($result['url']);
        $this->assertNotSame($token, $result['invitation']->token_hash);
        $this->actingAs($other)->post(route('academic.invitations.consume', $token))->assertForbidden();
        $this->actingAs($student)->get(route('academic.invitations.accept', $token))->assertOk();
        $this->post(route('academic.invitations.consume', $token))->assertRedirect();
        $this->assertDatabaseHas('academic_membership_invitations', ['id' => $result['invitation']->id, 'accepted_by' => $student->id]);
        $this->post(route('academic.invitations.consume', $token))->assertStatus(410);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_expired_and_revoked_invitations_cannot_be_accepted(): void
    {
        [$institution,,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        $student = User::factory()->create();
        $service = app(AcademicMembershipService::class);
        $expired = $service->invite($admin, $institution, $department, 'student', $student->email);
        $expired['invitation']->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($student)->post(route('academic.invitations.consume', basename($expired['url'])))->assertStatus(410);
        $revoked = $service->invite($admin, $institution, $department, 'student', $student->email);
        $service->revokeInvitation($admin, $revoked['invitation']);
        $this->post(route('academic.invitations.consume', basename($revoked['url'])))->assertStatus(410);
    }

    public function test_department_admin_cannot_invite_or_manage_another_department(): void
    {
        [$institution,$faculty,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        $other = Department::factory()->create(['faculty_id' => $faculty->id, 'adoption_status' => 'independent_active']);
        $this->actingAs($admin)->postJson(route('academic.invitations.store'), ['university_id' => $institution->id, 'department_id' => $other->id, 'role' => 'student', 'email' => 'outside@example.test'])->assertForbidden();
        $this->putJson(route('academic.departments.modules', $other), ['modules' => ['research_studio' => 'enabled']])->assertForbidden();
    }

    public function test_institution_activation_does_not_take_over_departments_or_paid_balances(): void
    {
        [$institution,,$department] = $this->campus();
        $super = User::factory()->create(['role' => 'super_admin']);
        $adoptedAt = $department->adopted_at->toISOString();
        $account = CommercialAccount::create(['department_id' => $department->id, 'name' => 'Department account', 'currency' => 'NGN', 'prepaid_balance_minor' => 14000]);
        $institution->update(['adoption_status' => 'active']);
        $this->assertSame(1, app(InstitutionAdoptionService::class)->migrateIndependentDepartments($institution, $super));
        $this->assertSame('independent_active', $department->fresh()->adoption_status);
        $this->assertSame($adoptedAt, $department->fresh()->adopted_at->toISOString());
        $this->assertSame(14000, $account->fresh()->prepaid_balance_minor);
    }

    public function test_two_party_affiliation_requires_verification_and_preserves_ids_and_history(): void
    {
        [$institution,,$department] = $this->campus('active');
        $institutionAdmin = $this->member($institution, null, 'university_admin');
        $departmentAdmin = $this->member($institution, $department, 'department_admin');
        $student = $this->member($institution, $department, 'student');
        $super = User::factory()->create(['role' => 'super_admin']);
        $original = $department->getRawOriginal();
        $memberIds = $department->affiliations()->pluck('id')->all();
        $service = app(DepartmentAffiliationService::class);
        $agreement = $service->request($institutionAdmin, $institution, $department, ['share_academic_data' => true, 'inherit_features' => true], 'institution');
        $this->assertFalse(app(AcademicOrganisationService::class)->canManageDepartment($institutionAdmin, $department));
        $this->actingAs($institutionAdmin)->postJson(route('academic.department-affiliations.respond', $agreement), ['action' => 'verify'])->assertForbidden();
        $service->respond($departmentAdmin, $agreement, 'accept');
        $this->assertSame('independent_active', $department->fresh()->adoption_status);
        $service->respond($super, $agreement, 'verify');
        $this->assertSame('institution_affiliated', $department->fresh()->adoption_status);
        $this->assertSame($original['id'], $department->fresh()->id);
        $this->assertSame($original['adopted_at'], $department->fresh()->getRawOriginal('adopted_at'));
        $this->assertSame($memberIds, $department->affiliations()->pluck('id')->all());
        $this->assertTrue(app(AcademicOrganisationService::class)->canManageDepartment($institutionAdmin, $department->fresh()));
        $this->assertTrue(app(AcademicOrganisationService::class)->hasInstitutionAccess($student));
        $service->respond($departmentAdmin, $agreement, 'end');
        $this->assertSame('independent_active', $department->fresh()->adoption_status);
        $this->assertFalse(app(AcademicOrganisationService::class)->canManageDepartment($institutionAdmin, $department->fresh()));
        $this->assertSame($memberIds, $department->affiliations()->pluck('id')->all());
    }

    public function test_department_can_decline_affiliation_and_institution_cannot_supply_consent(): void
    {
        [$institution,,$department] = $this->campus('active');
        $ia = $this->member($institution, null, 'university_admin');
        $da = $this->member($institution, $department, 'department_admin');
        $service = app(DepartmentAffiliationService::class);
        $agreement = $service->request($ia, $institution, $department, []);
        $this->actingAs($ia)->postJson(route('academic.department-affiliations.respond', $agreement), ['action' => 'accept'])->assertForbidden();
        $service->respond($da, $agreement, 'decline');
        $this->assertSame('declined', $agreement->fresh()->status);
        $this->assertSame('independent_active', $department->fresh()->adoption_status);
    }

    public function test_membership_context_switch_is_authorised_and_does_not_rewrite_profile(): void
    {
        [$a,,$ad] = $this->campus();
        [$b,,$bd] = $this->campus();
        $user = $this->member($a, $ad, 'student');
        $primary = $user->getRawOriginal();
        $other = UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $b->id, 'faculty_id' => $bd->faculty_id, 'department_id' => $bd->id, 'affiliation_type' => 'lecturer', 'status' => 'verified', 'verified_at' => now()]);
        $this->actingAs($user)->post(route('academic.context.switch'), ['affiliation_id' => $other->id])->assertRedirect();
        $this->get(route('academic.organisations'))->assertOk()->assertSee('Current workspace');
        $this->assertSame($primary['role'], $user->fresh()->role);
        $this->assertSame($primary['university_id'], $user->fresh()->university_id);
        $this->post(route('academic.context.switch'), ['affiliation_id' => 0])->assertRedirect();
        $stranger = $this->member($b, $bd, 'student')->institutionAffiliations()->first();
        $this->post(route('academic.context.switch'), ['affiliation_id' => $stranger->id])->assertNotFound();
    }

    public function test_api_cannot_choose_someone_elses_affiliation_and_requires_real_department_for_admin(): void
    {
        [$institution,,$department] = $this->campus();
        $user = User::factory()->create();
        $stranger = $this->member($institution, $department, 'student');
        Sanctum::actingAs($user, ['platform:access']);
        $this->getJson('/api/v1/academic/affiliations', ['X-Academic-Affiliation' => (string) $stranger->institutionAffiliations()->first()->id])->assertForbidden();
        $this->postJson('/api/v1/institution-access', ['university_id' => $institution->id, 'requested_role' => 'department_admin'])->assertStatus(422);
    }

    public function test_revocation_removes_sponsorship_but_preserves_personally_paid_access(): void
    {
        [$institution,,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        $student = $this->member($institution, $department, 'student');
        PricingRule::create(['key' => 'feature.document_generation', 'name' => 'Document generation', 'scope_type' => 'global', 'scope_id' => 0, 'currency' => 'NGN', 'unit_amount_minor' => 200, 'enabled' => true, 'metadata' => ['pricing_type' => 'fixed', 'feature' => 'document_generation'], 'starts_at' => now()]);
        $account = CommercialAccount::create(['department_id' => $department->id, 'name' => 'Department account', 'status' => 'active', 'currency' => 'NGN', 'metadata' => ['sponsored_features' => ['document_generation']]]);
        $entitlements = app(EntitlementService::class);
        $this->assertTrue($entitlements->has($student, 'document_generation'));
        $membership = $student->institutionAffiliations()->first();
        app(AcademicMembershipService::class)->end($admin, $membership, 'revoked', 'No longer enrolled');
        $this->assertFalse($entitlements->has($student->fresh(), 'document_generation'));
        FeatureEntitlement::create(['user_id' => $student->id, 'feature' => 'document_generation', 'access_type' => 'purchased', 'status' => 'active', 'starts_at' => now()]);
        $this->assertTrue($entitlements->has($student->fresh(), 'document_generation'));
        $this->assertSame('active', $account->fresh()->status);
    }

    public function test_expired_department_plan_falls_back_to_authorised_institution_without_moving_funds(): void
    {
        [$institution,,$department] = $this->campus('active');
        $department->update(['adoption_status' => 'institution_affiliated', 'adoption_settings' => ['inherit_institution_features' => true, 'billing_owner' => 'department']]);
        $student = $this->member($institution, $department, 'student');
        $a = CommercialAccount::create(['department_id' => $department->id, 'name' => 'Department', 'status' => 'active', 'currency' => 'NGN', 'prepaid_balance_minor' => 4000, 'metadata' => ['sponsored_features' => ['document_generation']]]);
        $b = CommercialAccount::create(['university_id' => $institution->id, 'name' => 'Institution', 'status' => 'active', 'currency' => 'NGN', 'prepaid_balance_minor' => 9000, 'metadata' => ['sponsored_features' => ['document_generation']]]);
        $resolver = app(AcademicSponsorshipService::class);
        $this->assertSame($a->id, $resolver->account($student, 'document_generation')->id);
        $a->update(['access_ends_at' => now()->subDay()]);
        $this->assertSame($b->id, $resolver->account($student, 'document_generation')->id);
        $this->assertSame(4000, $a->fresh()->prepaid_balance_minor);
        $this->assertSame(9000, $b->fresh()->prepaid_balance_minor);
    }

    public function test_department_calendar_and_commercial_account_are_scoped(): void
    {
        [$institution,,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        $this->actingAs($admin)->post(route('academic.departments.calendar', $department), ['name' => '2026/2027 Semester one', 'start_date' => '2026-10-21', 'end_date' => '2027-03-20'])->assertRedirect();
        $this->assertDatabaseHas('academic_sessions', ['department_id' => $department->id, 'name' => '2026/2027 Semester one']);
        $this->get(route('admin.department'))->assertOk()->assertSee('2026/2027 Semester one');
        $this->get(route('institution.commercial-account'))->assertOk()->assertSee($department->name);
        $super = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($super)->post(route('admin.monetization.commercial-accounts.store'), ['university_id' => $institution->id, 'department_id' => $department->id, 'prepaid_balance' => '100.00', 'plan_key' => 'department_core'])->assertRedirect();
        $this->assertDatabaseHas('commercial_accounts', ['department_id' => $department->id, 'university_id' => null, 'prepaid_balance_minor' => 10000]);
        $this->get(route('admin.monetization', ['department' => $department->id]))->assertOk()->assertSee($department->name);
    }

    public function test_institution_admin_queries_exclude_independent_department_courses_until_consensual_affiliation(): void
    {
        [$institution,$faculty,$department] = $this->campus('active');
        $ia = $this->member($institution, null, 'university_admin');
        $da = $this->member($institution, $department, 'department_admin');
        $super = User::factory()->create(['role' => 'super_admin']);
        $course = Course::factory()->create(['department_id' => $department->id, 'access_mode' => 'institutional']);
        $this->actingAs($ia);
        $this->assertFalse(Course::whereKey($course->id)->exists());
        $this->assertFalse($ia->canAccessCourse($course));
        $service = app(DepartmentAffiliationService::class);
        $agreement = $service->request($ia, $institution, $department, ['share_academic_data' => true]);
        $service->respond($da, $agreement, 'accept');
        $service->respond($super, $agreement, 'verify');
        $this->assertTrue(Course::whereKey($course->id)->exists());
        $this->assertTrue($ia->canAccessCourse($course->fresh()));
        $service->respond($da, $agreement, 'end');
        $this->assertFalse(Course::whereKey($course->id)->exists());
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_personal_research_and_documents_survive_membership_and_department_affiliation(): void
    {
        [$institution,,$department] = $this->campus('active');
        $student = User::factory()->create();
        $context = app(IndependentAcademicContextService::class)->context();
        $project = ResearchProject::create(['university_id' => $context['university']->id, 'department_id' => $context['department']->id, 'academic_session_id' => $context['session']->id, 'semester_id' => $context['semester']->id, 'research_type_id' => app(IndependentAcademicContextService::class)->ensureResearchType()->id, 'owner_id' => $student->id, 'title' => 'Personal final-year research', 'workspace_mode' => 'independent', 'status' => 'draft']);
        $document = ContentDocument::create(['owner_id' => $student->id, 'title' => 'My existing draft', 'body' => 'Existing academic work must remain mine.', 'document_type' => 'research_section', 'editor_mode' => 'rich_text', 'status' => 'draft', 'visibility' => 'private']);
        $before = $document->fresh()->getRawOriginal();
        $da = $this->member($institution, $department, 'department_admin');
        $ia = $this->member($institution, null, 'university_admin');
        $super = User::factory()->create(['role' => 'super_admin']);
        $request = app(InstitutionAdoptionService::class)->requestAffiliationVerification($student, $institution, $department, 'student');
        app(InstitutionAdoptionService::class)->approveAccessRequest($request, $da);
        $agreement = app(DepartmentAffiliationService::class)->request($ia, $institution, $department, ['share_academic_data' => true]);
        app(DepartmentAffiliationService::class)->respond($da, $agreement, 'accept');
        app(DepartmentAffiliationService::class)->respond($super, $agreement, 'verify');
        $this->assertSame($before, $document->fresh()->getRawOriginal());
        $this->assertSame($student->id, $project->fresh()->owner_id);
        $this->assertTrue(app(ResearchProjectPolicy::class)->view($student->fresh(), $project));
        $this->assertFalse(app(ResearchProjectPolicy::class)->view($ia, $project));
    }

    public function test_department_module_preferences_cannot_enable_a_platform_disabled_feature(): void
    {
        [$institution,,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        FeatureAccessService::setStatus('research_studio', 'disabled', $admin->id);
        $this->actingAs($admin)->put(route('academic.departments.modules', $department), ['modules' => ['research_studio' => 'enabled']])->assertRedirect();
        $student = $this->member($institution, $department, 'student');
        $this->actingAs($student)->get(route('research.index'))->assertStatus(403);
        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_a_department_cannot_change_parent_institution_provider_or_channel_settings(): void
    {
        [$institution,,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        $this->actingAs($admin)->postJson(route('ai.settings.update'), [])->assertForbidden();
        $this->putJson(route('admin.notifications.update-channels'), ['email_notifications_enabled' => false, 'in_app_notifications_enabled' => false, 'push_notifications_enabled' => false])->assertForbidden();
    }

    public function test_department_pay_per_use_uses_its_account_and_release_restores_only_that_account(): void
    {
        [$institution,,$department] = $this->campus();
        $student = $this->member($institution, $department, 'student');
        PricingRule::create(['key' => 'feature.document_generation', 'name' => 'Documents', 'scope_type' => 'global', 'scope_id' => 0, 'currency' => 'NGN', 'unit_amount_minor' => 200, 'enabled' => true, 'metadata' => ['pricing_type' => 'pay_per_use', 'feature' => 'document_generation'], 'starts_at' => now()]);
        $account = CommercialAccount::create(['department_id' => $department->id, 'name' => 'Department sponsorship', 'status' => 'active', 'currency' => 'NGN', 'prepaid_balance_minor' => 5000, 'metadata' => ['sponsored_features' => ['document_generation']]]);
        $billing = app(FeatureBillingService::class);
        $reservation = $billing->begin($student, 'document_generation', 'department-reservation');
        $this->assertSame(4800, $account->fresh()->prepaid_balance_minor);
        $billing->release($student, $reservation);
        $this->assertSame(5000, $account->fresh()->prepaid_balance_minor);
        $this->assertDatabaseHas('feature_usage_charges', ['request_id' => 'department-reservation', 'commercial_account_id' => $account->id, 'status' => 'released']);
    }

    public function test_department_semester_invoices_are_idempotent_and_do_not_charge_sibling_departments(): void
    {
        [$institution,$faculty,$department] = $this->campus();
        $admin = $this->member($institution, $department, 'department_admin');
        $student = $this->member($institution, $department, 'student');
        $sibling = Department::factory()->create(['faculty_id' => $faculty->id, 'adoption_status' => 'independent_active']);
        $other = $this->member($institution, $sibling, 'student');
        CommercialAccount::create(['department_id' => $department->id, 'name' => 'Department terms', 'status' => 'active', 'currency' => 'NGN', 'metadata' => ['student_semester_fee_minor' => 10000]]);
        $this->actingAs($admin)->post(route('academic.departments.calendar', $department), ['name' => 'Approved period', 'start_date' => '2026-10-21', 'end_date' => '2027-04-01'])->assertRedirect();
        $semester = app(AcademicContextService::class)->activeSemesterForDepartment($department->id);
        $this->post(route('billing.generate-invoices', $semester))->assertRedirect();
        $this->post(route('billing.generate-invoices', $semester))->assertRedirect();
        $this->assertDatabaseHas('invoices', ['user_id' => $student->id, 'department_id' => $department->id, 'amount_minor' => 10000]);
        $this->assertDatabaseMissing('invoices', ['user_id' => $other->id]);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_department_administrator_nomination_does_not_grant_privileges_until_platform_review(): void
    {
        [$institution,,$department] = $this->campus('active');
        $department->update(['adoption_status' => 'institution_managed']);
        $ia = $this->member($institution, null, 'university_admin');
        $candidate = User::factory()->create();
        $invitation = app(AcademicMembershipService::class)->invite($ia, $institution, $department, 'department_admin', $candidate->email);
        $membership = app(AcademicMembershipService::class)->accept($candidate, basename($invitation['url']));
        $this->assertSame('pending_verification', $membership->status);
        $this->assertSame('student', $candidate->fresh()->role);
        $this->assertDatabaseHas('institution_access_requests', ['user_id' => $candidate->id, 'department_id' => $department->id, 'requested_role' => 'department_admin', 'status' => 'pending']);
    }

    public function test_queued_course_work_restores_scoped_role_without_overwriting_primary_profile(): void
    {
        [$first,,$firstDepartment] = $this->campus();
        [$second,,$secondDepartment] = $this->campus();
        $user = $this->member($first, $firstDepartment, 'student');
        $membership = UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $second->id, 'faculty_id' => $secondDepartment->faculty_id, 'department_id' => $secondDepartment->id, 'affiliation_type' => 'lecturer', 'status' => 'verified', 'verified_at' => now()]);
        $course = Course::factory()->create(['department_id' => $secondDepartment->id, 'access_mode' => 'institutional']);
        $session = AcademicSession::factory()->create(['university_id' => $second->id, 'department_id' => $secondDepartment->id]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id]);
        LecturerCourseAssignment::create(['user_id' => $user->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'is_coordinator' => true]);
        app(AcademicOrganisationService::class)->restoreCourseActor($user, $course, 'lecturer');
        $this->assertSame('lecturer', $user->role);
        $this->assertSame($second->id, $user->university_id);
        $this->assertSame('student', $user->fresh()->role);
        $this->assertSame($first->id, $user->fresh()->university_id);
        $membership->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->expectException(HttpException::class);
        app(AcademicOrganisationService::class)->restoreCourseActor($user->fresh(), $course, 'lecturer');
    }

    public function test_verification_documents_are_private_to_the_applicant_and_platform_reviewer(): void
    {
        [$institution,$faculty,$department] = $this->campus();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $super = User::factory()->create(['role' => 'super_admin']);
        Storage::fake('local');
        Storage::disk('local')->put('verification/authority.pdf', '%PDF-1.4 private verification fixture');
        $asset = MediaAsset::create(['owner_id' => $owner->id, 'disk' => 'local', 'path' => 'verification/authority.pdf', 'original_name' => 'authority.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 40, 'visibility' => 'private', 'scan_status' => 'clean', 'scan_provider' => 'fixture']);
        $application = DepartmentAdoptionRequest::create(['requester_user_id' => $owner->id, 'university_id' => $institution->id, 'faculty_id' => $faculty->id, 'department_id' => $department->id, 'representative_title' => 'Representative', 'status' => 'pending', 'metadata' => ['evidence_media_id' => $asset->id]]);
        $this->actingAs($other)->get(route('department.onboarding.evidence', $application))->assertForbidden();
        $this->actingAs($owner)->get(route('department.onboarding.evidence', $application))->assertDownload('authority.pdf');
        $this->actingAs($super)->get(route('department.onboarding.evidence', $application))->assertDownload('authority.pdf');
    }

    public function test_a_lecturer_with_another_primary_profile_can_be_assigned_in_the_approved_department(): void
    {
        [$primary,,$primaryDepartment] = $this->campus();
        [$target,,$targetDepartment] = $this->campus();
        $user = $this->member($primary, $primaryDepartment, 'student');
        $admin = $this->member($target, $targetDepartment, 'department_admin');
        UserInstitutionAffiliation::create(['user_id' => $user->id, 'university_id' => $target->id, 'faculty_id' => $targetDepartment->faculty_id, 'department_id' => $targetDepartment->id, 'affiliation_type' => 'lecturer', 'status' => 'verified', 'verified_at' => now()]);
        $course = Course::factory()->create(['department_id' => $targetDepartment->id, 'access_mode' => 'institutional']);
        $session = AcademicSession::factory()->create(['university_id' => $target->id, 'department_id' => $targetDepartment->id]);
        Semester::factory()->create(['academic_session_id' => $session->id, 'is_active' => true]);
        $this->actingAs($admin)->get(route('admin.courses.show', $course))->assertOk()->assertViewHas('lecturers', fn ($rows) => $rows->contains('id', $user->id));
        $this->post(route('admin.courses.lecturers.store', $course), ['user_id' => $user->id])->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('lecturer_course_assignments', ['user_id' => $user->id, 'course_id' => $course->id]);
        $this->assertSame('student',$user->fresh()->role);
        $this->assertSame($primary->id,$user->fresh()->university_id);
    }
    public function test_typed_department_and_school_names_reuse_case_insensitive_directory_matches():void
    {
        [$institution,$faculty,$department]=$this->campus();$department->update(['name'=>'Existing Research Department','adoption_status'=>'unclaimed']);
        $applicant=User::factory()->create(['role'=>'member','account_type'=>'department_representative']);$super=User::factory()->create(['role'=>'super_admin']);
        $service=app(InstitutionAdoptionService::class);
        $request=$service->createDepartmentAdoption($applicant,$institution,null,null,['faculty_name'=>strtoupper($faculty->name),'department_name'=>'EXISTING RESEARCH DEPARTMENT','representative_title'=>'Head']);
        $approved=$service->approveDepartmentAdoption($request,$super);
        $this->assertSame($department->id,$approved->id);$this->assertDatabaseCount('faculties',1);$this->assertDatabaseCount('departments',1);
    }

    public function test_an_administrator_switches_context_before_course_model_binding():void
    {
        [$first,,$a]=$this->campus();[$second,,$b]=$this->campus();$admin=$this->member($first,$a,'department_admin');
        $membership=UserInstitutionAffiliation::create(['user_id'=>$admin->id,'university_id'=>$second->id,'faculty_id'=>$b->faculty_id,'department_id'=>$b->id,'affiliation_type'=>'department_admin','status'=>'verified','verified_at'=>now()]);
        $firstCourse=\App\Models\Course::factory()->create(['department_id'=>$a->id,'access_mode'=>'institutional']);
        $secondCourse=\App\Models\Course::factory()->create(['department_id'=>$b->id,'access_mode'=>'institutional']);
        $this->actingAs($admin)->post(route('academic.context.switch'),['affiliation_id'=>$membership->id])->assertRedirect();
        $this->get(route('admin.courses.show',$secondCourse))->assertOk();
        $this->get(route('admin.courses.show',$firstCourse))->assertNotFound();
        $this->assertSame($first->id,$admin->fresh()->university_id);
    }

}
