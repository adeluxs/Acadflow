<?php

namespace App\Enums;

enum Permission: string
{
    // User Management
    case VIEW_ALL_USERS = 'view_all_users';
    case CREATE_USERS = 'create_users';
    case EDIT_USERS = 'edit_users';
    case DELETE_USERS = 'delete_users';
    case MANAGE_ROLES = 'manage_roles';
    case VIEW_OWN_PROFILE = 'view_own_profile';
    case EDIT_OWN_PROFILE = 'edit_own_profile';

    // Department & Faculty
    case CREATE_FACULTY = 'create_faculty';
    case EDIT_FACULTY = 'edit_faculty';
    case DELETE_FACULTY = 'delete_faculty';
    case CREATE_DEPARTMENT = 'create_department';
    case EDIT_DEPARTMENT = 'edit_department';
    case DELETE_DEPARTMENT = 'delete_department';
    case VIEW_ALL_DEPARTMENTS = 'view_all_departments';
    case VIEW_OWN_DEPARTMENT = 'view_own_department';

    // Course Management
    case CREATE_COURSE = 'create_course';
    case EDIT_COURSE = 'edit_course';
    case DELETE_COURSE = 'delete_course';
    case ASSIGN_LECTURER = 'assign_lecturer';
    case VIEW_ALL_COURSES = 'view_all_courses';
    case ENROLL_IN_COURSE = 'enroll_in_course';
    case VIEW_ENROLLED_COURSES = 'view_enrolled_courses';

    // Submissions
    case CREATE_SUBMISSION = 'create_submission';
    case VIEW_OWN_SUBMISSIONS = 'view_own_submissions';
    case VIEW_COURSE_SUBMISSIONS = 'view_course_submissions';
    case COMMENT_ON_SUBMISSION = 'comment_on_submission';
    case GRADE_SUBMISSION = 'grade_submission';
    case REQUEST_CORRECTION = 'request_correction';
    case RESUBMIT = 'resubmit';
    case APPROVE_SUBMISSION = 'approve_submission';
    case EXPORT_GRADES = 'export_grades';
    case CREATE_SUBMISSION_TASK = 'create_submission_task';
    case MANAGE_SUBMISSION_TASK = 'manage_submission_task';
    case VIEW_SUBMISSION_TASKS = 'view_submission_tasks';
    case GRANT_SUBMISSION_EXTENSION = 'grant_submission_extension';

    // Groups
    case CREATE_GROUP = 'create_group';
    case JOIN_GROUP = 'join_group';
    case LEAVE_GROUP = 'leave_group';
    case VIEW_GROUP_MEMBERS = 'view_group_members';
    case MANAGE_GROUP = 'manage_group';

    // Attendance
    case START_ATTENDANCE = 'start_attendance';
    case STOP_ATTENDANCE = 'stop_attendance';
    case CHECK_IN = 'check_in';
    case VIEW_OWN_ATTENDANCE = 'view_own_attendance';
    case VIEW_COURSE_ATTENDANCE = 'view_course_attendance';
    case EDIT_ATTENDANCE = 'edit_attendance';
    case EXPORT_ATTENDANCE = 'export_attendance';

    // Billing
    case SET_PRICING = 'set_pricing';
    case CREATE_INVOICE = 'create_invoice';
    case VIEW_ALL_INVOICES = 'view_all_invoices';
    case VIEW_OWN_INVOICES = 'view_own_invoices';
    case MAKE_PAYMENT = 'make_payment';
    case VERIFY_PAYMENT = 'verify_payment';
    case GENERATE_RECEIPT = 'generate_receipt';
    case WAIVE_PAYMENT = 'waive_payment';

    // Documents
    case MANAGE_TEMPLATES = 'manage_templates';
    case GENERATE_DOCUMENT = 'generate_document';
    case VIEW_OWN_DOCUMENTS = 'view_own_documents';
    case VIEW_COURSE_DOCUMENTS = 'view_course_documents';
    case DOWNLOAD_DOCUMENT = 'download_document';
    case PRINT_DOCUMENT = 'print_document';

    // Analytics
    case VIEW_ALL_ANALYTICS = 'view_all_analytics';
    case VIEW_DEPARTMENT_ANALYTICS = 'view_department_analytics';
    case VIEW_COURSE_ANALYTICS = 'view_course_analytics';
    case VIEW_OWN_ANALYTICS = 'view_own_analytics';
    case EXPORT_REPORTS = 'export_reports';

    // Notifications
    case SEND_SYSTEM_NOTIFICATION = 'send_system_notification';
    case SEND_COURSE_NOTIFICATION = 'send_course_notification';
    case RECEIVE_NOTIFICATIONS = 'receive_notifications';

    // Settings
    case SYSTEM_CONFIG = 'system_config';
    case UNIVERSITY_SETTINGS = 'university_settings';
    case DEPARTMENT_SETTINGS = 'department_settings';
    case MANAGE_EMAIL_TEMPLATES = 'manage_email_templates';

    // AI Academic Assistant
    case MANAGE_AI_SETTINGS = 'manage_ai_settings';
    case VIEW_AI_ANALYTICS = 'view_ai_analytics';

    public static function forRole(string $role): array
    {
        return match ($role) {
            'super_admin' => self::superAdminPermissions(),
            'university_admin' => self::universityAdminPermissions(),
            'department_admin' => self::departmentAdminPermissions(),
            'lecturer' => self::lecturerPermissions(),
            'student' => self::studentPermissions(),
            default => [],
        };
    }

    public static function superAdminPermissions(): array
    {
        return [
            self::VIEW_ALL_USERS,
            self::CREATE_USERS,
            self::EDIT_USERS,
            self::DELETE_USERS,
            self::MANAGE_ROLES,
            self::VIEW_OWN_PROFILE,
            self::EDIT_OWN_PROFILE,
            self::CREATE_FACULTY,
            self::EDIT_FACULTY,
            self::DELETE_FACULTY,
            self::CREATE_DEPARTMENT,
            self::EDIT_DEPARTMENT,
            self::DELETE_DEPARTMENT,
            self::VIEW_ALL_DEPARTMENTS,
            self::VIEW_OWN_DEPARTMENT,
            self::CREATE_COURSE,
            self::EDIT_COURSE,
            self::DELETE_COURSE,
            self::ASSIGN_LECTURER,
            self::VIEW_ALL_COURSES,
            self::VIEW_ENROLLED_COURSES,
            self::VIEW_OWN_SUBMISSIONS,
            self::VIEW_COURSE_SUBMISSIONS,
            self::VIEW_GROUP_MEMBERS,
            self::VIEW_COURSE_ATTENDANCE,
            self::CREATE_INVOICE,
            self::VIEW_ALL_INVOICES,
            self::VERIFY_PAYMENT,
            self::GENERATE_RECEIPT,
            self::WAIVE_PAYMENT,
            self::MANAGE_TEMPLATES,
            self::VIEW_COURSE_DOCUMENTS,
            self::DOWNLOAD_DOCUMENT,
            self::PRINT_DOCUMENT,
            self::VIEW_ALL_ANALYTICS,
            self::VIEW_DEPARTMENT_ANALYTICS,
            self::VIEW_COURSE_ANALYTICS,
            self::VIEW_OWN_ANALYTICS,
            self::EXPORT_REPORTS,
            self::SEND_SYSTEM_NOTIFICATION,
            self::RECEIVE_NOTIFICATIONS,
            self::SYSTEM_CONFIG,
            self::UNIVERSITY_SETTINGS,
            self::MANAGE_EMAIL_TEMPLATES,
            self::MANAGE_AI_SETTINGS,
            self::VIEW_AI_ANALYTICS,
        ];
    }

    public static function universityAdminPermissions(): array
    {
        return [
            self::VIEW_ALL_USERS,
            self::CREATE_USERS,
            self::EDIT_USERS,
            self::VIEW_OWN_PROFILE,
            self::EDIT_OWN_PROFILE,
            self::CREATE_FACULTY,
            self::EDIT_FACULTY,
            self::CREATE_DEPARTMENT,
            self::EDIT_DEPARTMENT,
            self::VIEW_ALL_DEPARTMENTS,
            self::VIEW_OWN_DEPARTMENT,
            self::CREATE_COURSE,
            self::EDIT_COURSE,
            self::DELETE_COURSE,
            self::ASSIGN_LECTURER,
            self::VIEW_ALL_COURSES,
            self::VIEW_ENROLLED_COURSES,
            self::VIEW_OWN_SUBMISSIONS,
            self::VIEW_COURSE_SUBMISSIONS,
            self::VIEW_GROUP_MEMBERS,
            self::VIEW_COURSE_ATTENDANCE,
            self::CREATE_INVOICE,
            self::VIEW_ALL_INVOICES,
            self::VERIFY_PAYMENT,
            self::GENERATE_RECEIPT,
            self::WAIVE_PAYMENT,
            self::MANAGE_TEMPLATES,
            self::VIEW_COURSE_DOCUMENTS,
            self::DOWNLOAD_DOCUMENT,
            self::PRINT_DOCUMENT,
            self::VIEW_ALL_ANALYTICS,
            self::VIEW_DEPARTMENT_ANALYTICS,
            self::VIEW_COURSE_ANALYTICS,
            self::VIEW_OWN_ANALYTICS,
            self::EXPORT_REPORTS,
            self::SEND_SYSTEM_NOTIFICATION,
            self::RECEIVE_NOTIFICATIONS,
            self::UNIVERSITY_SETTINGS,
            self::MANAGE_AI_SETTINGS,
            self::VIEW_AI_ANALYTICS,
        ];
    }

    public static function departmentAdminPermissions(): array
    {
        return [
            self::VIEW_ALL_USERS,
            // Note: CREATE_USERS and EDIT_USERS are conditional - implemented in policy
            self::VIEW_OWN_PROFILE,
            self::EDIT_OWN_PROFILE,
            self::EDIT_DEPARTMENT,
            self::VIEW_ALL_DEPARTMENTS,
            self::VIEW_OWN_DEPARTMENT,
            self::CREATE_COURSE,
            self::EDIT_COURSE,
            self::ASSIGN_LECTURER,
            self::VIEW_ALL_COURSES,
            self::VIEW_ENROLLED_COURSES,
            self::VIEW_OWN_SUBMISSIONS,
            self::VIEW_COURSE_SUBMISSIONS,
            self::VIEW_GROUP_MEMBERS,
            self::VIEW_COURSE_ATTENDANCE,
            self::EDIT_ATTENDANCE,
            self::EXPORT_ATTENDANCE,
            self::CREATE_INVOICE,
            self::VIEW_ALL_INVOICES,
            self::VERIFY_PAYMENT,
            self::GENERATE_RECEIPT,
            self::WAIVE_PAYMENT,
            self::MANAGE_TEMPLATES,
            self::VIEW_COURSE_DOCUMENTS,
            self::DOWNLOAD_DOCUMENT,
            self::PRINT_DOCUMENT,
            self::VIEW_DEPARTMENT_ANALYTICS,
            self::VIEW_COURSE_ANALYTICS,
            self::VIEW_OWN_ANALYTICS,
            self::EXPORT_REPORTS,
            self::SEND_COURSE_NOTIFICATION,
            self::SEND_SYSTEM_NOTIFICATION,
            self::RECEIVE_NOTIFICATIONS,
            self::DEPARTMENT_SETTINGS,
            self::MANAGE_AI_SETTINGS,
            self::VIEW_AI_ANALYTICS,
            self::CREATE_SUBMISSION_TASK,
            self::MANAGE_SUBMISSION_TASK,
            self::VIEW_SUBMISSION_TASKS,
            self::GRANT_SUBMISSION_EXTENSION,
        ];
    }

    public static function lecturerPermissions(): array
    {
        return [
            self::VIEW_OWN_PROFILE,
            self::EDIT_OWN_PROFILE,
            self::VIEW_OWN_DEPARTMENT,
            self::CREATE_COURSE,
            self::EDIT_COURSE,
            self::VIEW_ALL_COURSES,
            self::VIEW_ENROLLED_COURSES,
            self::VIEW_OWN_SUBMISSIONS,
            self::VIEW_COURSE_SUBMISSIONS,
            self::COMMENT_ON_SUBMISSION,
            self::GRADE_SUBMISSION,
            self::REQUEST_CORRECTION,
            self::APPROVE_SUBMISSION,
            self::EXPORT_GRADES,
            self::VIEW_GROUP_MEMBERS,
            self::START_ATTENDANCE,
            self::STOP_ATTENDANCE,
            self::VIEW_COURSE_ATTENDANCE,
            self::EDIT_ATTENDANCE,
            self::EXPORT_ATTENDANCE,
            self::GENERATE_DOCUMENT,
            self::VIEW_COURSE_DOCUMENTS,
            self::DOWNLOAD_DOCUMENT,
            self::PRINT_DOCUMENT,
            self::VIEW_COURSE_ANALYTICS,
            self::VIEW_OWN_ANALYTICS,
            self::EXPORT_REPORTS,
            self::SEND_COURSE_NOTIFICATION,
            self::RECEIVE_NOTIFICATIONS,
            self::CREATE_SUBMISSION_TASK,
            self::MANAGE_SUBMISSION_TASK,
            self::VIEW_SUBMISSION_TASKS,
            self::GRANT_SUBMISSION_EXTENSION,
        ];
    }

    public static function studentPermissions(): array
    {
        return [
            self::VIEW_OWN_PROFILE,
            self::EDIT_OWN_PROFILE,
            self::VIEW_ENROLLED_COURSES,
            self::CREATE_SUBMISSION,
            self::VIEW_OWN_SUBMISSIONS,
            self::COMMENT_ON_SUBMISSION,
            self::RESUBMIT,
            self::CREATE_GROUP,
            self::JOIN_GROUP,
            self::LEAVE_GROUP,
            self::VIEW_GROUP_MEMBERS,
            self::MANAGE_GROUP,
            self::CHECK_IN,
            self::VIEW_OWN_ATTENDANCE,
            self::VIEW_OWN_INVOICES,
            self::MAKE_PAYMENT,
            self::GENERATE_RECEIPT,
            self::GENERATE_DOCUMENT,
            self::VIEW_OWN_DOCUMENTS,
            self::DOWNLOAD_DOCUMENT,
            self::PRINT_DOCUMENT,
            self::VIEW_OWN_ANALYTICS,
            self::EXPORT_REPORTS,
            self::RECEIVE_NOTIFICATIONS,
            self::ENROLL_IN_COURSE,
        ];
    }

    public function belongsTo(string $role): bool
    {
        return in_array($this, self::forRole($role));
    }
}
