<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\User;
use Carbon\Carbon;

/**
 * Comprehensive validator for submission workflow
 * Checks payment, enrollment, deadlines, file requirements, etc.
 */
class SubmissionValidator
{
    /**
     * Validate if a user can create a submission for a task
     * Returns: ['valid' => true/false, 'errors' => []]
     */
    public function validateCanCreateSubmission(User $student, SubmissionTask $task, ?User $forUser = null): array
    {
        $errors = [];

        // Check if student is enrolled in the course
        if (! $this->isEnrolled($student, $task->course)) {
            $errors[] = 'You are not enrolled in this course.';
        }

        // Check if student has paid for the semester
        if (! $this->hasPaidForSemester($student, $task->semester)) {
            $errors[] = 'You must complete payment for this semester before submitting work.';
        }

        // Check max attempts per assignment
        $maxAttempts = \App\Services\SettingService::get('max_attempts_per_assignment', 3);
        $existingSubmissions = Submission::where('user_id', $student->id)
            ->where('assignment_id', $task->id)
            ->count();
        if ($existingSubmissions >= $maxAttempts) {
            $errors[] = "You have reached the maximum number of submission attempts ({$maxAttempts}).";
        }

        // Check if task is published
        if ($task->status !== 'published') {
            $errors[] = 'This assignment is not yet available for submission.';
        }

        // Check if assignment is visible to students
        if (! $task->is_visible_to_students) {
            $errors[] = 'This assignment is not visible to you.';
        }

        // Check if submission window is open
        if ($task->open_at && now()->isBefore($task->open_at)) {
            $errors[] = "This assignment will open at {$task->open_at->format('M d, Y H:i')}.";
        }

        // Check if submission is closed (and no late allowed)
        if ($task->close_at && now()->isAfter($task->close_at) && ! $task->allow_late_submissions) {
            $errors[] = 'The submission deadline has passed and late submissions are not allowed.';
        }

        // Check if hard deadline has passed
        if ($task->late_deadline && now()->isAfter($task->late_deadline)) {
            $errors[] = 'The absolute submission deadline has passed.';
        }

        // Check group requirements if applicable
        if ($task->allow_group_submissions) {
            // Can be validated when actually creating submission with group
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate file upload against task requirements
     * Returns: ['valid' => true/false, 'errors' => []]
     */
    public function validateFiles(array $files, SubmissionTask $task): array
    {
        $errors = [];

        if (empty($files)) {
            $errors[] = 'At least one file is required.';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file count
        if (count($files) < $task->min_file_count) {
            $errors[] = "Minimum {$task->min_file_count} file(s) required.";
        }

        if (count($files) > $task->max_file_count) {
            $errors[] = "Maximum {$task->max_file_count} files allowed. You uploaded ".count($files).'.';
        }

        // Get allowed extensions from file types
        $allowedExtensions = $this->getExtensionsFromMimeTypes($task->allowed_file_types ?? []);

        $totalSize = 0;
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Check file extension
            if (! in_array($extension, $allowedExtensions)) {
                $errors[] = "File type '.{$extension}' is not allowed. Allowed types: ".implode(', ', $allowedExtensions);
                break;
            }

            // Check file size
            $fileSizeMb = $file->getSize() / (1024 * 1024);
            if ($fileSizeMb > $task->max_file_size_mb) {
                $errors[] = "File '{$file->getClientOriginalName()}' exceeds maximum size of {$task->max_file_size_mb}MB.";
            }

            $totalSize += $file->getSize();
        }

        // Check total size (optional - could add max_total_size_mb field)
        $totalSizeMb = $totalSize / (1024 * 1024);
        $maxTotalMb = ($task->max_file_count * $task->max_file_size_mb);
        if ($totalSizeMb > $maxTotalMb) {
            $errors[] = "Total file size exceeds the limit.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate if a student can resubmit
     * Returns: ['valid' => true/false, 'errors' => []]
     */
    public function validateCanResubmit(Submission $submission): array
    {
        $errors = [];

        if ($submission->status !== 'correction_requested' && $submission->status !== 'under_review') {
            $errors[] = 'This submission cannot be resubmitted in its current status.';
        }

        if ($submission->task) {
            // Check resubmission limit
            if ($submission->task->max_resubmissions && $submission->resubmission_count >= $submission->task->max_resubmissions) {
                $errors[] = "Maximum resubmissions ({$submission->task->max_resubmissions}) reached.";
            }

            // Check if task still accepts submissions
            if (! $submission->task->acceptsSubmissions()) {
                $errors[] = 'This assignment no longer accepts submissions.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Check if user is enrolled in course
     */
    public function isEnrolled(User $user, $course): bool
    {
        return Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'enrolled')
            ->exists();
    }

    /**
     * Check if user has paid for semester
     * Returns true if:
     * - Paid invoice exists, OR
     * - Within grace period, OR
     * - Has scholarship/waiver
     */
    public function hasPaidForSemester(User $user, Semester $semester): bool
    {
        // Super admins and staff bypass payment
        if (in_array($user->role, ['super_admin', 'university_admin', 'department_admin', 'lecturer'])) {
            return true;
        }

        // Check if paid
        $paid = Invoice::where('user_id', $user->id)
            ->where('semester_id', $semester->id)
            ->where('status', 'verified')
            ->exists();

        if ($paid) {
            return true;
        }

        // Check if within grace period (default 7 days from semester start)
        $graceEnd = $semester->start_date->addDays(\App\Services\SettingService::get('grace_period_days', 7));
        if (now()->lessThanOrEqualTo($graceEnd)) {
            return true;
        }

        // Check if waived/exempted (would need a separate table or field)
        // This is a placeholder for future implementation

        return false;
    }

    /**
     * Check if submission is late
     */
    public function isLate(Submission $submission): bool
    {
        $deadline = $submission->getEffectiveDeadline();
        
        if (! $deadline || ! $submission->submitted_at) {
            return false;
        }

        return $submission->submitted_at > $deadline;
    }

    /**
     * Calculate minutes late
     */
    public function minutesLate(Submission $submission): int
    {
        $deadline = $submission->getEffectiveDeadline();
        
        if (! $deadline || ! $submission->submitted_at) {
            return 0;
        }

        if ($submission->submitted_at <= $deadline) {
            return 0;
        }

        return (int) $deadline->diffInMinutes($submission->submitted_at);
    }

    /**
     * Convert MIME types to file extensions
     */
    private function getExtensionsFromMimeTypes(?array $mimeTypes): array
    {
        if (! $mimeTypes) {
            // Default safe file types
            return ['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip'];
        }

        $mimeToExt = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpeg' => 'jpeg',
            'image/gif' => 'gif',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        ];

        $extensions = [];
        foreach ($mimeTypes as $mime) {
            if (isset($mimeToExt[$mime])) {
                $extensions[] = $mimeToExt[$mime];
            } elseif (str_contains($mime, '/')) {
                // Extract extension from mime type like 'image/png' => 'png'
                $extensions[] = strtolower(explode('/', $mime)[1]);
            }
        }

        return array_unique($extensions) ?: ['pdf', 'doc', 'docx'];
    }
}
