<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\AiContentDraft;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Department;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\PracticeQuiz;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LecturerTeachingStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_tools_are_student_only_even_by_direct_url(): void
    {
        $context = $this->context();
        $lecturer = $context['lecturer'];
        $quiz = PracticeQuiz::create([
            'user_id' => $context['student']->id,
            'course_id' => $context['course']->id,
            'title' => 'Student-only practice',
            'status' => PracticeQuiz::STATUS_READY,
            'questions' => [['question' => 'Question?', 'options' => ['A', 'B'], 'answer' => 'A']],
        ]);
        $material = CourseMaterial::create([
            'course_id' => $context['course']->id,
            'semester_id' => $context['semester']->id,
            'uploaded_by' => $lecturer->id,
            'title' => 'Shared lecture source',
            'type' => 'lecture_note',
            'content' => '<p>Course material shared with the class.</p>',
            'is_visible' => true,
            'requires_enrollment' => true,
        ]);

        $this->actingAs($lecturer);
        foreach ([
            route('study.index'), route('student.learning-profile'), route('study-notes.index'),
            route('practice-quizzes.index'), route('practice-quizzes.show', $quiz),
            route('flashcards.index'), route('study-plans.index'), route('mastery.index'),
            route('deadline-coach.index'), route('reflections.index'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post(route('annotations.store', [$context['course'], $material]), ['note' => 'Lecturer note'])
            ->assertForbidden();
        $this->get(route('materials.show', [$context['course'], $material]))
            ->assertOk()
            ->assertDontSee('My highlights &amp; notes', false)
            ->assertDontSee('Study this material');

        $this->actingAs($context['student'])->get(route('materials.show', [$context['course'], $material]))
            ->assertOk()
            ->assertSee('My highlights &amp; notes', false)
            ->assertSee('Study this material');
    }

    public function test_teaching_studio_is_lecturer_only_and_scoped_to_assigned_courses(): void
    {
        $context = $this->context();
        $foreignLecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $foreignCourse = Course::factory()->create(['department_id' => $context['department']->id, 'access_mode' => 'independent', 'owner_user_id' => $foreignLecturer->id, 'code' => 'PRIVATE999', 'name' => 'Another lecturer course']);
        LecturerCourseAssignment::create(['user_id' => $foreignLecturer->id, 'course_id' => $foreignCourse->id, 'semester_id' => $context['semester']->id]);

        Submission::factory()->create([
            'user_id' => $context['student']->id, 'course_id' => $context['course']->id,
            'semester_id' => $context['semester']->id, 'status' => 'submitted', 'title' => 'Waiting laboratory report', 'submitted_at' => now()->subHour(),
        ]);
        Discussion::create([
            'course_id' => $context['course']->id, 'semester_id' => $context['semester']->id,
            'user_id' => $context['student']->id, 'title' => 'Please explain normalization', 'content' => 'I need another example.', 'status' => 'open',
        ]);
        AiContentDraft::create([
            'user_id' => $context['lecturer']->id, 'course_id' => $context['course']->id,
            'feature' => 'lecturer_assistant', 'action' => 'lesson_plan', 'instruction' => 'Plan week three',
            'content' => 'Ready lesson plan', 'status' => AiContentDraft::STATUS_READY,
        ]);

        $this->actingAs($context['lecturer'])->get(route('teaching.index'))
            ->assertOk()->assertViewIs('lecturer.teaching-studio')
            ->assertSee('Teaching Studio')->assertSee('Waiting laboratory report')
            ->assertSee('Please explain normalization')->assertSee($context['course']->code)
            ->assertDontSee('PRIVATE999')
            ->assertViewHas('overview', fn (array $overview) => $overview['assignedCourses'] === 1 && $overview['pendingReviews'] === 1
                && $overview['openQuestions'] === 1 && $overview['readyDrafts'] === 1);

        $this->actingAs($context['student'])->get(route('teaching.index'))->assertForbidden();
    }

    public function test_lecturer_dashboard_links_to_teaching_studio_and_keeps_lists_bounded(): void
    {
        $context = $this->context();
        for ($i = 0; $i < 15; $i++) {
            $course = Course::factory()->create(['department_id' => $context['department']->id, 'access_mode' => 'independent', 'owner_user_id' => $context['lecturer']->id, 'code' => sprintf('TEACH%02d', $i)]);
            LecturerCourseAssignment::create(['user_id' => $context['lecturer']->id, 'course_id' => $course->id, 'semester_id' => $context['semester']->id]);
        }

        $this->actingAs($context['lecturer'])->get(route('dashboard'))
            ->assertOk()->assertSee('Teaching today')->assertSee('Open Teaching Studio')
            ->assertViewHas('courses', fn ($courses) => $courses->count() === 6)
            ->assertViewHas('teachingSignals', fn (array $signals) => array_key_exists('openQuestions', $signals));

        $this->get(route('teaching.index'))->assertOk()
            ->assertViewHas('courses', fn ($courses) => $courses->count() === 12);
    }

    public function test_teaching_studio_query_count_does_not_grow_with_course_catalogue(): void
    {
        $context = $this->context();
        $this->actingAs($context['lecturer'])->get(route('teaching.index'))->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('teaching.index'))->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 0; $i < 25; $i++) {
            $course = Course::factory()->create(['department_id' => $context['department']->id, 'access_mode' => 'independent', 'owner_user_id' => $context['lecturer']->id, 'code' => sprintf('BOUND%02d', $i)]);
            LecturerCourseAssignment::create(['user_id' => $context['lecturer']->id, 'course_id' => $course->id, 'semester_id' => $context['semester']->id]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get(route('teaching.index'))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($smallCount + 1, count($queries));
        $this->assertFalse(collect($queries)->contains(fn (array $query) => str_starts_with($query['query'], 'select * from "course_materials"')));
        $response->assertViewHas('courses', fn ($courses) => $courses->count() === 12);
    }

    /** @return array<string,mixed> */
    private function context(): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $academicSession = AcademicSession::factory()->create(['university_id' => $university->id, 'is_current' => true, 'is_active' => true]);
        $semester = Semester::factory()->create(['academic_session_id' => $academicSession->id, 'is_active' => true]);
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer', 'university_id' => $university->id, 'department_id' => $department->id]);
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => $university->id, 'department_id' => $department->id]);
        $course = Course::factory()->create(['department_id' => $department->id, 'code' => 'CSC301', 'name' => 'Database Systems']);
        LecturerCourseAssignment::create(['user_id' => $lecturer->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'is_coordinator' => true]);
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()]);

        return compact('lecturer', 'student', 'course', 'semester', 'department');
    }
}
