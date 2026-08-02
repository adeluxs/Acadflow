<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case CORRECTION_REQUESTED = 'correction_requested';
    case RESUBMITTED = 'resubmitted';
    case APPROVED = 'approved';
    case GRADED = 'graded';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::CORRECTION_REQUESTED => 'Correction Requested',
            self::RESUBMITTED => 'Resubmitted',
            self::APPROVED => 'Approved',
            self::GRADED => 'Graded',
            self::ARCHIVED => 'Archived',
        };
    }
}

enum SubmissionType: string
{
    case ASSIGNMENT = 'assignment';
    case PROJECT = 'project';
    case SIWES = 'siwes';
    case GROUP = 'group';
    case SEMINAR = 'seminar';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNMENT => 'Assignment',
            self::PROJECT => 'Project',
            self::SIWES => 'SIWES Report',
            self::GROUP => 'Group Report',
            self::SEMINAR => 'Seminar Paper',
        };
    }
}

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case LATE = 'late';
    case ABSENT = 'absent';
    case INVALID = 'invalid';
    case PENDING = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Present',
            self::LATE => 'Late',
            self::ABSENT => 'Absent',
            self::INVALID => 'Invalid',
            self::PENDING => 'Pending',
        };
    }
}

enum AttendanceSessionStatus: string
{
    case ACTIVE = 'active';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }
}