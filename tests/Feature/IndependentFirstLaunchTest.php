<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LecturerCourseAssignment;
use App\Models\ResearchProject;
use App\Models\User;
use App\Policies\ResearchProjectPolicy;
use App\Services\IndependentAcademicContextService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndependentFirstLaunchTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_personas_do_not_require_institutional_onboarding(): void
    {
        $this->assertSame(['student', 'lecturer', 'researcher', 'independent_professional', 'author_publisher', 'other'], array_keys(OnboardingService::PUBLIC_ACCOUNT_PATHS));
        foreach (array_keys(OnboardingService::PUBLIC_ACCOUNT_PATHS) as $path) {
            $this->assertNotContains($path, OnboardingService::INSTITUTIONAL_PATHS);
        }
        $this->assertNotContains('student', OnboardingService::INSTITUTIONAL_PATHS);
        $this->assertNotContains('lecturer', OnboardingService::INSTITUTIONAL_PATHS);
        $this->assertNotContains('researcher', OnboardingService::INSTITUTIONAL_PATHS);
    }

    public function test_launch_feature_profile_keeps_core_enabled_and_non_core_admin_controlled(): void
    {
        $definitions = (array) config('features.definitions', []);

        foreach (['courses', 'research_studio', 'ai_assistant', 'wallet_billing', 'documents_exports'] as $feature) {
            $this->assertSame('enabled', $definitions[$feature]['default_status'] ?? null, $feature.' should launch enabled.');
        }

        foreach (['attendance', 'siwes_module', 'commerce_marketplace', 'creator_payouts', 'institution_self_service'] as $feature) {
            $this->assertSame('disabled', $definitions[$feature]['default_status'] ?? null, $feature.' should launch disabled but remain registered.');
        }
    }

    public function test_independent_classroom_is_scoped_by_explicit_participation_not_institution(): void
    {
        $context = app(IndependentAcademicContextService::class)->context();
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer', 'university_id' => null, 'department_id' => null]);
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => null, 'department_id' => null]);

        $course = Course::query()->create([
            'department_id' => $context['department']->id,
            'access_mode' => 'independent',
            'owner_user_id' => $lecturer->id,
            'code' => 'IND-'.strtoupper(substr((string) $lecturer->uuid, 0, 6)),
            'name' => 'Independent Classroom',
            'credit_hours' => 3,
            'level' => 'General',
            'semester' => 'Independent',
            'type' => 'elective',
            'pass_mark' => 40,
            'is_active' => true,
        ]);

        $this->assertTrue($lecturer->fresh()->canAccessCourse($course));
        $this->assertFalse($student->fresh()->canAccessCourse($course));

        Enrollment::query()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'semester_id' => $context['semester']->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $this->assertTrue($student->fresh()->canAccessCourse($course));
        $this->assertNull($student->fresh()->university_id, 'Independent enrollment must not attach the student to the synthetic institution.');
    }

    public function test_lecturer_can_create_an_independent_classroom_with_the_default_semester_and_a_descriptive_level(): void
    {
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => null,
            'department_id' => null,
        ]);

        $response = $this->actingAs($lecturer)->post(route('lecturer.courses.independent.store'), [
            'name' => 'Machine Learning',
            'code' => 'ML101',
            'description' => 'Learning machine models.',
            'credit_hours' => 3,
            'level' => 'Postgraduate Diploma',
            'type' => 'elective',
            'pass_mark' => 40,
        ]);

        $course = Course::query()->where('code', 'ML101')->firstOrFail();
        $response->assertRedirect(route('courses.show', $course));
        $this->assertSame('Independent', $course->semester);
        $this->assertSame('Postgraduate Diploma', $course->level);
        $this->assertDatabaseHas('lecturer_course_assignments', [
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'is_coordinator' => true,
        ]);
        $this->assertSame(1, LecturerCourseAssignment::query()->where('course_id', $course->id)->count());

    }

    public function test_independent_research_is_visible_only_to_explicit_participants(): void
    {
        $independent = app(IndependentAcademicContextService::class);
        $context = $independent->context();
        $type = $independent->ensureResearchType();
        $owner = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => null]);
        $stranger = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => null]);

        $project = ResearchProject::query()->create([
            'university_id' => $context['university']->id,
            'department_id' => $context['department']->id,
            'workspace_mode' => 'independent',
            'academic_session_id' => $context['session']->id,
            'semester_id' => $context['semester']->id,
            'research_type_id' => $type->id,
            'owner_id' => $owner->id,
            'title' => 'Independent Research Workspace',
            'status' => 'draft',
            'progress' => 0,
        ]);

        $policy = app(ResearchProjectPolicy::class);
        $this->assertTrue($policy->view($owner, $project));
        $this->assertFalse($policy->view($stranger, $project));
        $this->assertNull($owner->fresh()->university_id, 'Independent research must not attach the owner to the synthetic institution.');
    }
}
