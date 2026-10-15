<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\AttendanceRecord;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Support\Errors\UserFacingError;

class SyncController extends BaseController
{
    public function process(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'actions' => ['required', 'array'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.payload' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $results = [];

        foreach ($request->input('actions', []) as $action) {
            try {
                $result = $this->handleAction($user, $action);
                $results[] = [
                    'type' => $action['type'],
                    'status' => 'success',
                    'data' => $result,
                ];
            } catch (\Throwable $e) {
                report($e);
                $safe = UserFacingError::fromThrowable($e, $request);
                $results[] = [
                    'type' => $action['type'],
                    'status' => 'error',
                    'code' => $safe->code,
                    'message' => $safe->message,
                    'retryable' => $safe->retryable,
                    'request_id' => $safe->requestId,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    protected function handleAction($user, array $action): mixed
    {
        return match ($action['type']) {
            'attendance.checkin' => $this->syncAttendanceCheckIn($user, $action['payload']),
            'submission.create' => $this->syncSubmissionCreate($user, $action['payload']),
            default => throw new \InvalidArgumentException("Unsupported action type: {$action['type']}"),
        };
    }

    protected function syncAttendanceCheckIn($user, array $payload): AttendanceRecord
    {
        $session = \App\Models\AttendanceSession::findOrFail($payload['session_id']);

        return AttendanceRecord::create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'status' => $payload['status'] ?? 'present',
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'device_fingerprint' => $payload['device_fingerprint'] ?? null,
            'is_verified' => true,
        ]);
    }

    protected function syncSubmissionCreate($user, array $payload): Submission
    {
        $task = \App\Models\SubmissionTask::findOrFail($payload['task_id']);

        return Submission::create([
            'user_id' => $user->id,
            'course_id' => $task->course_id,
            'semester_id' => $task->semester_id,
            'assignment_id' => $task->id,
            'title' => $payload['title'] ?? 'Offline Submission',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }
}
