<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Enrollment;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\AcademicSession;
use App\Models\University;
use App\Models\Faculty;
use App\Models\Department;
use App\Services\Ai\AcademicAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AiAssistantRoutingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_feature_resolver_is_role_and_tool_aware(): void
    {
        $service = app(AcademicAssistantService::class);

        $student = User::factory()->make(['role' => 'student', 'account_type' => 'student']);
        $lecturer = User::factory()->make(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $researcher = User::factory()->make(['role' => 'researcher', 'account_type' => 'researcher']);
        $admin = User::factory()->make(['role' => 'department_admin', 'account_type' => 'staff']);

        $this->assertSame('study_assistant', $service->featureFor($student, 'ask'));
        $this->assertSame('lecturer_assistant', $service->featureFor($lecturer, 'ask'));
        $this->assertSame('research_assistant', $service->featureFor($researcher, 'ask'));
        $this->assertSame('lecturer_assistant', $service->featureFor($admin, 'ask'));

        $this->assertSame('research_assistant', $service->featureFor($lecturer, 'ask', 'research_assistant'));
        $this->assertSame('lecturer_assistant', $service->featureFor($lecturer, 'ask', 'unsupported_assistant'));

        $this->assertSame('writing_assistant', $service->featureFor($student, 'writing'));
        $this->assertSame('writing_assistant', $service->featureFor($lecturer, 'writing'));

        $this->assertSame('citation_assistant', $service->featureFor($student, 'citation'));
        $this->assertSame('citation_assistant', $service->featureFor($lecturer, 'citation'));
    }

    public function test_assistant_experience_exposes_role_appropriate_options(): void
    {
        $service = app(AcademicAssistantService::class);
        $student = User::factory()->make(['role' => 'student', 'account_type' => 'student']);
        $lecturer = User::factory()->make(['role' => 'lecturer', 'account_type' => 'lecturer']);

        $studentOptions = collect($service->assistantExperience($student)['assistant_options'])->pluck('feature')->all();
        $lecturerOptions = collect($service->assistantExperience($lecturer)['assistant_options'])->pluck('feature')->all();

        $this->assertSame(['study_assistant'], $studentOptions);
        $this->assertContains('lecturer_assistant', $lecturerOptions);
        $this->assertContains('research_assistant', $lecturerOptions);
    }

    public function test_selected_empty_course_context_is_sent_as_draft_only_metadata(): void
    {
        [$course, $student] = $this->courseWithStudent();
        $service = app(AcademicAssistantService::class);
        $method = new ReflectionMethod($service, 'courseContextFor');
        $method->setAccessible(true);

        $context = $method->invoke($service, $course->id, $student);

        $this->assertSame($course->id, $context['course_id']);
        $this->assertSame([], $context['known_topics']);
        $this->assertSame([], $context['sample_materials']);
        $this->assertFalse($context['has_topics']);
        $this->assertFalse($context['has_materials']);
        $this->assertSame('course_selected_no_material_metadata', $context['context_status']);
        $this->assertSame('assistant_response_only_no_course_content_is_created_or_updated', $context['persistence_policy']);
    }

    public function test_lecturer_course_context_includes_material_topics_for_generation_requests(): void
    {
        [$course, , $lecturer, $semester] = $this->courseWithStudentAndLecturer();
        CourseMaterial::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'uploaded_by' => $lecturer->id,
            'title' => 'Database normalization slides',
            'description' => 'Functional dependencies and normal forms',
            'type' => 'slides',
            'file_name' => 'normalization.pdf',
            'file_path' => 'materials/normalization.pdf',
            'topic' => 'Database normalization',
            'week_number' => 4,
            'sequence_order' => 1,
            'is_visible' => false,
        ]);

        $service = app(AcademicAssistantService::class);
        $method = new ReflectionMethod($service, 'courseContextFor');
        $method->setAccessible(true);

        $context = $method->invoke($service, $course->id, $lecturer);

        $this->assertSame(['Database normalization'], $context['known_topics']);
        $this->assertTrue($context['has_topics']);
        $this->assertTrue($context['has_materials']);
        $this->assertSame('course_selected_material_metadata_available', $context['context_status']);
        $this->assertSame('Database normalization slides', $context['sample_materials'][0]['title']);
        $this->assertFalse($context['sample_materials'][0]['is_visible']);
    }

    /** @return array{0:Course,1:User} */
    private function courseWithStudent(): array
    {
        [$course, $student] = $this->courseWithStudentAndLecturer();
        return [$course, $student];
    }

    /** @return array{0:Course,1:User,2:User,3:Semester} */
    private function courseWithStudentAndLecturer(): array
    {
        $university = University::query()->create(['name' => 'AcadFlow University', 'short_name' => 'AFU', 'code' => 'AFU', 'domain' => 'afu.test', 'is_active' => true]);
        $faculty = Faculty::query()->create(['university_id' => $university->id, 'name' => 'Science', 'short_name' => 'SCI', 'code' => 'SCI', 'is_active' => true]);
        $department = Department::query()->create(['faculty_id' => $faculty->id, 'name' => 'Computer Science', 'short_name' => 'CSC', 'code' => 'CSC', 'is_active' => true]);
        $session = AcademicSession::query()->create(['university_id' => $university->id, 'name' => '2026/2027', 'start_date' => now(), 'end_date' => now()->addYear(), 'is_current' => true, 'is_active' => true]);
        $semester = Semester::query()->create(['academic_session_id' => $session->id, 'name' => 'First', 'number' => 1, 'start_date' => now(), 'end_date' => now()->addMonths(4), 'is_active' => true]);
        $course = Course::factory()->create(['department_id' => $department->id, 'code' => 'CSC401', 'name' => 'Database Systems']);
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $university->id, 'department_id' => $department->id]);
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer', 'university_id' => $university->id, 'department_id' => $department->id]);
        Enrollment::query()->create(['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        LecturerCourseAssignment::query()->create(['user_id' => $lecturer->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'is_coordinator' => true]);

        return [$course, $student, $lecturer, $semester];
    }
}
