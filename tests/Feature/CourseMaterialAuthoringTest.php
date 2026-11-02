<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseMaterialAuthoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_lecturer_can_create_sanitized_authored_material_without_a_file(): void
    {
        $ctx = $this->makeCourseContext();

        $this->actingAs($ctx['lecturer'])
            ->post(route('lecturer.materials.store', $ctx['course']), [
                'mode' => 'author',
                'title' => 'Week 4 — Normalization',
                'type' => 'lecture_note',
                'content' => '<h2>Normalization</h2><p>Reduce duplication.</p><script>alert("xss")</script>',
                'is_visible' => '1',
            ])
            ->assertRedirect();

        $material = CourseMaterial::query()->where('title', 'Week 4 — Normalization')->first();

        $this->assertNotNull($material);
        $this->assertSame('authored', $material->source);
        $this->assertNull($material->file_path);
        $this->assertTrue($material->hasContent());
        $this->assertStringContainsString('Normalization', (string) $material->content);
        $this->assertStringNotContainsString('script', strtolower((string) $material->content));
        $this->assertStringNotContainsString('alert', (string) $material->content);
    }

    public function test_store_requires_either_a_file_or_written_content(): void
    {
        $ctx = $this->makeCourseContext();

        $this->actingAs($ctx['lecturer'])
            ->post(route('lecturer.materials.store', $ctx['course']), [
                'mode' => 'author',
                'title' => 'Empty material',
                'type' => 'lecture_note',
                'content' => '',
            ])
            ->assertSessionHasErrors('content');
    }

    public function test_lecturer_can_update_authored_content_and_it_is_sanitized(): void
    {
        $ctx = $this->makeCourseContext();
        $material = CourseMaterial::query()->create([
            'course_id' => $ctx['course']->id,
            'semester_id' => $ctx['semester']->id,
            'uploaded_by' => $ctx['lecturer']->id,
            'title' => 'Draft notes',
            'type' => 'lecture_note',
            'content' => '<p>Old content</p>',
            'content_format' => 'html',
            'source' => 'authored',
            'requires_enrollment' => true,
            'is_visible' => true,
        ]);

        $this->actingAs($ctx['lecturer'])
            ->put(route('lecturer.materials.update', [$ctx['course'], $material]), [
                'title' => 'Draft notes',
                'type' => 'lecture_note',
                'content' => '<p>New content</p><script>bad()</script>',
                'is_visible' => '1',
            ])
            ->assertRedirect();

        $material->refresh();
        $this->assertStringContainsString('New content', (string) $material->content);
        $this->assertStringNotContainsString('script', strtolower((string) $material->content));
    }

    public function test_show_renders_authored_content_without_scripts(): void
    {
        $ctx = $this->makeCourseContext();

        $this->actingAs($ctx['lecturer'])
            ->post(route('lecturer.materials.store', $ctx['course']), [
                'mode' => 'author',
                'title' => 'Rendered notes',
                'type' => 'lecture_note',
                'content' => '<h2>Visible heading</h2><script>alert(1)</script>',
                'is_visible' => '1',
            ]);

        $material = CourseMaterial::query()->where('title', 'Rendered notes')->firstOrFail();

        $this->actingAs($ctx['lecturer'])
            ->get(route('materials.show', [$ctx['course'], $material]))
            ->assertOk()
            ->assertSee('Visible heading')
            ->assertDontSee('alert(1)');
    }

    public function test_student_cannot_reach_lecturer_material_authoring_route(): void
    {
        $ctx = $this->makeCourseContext();
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $ctx['university']->id,
        ]);

        $this->actingAs($student)
            ->post(route('lecturer.materials.store', $ctx['course']), [
                'mode' => 'author',
                'title' => 'Nope',
                'type' => 'lecture_note',
                'content' => '<p>nope</p>',
            ])
            ->assertForbidden();
    }

    /**
     * @return array<string,\App\Models\Model>
     */
    private function makeCourseContext(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $session = AcademicSession::factory()->create([
            'university_id' => $university->id,
            'is_current' => true,
            'is_active' => true,
        ]);
        $semester = Semester::factory()->create([
            'academic_session_id' => $session->id,
            'is_active' => true,
            'start_date' => now(),
            'end_date' => now()->addMonths(4),
        ]);
        $course = Course::factory()->create(['department_id' => $department->id]);
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);

        LecturerCourseAssignment::query()->create([
            'user_id' => $lecturer->id,
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'is_coordinator' => true,
        ]);

        return compact('university', 'faculty', 'department', 'session', 'semester', 'course', 'lecturer');
    }
}
