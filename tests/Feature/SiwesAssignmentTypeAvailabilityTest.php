<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\SubmissionTask;
use App\Models\University;
use App\Models\User;
use App\Services\Assignments\AssignmentTypeProfile;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the administrator-controlled availability of the SIWES assignment
 * type: disabled by default, admin-toggleable, hidden and rejected while
 * disabled, fully functional once enabled, and protected from non-admins.
 */
class SiwesAssignmentTypeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,\App\Models\Model> */
    private function context(): array
    {
        $university = University::factory()->create(['adoption_status'=>'active']);
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id,'adoption_status'=>'institution_managed']);
        $session = AcademicSession::factory()->create(['university_id' => $university->id, 'is_active' => true]);
        $semester = Semester::factory()->create(['academic_session_id' => $session->id, 'is_active' => true, 'start_date' => now()->subWeek(), 'end_date' => now()->addMonths(3)]);
        $course = Course::factory()->create(['department_id' => $department->id]);

        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        LecturerCourseAssignment::create([
            'course_id' => $course->id,
            'user_id' => $lecturer->id,
            'semester_id' => $semester->id,
            'is_coordinator' => false,
        ]);

        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'account_type' => 'admin',
        ]);

        $universityAdmin = User::factory()->create([
            'role' => 'university_admin',
            'account_type' => 'admin',
            'university_id' => $university->id,
        ]);

        foreach([$lecturer,$student] as $approved)\App\Models\UserInstitutionAffiliation::create(['user_id'=>$approved->id,'university_id'=>$university->id,'department_id'=>$department->id,'faculty_id'=>$faculty->id,'affiliation_type'=>$approved->role,'status'=>'verified','verified_at'=>now()]);
        return compact('university', 'faculty', 'department', 'session', 'semester', 'course', 'lecturer', 'student', 'superAdmin', 'universityAdmin');
    }

    /** @return array<string,mixed> */
    private function siwesPayload(array $ctx): array
    {
        return [
            'title' => 'SIWES placement',
            'type' => 'siwes',
            'open_at' => now()->format('Y-m-d\TH:i'),
            'due_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'late_deadline' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'max_file_size_mb' => 10,
            'max_file_count' => 5,
            'min_file_count' => 1,
            'max_score' => 100,
            'submission_format' => 'file',
            'semester_id' => $ctx['semester']->id,
            'is_visible_to_students' => true,
            'status' => 'draft',
            'allowed_file_types' => 'pdf',
            'type_metadata' => [
                'placement_organisation' => 'Acme Ltd',
                'industry_supervisor' => 'Engr Bello',
                'start_date' => now()->format('Y-m-d'),
                'end_date' => now()->addMonths(3)->format('Y-m-d'),
                'duration_weeks' => 12,
                'logbook_expectation' => 'weekly',
                'evaluation_method' => 'both',
            ],
        ];
    }

    public function test_siwes_is_disabled_by_default(): void
    {
        $this->assertFalse(AssignmentTypeProfile::isEnabled('siwes'));
        $this->assertNotContains('siwes', AssignmentTypeProfile::enabledTypes());
        $this->assertDatabaseHas('settings', ['key' => 'siwes_assignment_enabled', 'value' => '0']);
    }

    public function test_admin_can_enable_siwes(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['superAdmin'])
            ->put(route('admin.settings.assignment-types.siwes'), ['enabled' => '1'])
            ->assertRedirect();

        $this->assertTrue(AssignmentTypeProfile::isEnabled('siwes'));
        $this->assertContains('siwes', AssignmentTypeProfile::enabledTypes());
    }

    public function test_disabled_siwes_cannot_be_created(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $this->siwesPayload($ctx))
            ->assertSessionHasErrors(['type']);

        $this->assertDatabaseCount('submission_tasks', 0);
    }

    public function test_disabled_siwes_is_not_selectable_on_the_create_form(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['lecturer'])
            ->get(route('submission-tasks.create', $ctx['course']))
            ->assertOk()
            ->assertDontSee('value="siwes"', false);
    }

    public function test_enabled_siwes_can_be_created(): void
    {
        $ctx = $this->context();
        SettingService::set('siwes_assignment_enabled', true, 'boolean');

        $this->actingAs($ctx['lecturer'])
            ->get(route('submission-tasks.create', $ctx['course']))
            ->assertOk()
            ->assertSee('value="siwes"', false);

        $this->actingAs($ctx['lecturer'])
            ->post(route('submission-tasks.store', $ctx['course']), $this->siwesPayload($ctx))
            ->assertRedirect();

        $this->assertDatabaseHas('submission_tasks', ['course_id' => $ctx['course']->id, 'type' => 'siwes']);
    }

    public function test_non_admin_cannot_change_the_siwes_setting(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['student'])
            ->put(route('admin.settings.assignment-types.siwes'), ['enabled' => '1'])
            ->assertForbidden();

        $this->assertFalse(AssignmentTypeProfile::isEnabled('siwes'));
    }

    public function test_university_admin_cannot_change_the_siwes_setting(): void
    {
        $ctx = $this->context();

        $this->actingAs($ctx['universityAdmin'])
            ->put(route('admin.settings.assignment-types.siwes'), ['enabled' => '1'])
            ->assertForbidden();

        $this->assertFalse(AssignmentTypeProfile::isEnabled('siwes'));
    }

    public function test_existing_siwes_task_is_unavailable_to_students(): void
    {
        $ctx = $this->context();
        Enrollment::create([
            'user_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        $task = SubmissionTask::factory()->create([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'type' => 'siwes',
            'status' => 'published',
            'is_visible_to_students' => true,
        ]);

        $this->actingAs($ctx['student'])
            ->get(route('submission-tasks.student.show', [$ctx['course'], $task]))
            ->assertOk()
            ->assertSee('Unavailable');

        $this->actingAs($ctx['student'])
            ->post(route('submissions.store'), [
                'course_id' => $ctx['course']->id,
                'submission_task_id' => $task->id,
                'type' => 'assignment',
                'title' => 'Attempt on unavailable task',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_existing_siwes_records_are_preserved_when_disabled(): void
    {
        $ctx = $this->context();

        $task = SubmissionTask::factory()->create([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'type' => 'siwes',
            'status' => 'draft',
        ]);

        // Disabling must never delete or mutate existing SIWES records.
        SettingService::set('siwes_assignment_enabled', false, 'boolean');

        $this->assertDatabaseHas('submission_tasks', ['id' => $task->id, 'type' => 'siwes']);
        $this->assertFalse(AssignmentTypeProfile::isEnabled('siwes'));
    }
}
