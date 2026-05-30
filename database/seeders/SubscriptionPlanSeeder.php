<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing plans (optional)
        // DB::table('subscription_plans')->truncate();

        $plans = [
            [
                'uuid' => 'free-plan-uuid',
                'name' => 'free',
                'display_name' => 'Free Tier',
                'description' => 'Limited access for trial and evaluation purposes.',
                'price_per_month' => 0,
                'price_per_semester' => 0,
                'price_per_year' => 0,
                'billing_cycle' => 'monthly',
                'features' => json_encode(['view_own_submissions', 'view_enrolled_courses', 'receive_notifications']),
                'limits' => json_encode(['max_courses' => 1, 'max_students_per_course' => 10]),
                'max_courses' => 1,
                'max_students_per_course' => 10,
                'max_file_upload_size_mb' => 5,
                'max_storage_gb' => 0.5,
                'allow_group_submissions' => false,
                'allow_rubrics' => false,
                'allow_attendance_tracking' => false,
                'allow_document_generation' => false,
                'allow_api_access' => false,
                'allow_white_label' => false,
                'max_administrators' => 0,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => 'limited-plan-uuid',
                'name' => 'limited',
                'display_name' => 'Limited',
                'description' => 'Basic features for small departments with limited courses.',
                'price_per_month' => 10,
                'price_per_semester' => 50,
                'price_per_year' => 100,
                'billing_cycle' => 'monthly',
                'features' => json_encode(['create_submission', 'view_own_submissions', 'view_course_submissions', 'comment_on_submission', 'resubmit', 'view_own_attendance', 'enroll_in_course', 'create_group', 'join_group']),
                'limits' => json_encode(['max_courses' => 5, 'max_students_per_course' => 30]),
                'max_courses' => 5,
                'max_students_per_course' => 30,
                'max_file_upload_size_mb' => 20,
                'max_storage_gb' => 2,
                'allow_group_submissions' => false,
                'allow_rubrics' => false,
                'allow_attendance_tracking' => false,
                'allow_document_generation' => false,
                'allow_api_access' => false,
                'allow_white_label' => false,
                'max_administrators' => 1,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => 'feature-plan-uuid',
                'name' => 'feature',
                'display_name' => 'Feature-Rich',
                'description' => 'Full feature set for active departments with moderate usage.',
                'price_per_month' => 30,
                'price_per_semester' => 150,
                'price_per_year' => 300,
                'billing_cycle' => 'monthly',
                'features' => json_encode(['create_submission', 'view_own_submissions', 'view_course_submissions', 'comment_on_submission', 'resubmit', 'grade_submission', 'request_correction', 'approve_submission', 'export_grades', 'create_submission_task', 'manage_submission_task', 'view_submission_tasks', 'grant_submission_extension', 'start_attendance', 'stop_attendance', 'view_course_attendance', 'edit_attendance', 'export_attendance', 'manage_templates', 'generate_document', 'view_own_documents', 'download_document', 'print_document', 'view_course_analytics', 'view_own_analytics', 'export_reports']),
                'limits' => json_encode(['max_courses' => 20, 'max_students_per_course' => 100]),
                'max_courses' => 20,
                'max_students_per_course' => 100,
                'max_file_upload_size_mb' => 50,
                'max_storage_gb' => 10,
                'allow_group_submissions' => true,
                'allow_rubrics' => true,
                'allow_attendance_tracking' => true,
                'allow_document_generation' => true,
                'allow_api_access' => false,
                'allow_white_label' => false,
                'max_administrators' => 3,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => 'lecturer-plan-uuid',
                'name' => 'lecturer',
                'display_name' => 'Lecturer Pro',
                'description' => 'Designed for individual lecturers with course management needs.',
                'price_per_month' => 20,
                'price_per_semester' => 100,
                'price_per_year' => 200,
                'billing_cycle' => 'monthly',
                'features' => json_encode(['create_submission', 'view_own_submissions', 'view_course_submissions', 'comment_on_submission', 'grade_submission', 'request_correction', 'approve_submission', 'export_grades', 'create_submission_task', 'manage_submission_task', 'view_submission_tasks', 'grant_submission_extension', 'start_attendance', 'stop_attendance', 'view_course_attendance', 'edit_attendance', 'export_attendance', 'generate_document', 'view_own_documents', 'download_document', 'print_document', 'view_course_analytics', 'view_own_analytics', 'export_reports']),
                'limits' => json_encode(['max_courses' => 10, 'max_students_per_course' => 50]),
                'max_courses' => 10,
                'max_students_per_course' => 50,
                'max_file_upload_size_mb' => 30,
                'max_storage_gb' => 5,
                'allow_group_submissions' => true,
                'allow_rubrics' => true,
                'allow_attendance_tracking' => true,
                'allow_document_generation' => true,
                'allow_api_access' => false,
                'allow_white_label' => false,
                'max_administrators' => 0,
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uuid' => 'department-plan-uuid',
                'name' => 'department',
                'display_name' => 'Department License',
                'description' => 'Full department-level licensing with white-label and API access.',
                'price_per_month' => 100,
                'price_per_semester' => 500,
                'price_per_year' => 1000,
                'billing_cycle' => 'monthly',
                'features' => json_encode(['create_submission', 'view_own_submissions', 'view_course_submissions', 'comment_on_submission', 'grade_submission', 'request_correction', 'approve_submission', 'export_grades', 'create_submission_task', 'manage_submission_task', 'view_submission_tasks', 'grant_submission_extension', 'create_group', 'join_group', 'leave_group', 'manage_group', 'view_group_members', 'start_attendance', 'stop_attendance', 'view_course_attendance', 'edit_attendance', 'export_attendance', 'manage_templates', 'generate_document', 'view_own_documents', 'view_course_documents', 'download_document', 'print_document', 'view_all_analytics', 'view_department_analytics', 'view_course_analytics', 'view_own_analytics', 'export_reports', 'send_course_notification', 'send_system_notification', 'receive_notifications', 'system_config', 'university_settings', 'department_settings', 'manage_email_templates']),
                'limits' => json_encode(['max_courses' => null, 'max_students_per_course' => null]),
                'max_courses' => null, // unlimited
                'max_students_per_course' => null, // unlimited
                'max_file_upload_size_mb' => 100,
                'max_storage_gb' => 50,
                'allow_group_submissions' => true,
                'allow_rubrics' => true,
                'allow_attendance_tracking' => true,
                'allow_document_generation' => true,
                'allow_api_access' => true,
                'allow_white_label' => true,
                'max_administrators' => 10,
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
