<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UniversitySeeder extends Seeder
{
    public function run(): void
    {
        // Production deployments should never receive predictable demo accounts by accident.
        // Opt in explicitly with ACADEMIC_SEED_DEMO=true when a production-like demo is intended.
        if (app()->environment('production') && ! filter_var(env('ACADEMIC_SEED_DEMO', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->command?->warn('Skipping demo university/users in production. Set ACADEMIC_SEED_DEMO=true to opt in.');
            return;
        }

        $password = Hash::make((string) env('ACADEMIC_DEMO_PASSWORD', 'password123'));

        User::firstOrCreate(
            ['email' => 'admin@uniacademic.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'password' => $password,
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );

        // Keep one deterministic demo tenant for local development. The national
        // catalogue seeder later merges this exact institution with the NUC row.
        $university = University::firstOrCreate(
            ['code' => 'FUTO'],
            [
                'name' => 'Federal University of Technology, Owerri',
                'short_name' => 'FUTO',
                'institution_type' => 'university',
                'ownership' => 'Federal',
                'state' => 'Imo',
                'regulator' => 'NUC',
                'email' => 'info@futo.edu.ng',
                'phone' => '+2348000000000',
                'address' => 'Owerri, Imo State, Nigeria',
                'website' => 'https://futo.edu.ng',
                'timezone' => 'Africa/Lagos',
                'is_active' => true,
            ]
        );

        $faculty = Faculty::firstOrCreate(
            ['university_id' => $university->id, 'code' => 'SCI'],
            [
                'name' => 'Faculty of Science',
                'short_name' => 'Science',
                'is_active' => true,
                'catalog_source' => 'demo_verified',
                'is_catalog_template' => false,
            ]
        );

        $department = Department::firstOrCreate(
            ['faculty_id' => $faculty->id, 'code' => 'CSC'],
            [
                'name' => 'Computer Science',
                'short_name' => 'CSC',
                'is_active' => true,
                'catalog_source' => 'demo_verified',
                'is_catalog_template' => false,
            ]
        );

        $session = AcademicSession::firstOrCreate(
            ['university_id' => $university->id, 'name' => '2025/2026'],
            [
                'start_date' => '2025-09-01',
                'end_date' => '2026-08-31',
                'is_current' => true,
                'is_active' => true,
            ]
        );

        $semester = Semester::firstOrCreate(
            ['academic_session_id' => $session->id, 'number' => 1],
            [
                'name' => 'First Semester',
                'start_date' => '2025-09-01',
                'end_date' => '2026-02-28',
                'is_active' => true,
            ]
        );

        $courseRows = [
            ['code' => 'CSC101', 'name' => 'Introduction to Computer Science', 'level' => '100'],
            ['code' => 'CSC102', 'name' => 'Computer Programming I', 'level' => '100'],
            ['code' => 'CSC201', 'name' => 'Data Structures and Algorithms', 'level' => '200'],
            ['code' => 'CSC202', 'name' => 'Database Systems', 'level' => '200'],
            ['code' => 'CSC301', 'name' => 'Software Engineering', 'level' => '300'],
            ['code' => 'CSC401', 'name' => 'Final Year Project I', 'level' => '400'],
        ];

        $courses = collect($courseRows)->map(function (array $row) use ($department) {
            return Course::firstOrCreate(
                ['department_id' => $department->id, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'level' => $row['level'],
                    'semester' => '1st',
                    'type' => 'compulsory',
                    'credit_hours' => 3,
                    'pass_mark' => 40,
                    'is_active' => true,
                    'catalog_source' => 'demo_verified',
                    'is_catalog_template' => false,
                ]
            );
        });

        User::firstOrCreate(
            ['email' => 'universityadmin@futo.edu.ng'],
            [
                'university_id' => $university->id,
                'first_name' => 'University',
                'last_name' => 'Admin',
                'password' => $password,
                'role' => 'university_admin',
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'deptadmin@futo.edu.ng'],
            [
                'university_id' => $university->id,
                'department_id' => $department->id,
                'first_name' => 'Department',
                'last_name' => 'Admin',
                'password' => $password,
                'role' => 'department_admin',
                'is_active' => true,
            ]
        );

        $lecturers = collect([
            'dr.ibrahim@futo.edu.ng' => ['Dr.', 'Ibrahim'],
            'prof.adeyemi@futo.edu.ng' => ['Prof.', 'Adeyemi'],
        ])->map(function (array $name, string $email) use ($university, $department, $password) {
            return User::firstOrCreate(
                ['email' => $email],
                [
                    'university_id' => $university->id,
                    'department_id' => $department->id,
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'password' => $password,
                    'role' => 'lecturer',
                    'is_active' => true,
                ]
            );
        });

        // The lecturer collection is keyed by email because it is mapped from an
        // associative array. Reindex both collections before using numeric offsets
        // so PHP never attempts arithmetic on an email string and the first course
        // in each lecturer's slice is correctly marked as coordinator.
        foreach ($lecturers->values() as $lecturerIndex => $lecturer) {
            foreach ($courses->slice($lecturerIndex * 2, 3)->values() as $courseIndex => $course) {
                LecturerCourseAssignment::firstOrCreate(
                    ['course_id' => $course->id, 'user_id' => $lecturer->id, 'semester_id' => $semester->id],
                    ['is_coordinator' => $courseIndex === 0]
                );
            }
        }

        foreach ([
            'student001@student.futo.edu.ng' => ['Daniel', 'Adekunle', 'FUTO/CSC/001'],
            'student002@student.futo.edu.ng' => ['Amina', 'Yusuf', 'FUTO/CSC/002'],
            'student003@student.futo.edu.ng' => ['Chinedu', 'Okafor', 'FUTO/CSC/003'],
        ] as $email => $name) {
            $student = User::firstOrCreate(
                ['email' => $email],
                [
                    'university_id' => $university->id,
                    'department_id' => $department->id,
                    'student_id' => $name[2],
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'password' => $password,
                    'role' => 'student',
                    'is_active' => true,
                ]
            );

            foreach ($courses->take(4) as $course) {
                Enrollment::firstOrCreate(
                    ['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id],
                    ['status' => 'enrolled', 'enrolled_at' => now()]
                );
            }
        }

        $this->command?->info('AcadFlow demo tenant and role accounts ensured without overwriting existing rows.');
    }
}
