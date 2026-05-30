<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Semester;
use App\Models\University;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UniversitySeeder extends Seeder
{
    public function run(): void
    {
        $uuid1 = Str::uuid()->toString();
        $uuid2 = Str::uuid()->toString();
        $uuid3 = Str::uuid()->toString();

        // Super Admin
        User::updateOrCreate(
            ['email' => 'admin@uniacademic.com'],
            [
                'uuid' => $uuid1,
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'password' => Hash::make('password123'),
                'role' => 'super_admin',
                'is_active' => 1,
            ]
        );
        $this->command->info('Super Admin: admin@uniacademic.com / password123');

        // University
        $university = University::updateOrCreate(
            ['code' => 'FUT001'],
            [
                'uuid' => $uuid2,
                'name' => 'Federal University of Technology',
                'short_name' => 'FUT',
                'code' => 'FUT001',
                'email' => 'info@fut.edu.ng',
                'phone' => '+2348000000000',
                'address' => 'Owerri, Imo State, Nigeria',
                'website' => 'https://fut.edu.ng',
                'timezone' => 'Africa/Lagos',
                'is_active' => 1,
            ]
        );

        // Faculty
        $faculty = Faculty::updateOrCreate(
            ['code' => 'ENG'],
            [
                'uuid' => Str::uuid()->toString(),
                'university_id' => $university->id,
                'name' => 'Faculty of Engineering',
                'short_name' => 'ENG',
                'code' => 'ENG',
                'is_active' => 1,
            ]
        );

        // Department
        $department = Department::updateOrCreate(
            ['code' => 'CS'],
            [
                'uuid' => Str::uuid()->toString(),
                'faculty_id' => $faculty->id,
                'name' => 'Department of Computer Science',
                'short_name' => 'CS',
                'code' => 'CS',
                'is_active' => 1,
            ]
        );
        $this->command->info("Created: $university->name, $faculty->name, $department->name");

        // Session & Semester
        $session = AcademicSession::updateOrCreate(
            ['university_id' => $university->id, 'name' => '2025/2026'],
            [
                'uuid' => Str::uuid()->toString(),
                'university_id' => $university->id,
                'name' => '2025/2026',
                'start_date' => '2025-01-15',
                'end_date' => '2025-12-31',
                'is_current' => 1,
                'is_active' => 1,
            ]
        );

        $semester = Semester::updateOrCreate(
            ['academic_session_id' => $session->id, 'number' => 1],
            [
                'uuid' => Str::uuid()->toString(),
                'academic_session_id' => $session->id,
                'name' => 'First Semester',
                'number' => 1,
                'start_date' => '2025-01-15',
                'end_date' => '2025-06-30',
                'is_active' => 1,
            ]
        );
        $this->command->info("Created session: $session->name");

        // Courses
        $courses = [
            ['code' => 'CS101', 'name' => 'Introduction to Computer Science', 'level' => '100'],
            ['code' => 'CS102', 'name' => 'Computer Programming I', 'level' => '100'],
            ['code' => 'CS201', 'name' => 'Data Structures', 'level' => '200'],
            ['code' => 'CS202', 'name' => 'Database Management', 'level' => '200'],
            ['code' => 'CS301', 'name' => 'Software Engineering', 'level' => '300'],
            ['code' => 'CS401', 'name' => 'Final Year Project I', 'level' => '400'],
        ];

        $password = Hash::make('password123');

        foreach ($courses as $courseData) {
            Course::updateOrCreate(
                ['code' => $courseData['code']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'department_id' => $department->id,
                    'code' => $courseData['code'],
                    'name' => $courseData['name'],
                    'level' => $courseData['level'],
                    'semester' => '1st',
                    'type' => 'compulsory',
                    'credit_hours' => 3,
                    'pass_mark' => 40,
                    'is_active' => 1,
                ]
            );
        }
        $this->command->info('Created '.count($courses).' courses');

        // University Admin
        User::updateOrCreate(
            ['email' => 'universityadmin@fut.edu.ng'],
            [
                'uuid' => Str::uuid()->toString(),
                'university_id' => $university->id,
                'first_name' => 'University',
                'last_name' => 'Admin',
                'password' => $password,
                'role' => 'university_admin',
                'is_active' => 1,
            ]
        );

        // Department Admin
        User::updateOrCreate(
            ['email' => 'deptadmin@fut.edu.ng'],
            [
                'uuid' => Str::uuid()->toString(),
                'university_id' => $university->id,
                'department_id' => $department->id,
                'first_name' => 'Department',
                'last_name' => 'Admin',
                'password' => $password,
                'role' => 'department_admin',
                'is_active' => 1,
            ]
        );
        $this->command->info('Admins created');

        // Lecturers
        $lecturers = [
            'dr.adeyemi@fut.edu.ng' => ['Dr', 'Adeyemi'],
            'prof.ibrahim@fut.edu.ng' => ['Prof', 'Ibrahim'],
        ];
        foreach ($lecturers as $email => $name) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'uuid' => Str::uuid()->toString(),
                    'university_id' => $university->id,
                    'department_id' => $department->id,
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'password' => $password,
                    'role' => 'lecturer',
                    'is_active' => 1,
                ]
            );
        }
        $this->command->info('Created '.count($lecturers).' Lecturers');

        // Students
        $students = [
            'student001@student.fut.edu.ng' => ['John', 'Doe'],
            'student002@student.fut.edu.ng' => ['Jane', 'Smith'],
            'student003@student.fut.edu.ng' => ['Michael', 'Brown'],
        ];

        $courses = Course::where('department_id', $department->id)->get();

        foreach ($students as $email => $name) {
            $student = User::updateOrCreate(
                ['email' => $email],
                [
                    'uuid' => Str::uuid()->toString(),
                    'university_id' => $university->id,
                    'department_id' => $department->id,
                    'first_name' => $name[0],
                    'last_name' => $name[1],
                    'password' => $password,
                    'role' => 'student',
                    'is_active' => 1,
                ]
            );

            if ($semester && $courses->isNotEmpty()) {
                foreach ($courses->take(3) as $course) {
                    Enrollment::firstOrCreate(
                        ['user_id' => $student->id, 'course_id' => $course->id],
                        [
                            'semester_id' => $semester->id,
                            'status' => 'enrolled',
                            'enrolled_at' => now(),
                        ]
                    );
                }
            }
        }
        $this->command->info('Created '.count($students).' Students with enrollments');
        $this->command->info('=== SEEDING COMPLETE ===');
    }
}
