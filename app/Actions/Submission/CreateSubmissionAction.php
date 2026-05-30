<?php

namespace App\Actions\Submission;

use App\Enums\NotificationType;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use App\Services\NotificationService;

class CreateSubmissionAction
{
    public function execute(User $user, array $data): Submission
    {
        $submission = Submission::create([
            'user_id' => $user->id,
            'course_id' => $data['course_id'],
            'semester_id' => $data['semester_id'],
            'group_id' => $data['group_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => SubmissionStatus::DRAFT,
            'version' => 1,
            'due_date' => $data['due_date'] ?? null,
        ]);

        $notificationService = app(NotificationService::class);
        $notificationService->send(
            $user,
            NotificationType::SUBMISSION_RECEIVED,
            'Submission Created',
            "Your submission '{$submission->title}' has been created as a draft.",
            ['title' => $submission->title]
        );

        return $submission;
    }
}
