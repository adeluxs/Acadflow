<?php

namespace App\Actions\Submission;

use App\Enums\NotificationType;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionGrade;
use App\Models\User;
use App\Services\NotificationService;

class GradeSubmissionAction
{
    public function execute(Submission $submission, User $grader, array $data): SubmissionGrade
    {
        $grade = SubmissionGrade::create([
            'submission_id' => $submission->id,
            'user_id' => $grader->id,
            'score' => $data['score'],
            'max_score' => $data['max_score'] ?? 100,
            'rubric_id' => $data['rubric_id'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'is_final' => true,
        ]);

        $submission->update([
            'status' => SubmissionStatus::GRADED,
            'graded_at' => now(),
        ]);

        $notificationService = app(NotificationService::class);
        $notificationService->send(
            $submission->user,
            NotificationType::SUBMISSION_RECEIVED,
            'Submission Graded',
            "Your submission '{$submission->title}' has been graded.",
            ['title' => $submission->title, 'score' => $data['score']]
        );

        return $grade;
    }
}
