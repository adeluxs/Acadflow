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
