<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionGrade;
use App\Models\SubmissionRubric;
use App\Models\SubmissionTask;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Deterministic demo dataset for the marketing screenshot/video pipeline.
 *
 * Run against a dedicated database so it never touches development data:
 *
 *   DB_DATABASE=database/marketing-demo.sqlite \
 *     php artisan migrate:fresh --seed --seeder=MarketingDemoSeeder
 *
 * Demo accounts (password: "password"):
 *   demo.student@acadflow.test  (student)
 *   demo.lecturer@acadflow.test (lecturer)
 *   demo.admin@acadflow.test    (university admin)
 */
class MarketingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $university = University::factory()->create([
            'name' => 'Riverstone University',
            'short_name' => 'RU',
            'code' => 'RSU',
        ]);
        $faculty = Faculty::factory()->create(['university_id' => $university->id, 'name' => 'Computing']);
        $department = Department::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Computer Science']);

        $session = AcademicSession::factory()->create([
            'university_id' => $university->id,
            'name' => '2025/2026',
            'is_current' => true,
            'is_active' => true,
        ]);
        $semester = Semester::factory()->create([
            'academic_session_id' => $session->id,
            'name' => 'First Semester',
            'is_active' => true,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(3),
        ]);

        $courses = collect([
            ['code' => 'CSC 311', 'name' => 'Database Systems'],
            ['code' => 'CSC 305', 'name' => 'Software Engineering'],
            ['code' => 'CSC 401', 'name' => 'Artificial Intelligence'],
        ])->map(fn (array $data) => Course::factory()->create([
            'department_id' => $department->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'access_mode' => 'institutional',
        ]));

        $student = User::factory()->create([
            'first_name' => 'Amara',
            'last_name' => 'Okafor',
            'email' => 'demo.student@acadflow.test',
            'username' => 'amara',
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $university->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme' => 'B.Sc. Computer Science',
            'academic_level' => '300',
            'password' => 'password',
        ]);

        $lecturer = User::factory()->create([
            'first_name' => 'Dr. Ade',
            'last_name' => 'Bello',
            'email' => 'demo.lecturer@acadflow.test',
            'username' => 'drbello',
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $university->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'password' => 'password',
        ]);

        $admin = User::factory()->create([
            'first_name' => 'Ijeoma',
            'last_name' => 'Nwosu',
            'email' => 'demo.admin@acadflow.test',
            'username' => 'ijeoma',
            'role' => 'university_admin',
            'account_type' => 'admin',
            'university_id' => $university->id,
            'password' => 'password',
        ]);

        // Enroll the student and assign the lecturer across every course.
        foreach ($courses as $course) {
            Enrollment::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'semester_id' => $semester->id,
                'status' => 'enrolled',
                'enrolled_at' => now(),
            ]);
            LecturerCourseAssignment::create([
                'course_id' => $course->id,
                'user_id' => $lecturer->id,
                'semester_id' => $semester->id,
                'is_coordinator' => true,
            ]);
        }

        $databaseCourse = $courses[0];

        // A published text assignment (to showcase the rich text submission flow).
        $essay = SubmissionTask::create([
            'uuid' => (string) Str::uuid(),
            'course_id' => $databaseCourse->id,
            'semester_id' => $semester->id,
            'created_by' => $lecturer->id,
            'title' => 'Essay: Database Normalization',
            'description' => 'Explain 1NF through 3NF with a worked example from a real schema.',
            'instructions' => 'Write 800–1200 words. Cite your sources.',
            'type' => 'assignment',
            'open_at' => now()->subWeek(),
            'due_date' => now()->addDays(5),
            'close_at' => now()->addDays(7),
            'late_deadline' => now()->addDays(9),
            'allow_late_submissions' => true,
            'max_resubmissions' => 2,
            'allow_group_submissions' => false,
            'min_group_size' => 1,
            'max_group_size' => 1,
            'allowed_file_types' => ['pdf', 'docx'],
            'max_file_size_mb' => 20,
            'max_file_count' => 5,
            'min_file_count' => 1,
            'max_score' => 100,
            'late_submission_penalty_percent' => 10,
            'submission_format' => 'text',
            'status' => 'published',
            'is_visible_to_students' => true,
        ]);

        // A published file assignment, already graded for one submission.
        $lab = SubmissionTask::create([
            'uuid' => (string) Str::uuid(),
            'course_id' => $databaseCourse->id,
            'semester_id' => $semester->id,
            'created_by' => $lecturer->id,
            'title' => 'Lab Report: Query Optimization',
            'description' => 'Submit your optimised queries and a short analysis of the execution plans.',
            'type' => 'assignment',
            'open_at' => now()->subMonth(),
            'due_date' => now()->subWeek(),
            'close_at' => now()->subDays(5),
            'late_deadline' => now()->subDays(3),
            'allow_late_submissions' => true,
            'max_resubmissions' => 1,
            'allow_group_submissions' => false,
            'min_group_size' => 1,
            'max_group_size' => 1,
            'allowed_file_types' => ['pdf'],
            'max_file_size_mb' => 20,
            'max_file_count' => 3,
            'min_file_count' => 1,
            'max_score' => 100,
            'late_submission_penalty_percent' => 10,
            'submission_format' => 'file',
            'status' => 'published',
            'is_visible_to_students' => true,
        ]);

        // A "both" format capstone proposal to show the dual-layout flow.
        SubmissionTask::create([
            'uuid' => (string) Str::uuid(),
            'course_id' => $courses[2]->id,
            'semester_id' => $semester->id,
            'created_by' => $lecturer->id,
            'title' => 'Capstone Proposal',
            'description' => 'Outline your AI capstone: problem, approach, and evaluation plan.',
            'type' => 'project',
            'open_at' => now()->subDays(3),
            'due_date' => now()->addDays(14),
            'close_at' => now()->addDays(16),
            'late_deadline' => now()->addDays(18),
            'allow_late_submissions' => true,
            'max_resubmissions' => 3,
            'allow_group_submissions' => false,
            'min_group_size' => 1,
            'max_group_size' => 1,
            'allowed_file_types' => ['pdf', 'docx'],
            'max_file_size_mb' => 25,
            'max_file_count' => 5,
            'min_file_count' => 1,
            'max_score' => 100,
            'late_submission_penalty_percent' => 5,
            'submission_format' => 'both',
            'status' => 'published',
            'is_visible_to_students' => true,
        ]);

        // A graded submission to populate the dashboard and the review screen.
        $graded = Submission::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $databaseCourse->id,
            'semester_id' => $semester->id,
            'submission_task_id' => $lab->id,
            'type' => 'assignment',
            'title' => $lab->title,
            'status' => 'graded',
            'submitted_at' => now()->subDays(6),
            'graded_at' => now()->subDays(2),
            'is_late' => false,
        ]);

        $rubric = SubmissionRubric::create([
            'course_id' => $databaseCourse->id,
            'name' => 'Lab report rubric',
            'criteria' => [
                ['name' => 'Correctness', 'points' => 40],
                ['name' => 'Analysis', 'points' => 40],
                ['name' => 'Presentation', 'points' => 20],
            ],
            'total_points' => 100,
            'is_active' => true,
        ]);

        SubmissionGrade::create([
            'submission_id' => $graded->id,
            'user_id' => $lecturer->id,
            'score' => 84,
            'max_score' => 100,
            'rubric_id' => $rubric->id,
            'criteria_scores' => ['Correctness' => 36, 'Analysis' => 32, 'Presentation' => 16],
            'criteria_feedback' => ['Analysis' => 'Good depth; add query timings.', 'Presentation' => 'Clear structure.'],
            'feedback' => 'Strong analysis. Tighten the conclusion and cite the query plans.',
            'is_final' => true,
        ]);

        // Persist the demo entity ids so the screenshot pipeline can resolve
        // dynamic URLs (course, assignment, submission) without guessing.
        $manifest = [
            'universityId' => $university->id,
            'studentId' => $student->id,
            'lecturerId' => $lecturer->id,
            'adminId' => $admin->id,
            'courseIds' => $courses->pluck('id')->all(),
            'databaseCourseId' => $databaseCourse->id,
            'aiCourseId' => $courses[2]->id,
            'taskIds' => [
                'essay' => $essay->id,
                'lab' => $lab->id,
            ],
            'gradedSubmissionId' => $graded->id,
        ];
        file_put_contents(
            storage_path('app/marketing-demo.json'),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->command?->info('Marketing demo data seeded.');
        $this->command?->line('  student: demo.student@acadflow.test / password');
        $this->command?->line('  lecturer: demo.lecturer@acadflow.test / password');
        $this->command?->line('  admin: demo.admin@acadflow.test / password');
    }
}
