<?php

namespace App\Enums;

enum NotificationType: string
{
    // Submission related
    case SUBMISSION_RECEIVED = 'submission_received';
    case SUBMISSION_CONFIRMATION = 'submission_confirmation';
    case COMMENT_ADDED = 'comment_added';
    case CORRECTION_REQUESTED = 'correction_requested';
    case APPROVAL_GRANTED = 'approval_granted';
    case GRADE_POSTED = 'grade_posted';
    case FEEDBACK_POSTED = 'feedback_posted';

    // Billing related
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_OVERDUE = 'payment_overdue';

    // Attendance related
    case ATTENDANCE_STARTED = 'attendance_started';
    case ATTENDANCE_CLOSED = 'attendance_closed';

    // Deadline related
    case DEADLINE_APPROACHING = 'deadline_approaching';
    case OVERDUE_SUBMISSION = 'overdue_submission';

    // Course related
    case COURSE_INVITATION = 'course_invitation';
    case NEW_MATERIAL = 'new_material';
    case ASSIGNMENT_CREATED = 'assignment_created';

    // Discussion related
    case NEW_QUESTION = 'new_question';
    case NEW_REPLY = 'new_reply';

    // System
    case SYSTEM_ANNOUNCEMENT = 'system_announcement';
    case ENROLLMENT_CONFIRMED = 'enrollment_confirmed';

    public function label(): string
    {
        return match ($this) {
            self::SUBMISSION_RECEIVED => 'Submission Received',
            self::SUBMISSION_CONFIRMATION => 'Submission Confirmation',
            self::COMMENT_ADDED => 'Comment Added',
            self::CORRECTION_REQUESTED => 'Correction Requested',
            self::APPROVAL_GRANTED => 'Approval Granted',
            self::GRADE_POSTED => 'Grade Posted',
            self::FEEDBACK_POSTED => 'Feedback Posted',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::PAYMENT_OVERDUE => 'Payment Overdue',
            self::ATTENDANCE_STARTED => 'Attendance Started',
            self::ATTENDANCE_CLOSED => 'Attendance Closed',
            self::DEADLINE_APPROACHING => 'Deadline Approaching',
            self::OVERDUE_SUBMISSION => 'Overdue Submission',
            self::COURSE_INVITATION => 'Course Invitation',
            self::NEW_MATERIAL => 'New Material',
            self::ASSIGNMENT_CREATED => 'Assignment Created',
            self::NEW_QUESTION => 'New Question',
            self::NEW_REPLY => 'New Reply',
            self::SYSTEM_ANNOUNCEMENT => 'System Announcement',
            self::ENROLLMENT_CONFIRMED => 'Enrollment Confirmed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
