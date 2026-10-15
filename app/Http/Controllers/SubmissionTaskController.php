<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\AssignmentCreated;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\SubmissionExtension;
use App\Models\SubmissionTask;
use App\Models\SubmissionTaskAttachment;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\Assignments\AssignmentTypeProfile;
use App\Services\Commerce\FeatureBillingService;
use App\Support\Errors\UserFacingError;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubmissionTaskController extends Controller
{
    public function __construct(private AcademicContextService $academicContext)
    {
    }

    /**
     * Show all submission tasks for a course (lecturer view)
     */
    public function indexForCourse(Course $course)
    {
        // Check if user is authorized to view tasks for this course
        $this->authorize('createForCourse', $course);

        $tasks = $course->submissionTasks()
            ->withCount('submissions')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $tasksGrouped = collect($tasks->items())->groupBy('status');

        return view('submission-tasks.index', compact('course', 'tasks', 'tasksGrouped'));
    }

    /**
     * Show all tasks visible to a student for a course
     */
    public function availableForStudent(Request $request, Course $course)
    {
        $this->authorize('view', $course);

        $status = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'submitted', 'graded'])],
        ])['status'] ?? null;
        $userId = (int) Auth::id();
        $now = now();

        $query = $course->submissionTasks()
            ->where('status', 'published')
            ->where('is_visible_to_students', true)
            // Only the authenticated student's submission state belongs in a
            // student assignment listing. Do not eager-load classmates' work.
            ->with(['submissions' => fn ($q) => $q->where('user_id', $userId)->with('grade')]);

        if ($status === 'open') {
            $query->where(fn ($q) => $q->whereNull('open_at')->orWhere('open_at', '<=', $now))
                ->where(function ($q) use ($now) {
                    $q->where(function ($inner) use ($now) {
                        $inner->where('allow_late_submissions', true)
                            ->where(fn ($late) => $late->whereNull('late_deadline')->orWhere('late_deadline', '>=', $now));
                    })->orWhere(function ($inner) use ($now) {
                        $inner->where('allow_late_submissions', false)
                            ->where(fn ($due) => $due->whereNull('due_date')->orWhere('due_date', '>=', $now));
                    });
                });
        } elseif ($status === 'submitted') {
            $query->whereHas('submissions', fn ($q) => $q->where('user_id', $userId));
        } elseif ($status === 'graded') {
            $query->whereHas('submissions', fn ($q) => $q->where('user_id', $userId)->whereHas('grade'));
        }

        $tasks = $query->orderByRaw('due_date IS NULL, due_date ASC')->get();

        return view('submission-tasks.available', compact('course', 'tasks'));
    }

    /**
     * Create a new submission task
     */
    public function create(Course $course)
    {
        $this->authorize('createForCourse', $course);

        $activeSemester = $this->academicContext->activeSemesterForCourse($course);
        $semesters = $activeSemester ? collect([$activeSemester]) : collect();
        $rubrics = $course->submissionRubrics()->where('is_active', true)->get();
        $typeProfiles = AssignmentTypeProfile::availableDefinitions();

        return view('submission-tasks.create', compact('course', 'semesters', 'rubrics', 'typeProfiles'));
    }

    /**
     * Store a new submission task
     */
    public function store(Request $request, Course $course)
    {
        $user = Auth::user();

        $this->authorize('createForCourse', $course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'instructions' => 'nullable|string|max:5000',
            'type' => ['required', Rule::in(AssignmentTypeProfile::enabledTypes())],
            'open_at' => 'required|date_format:Y-m-d\TH:i',
            'close_at' => 'nullable|date_format:Y-m-d\TH:i',
            'due_date' => 'required|date_format:Y-m-d\TH:i',
            'late_deadline' => 'required|date_format:Y-m-d\TH:i',
            'allow_late_submissions' => 'boolean',
            'max_resubmissions' => 'nullable|integer|min:0',
            'allow_group_submissions' => 'boolean',
            'min_group_size' => 'required_if:allow_group_submissions,true|integer|min:1|max:10',
            'max_group_size' => 'required_if:allow_group_submissions,true|integer|min:1|max:10',
            'allowed_file_types' => 'nullable|string',
            'max_file_size_mb' => 'required|integer|min:1|max:'.max(1, intdiv(\App\Services\SettingService::getMaxUploadSize($request->user()?->university_id), 1024 * 1024)),
            'max_file_count' => 'required|integer|min:1|max:20',
            'min_file_count' => 'required|integer|min:1',
            'rubric_id' => 'nullable|exists:submission_rubrics,id',
            'max_score' => 'required|numeric|min:1|max:1000',
            'require_approval_before_grading' => 'boolean',
            'late_submission_penalty_percent' => 'nullable|numeric|min:0|max:100',
            'submission_format' => 'required|in:file,text,both',
            'status' => 'in:draft,published',
            'semester_id' => 'required|exists:semesters,id',
            'is_visible_to_students' => 'boolean',
        ]);

        // The browser only receives the current course semester/rubrics, but never
        // trust IDs posted by a client. Keep every assignment inside the same
        // institution/course context.
        $activeSemester = $this->academicContext->requireActiveSemesterForCourse($course);
        if ((int) $validated['semester_id'] !== (int) $activeSemester->id) {
            throw ValidationException::withMessages(['semester_id' => 'Assignments can only be created for the active semester of this course.']);
        }
        if (! empty($validated['rubric_id']) && ! $course->submissionRubrics()->whereKey($validated['rubric_id'])->exists()) {
            throw ValidationException::withMessages(['rubric_id' => 'The selected rubric does not belong to this course.']);
        }

        $this->assertTaskScheduleIsConsistent($validated);

        $validated['type_metadata'] = $this->validateTypeMetadata($request, $validated['type']);

        // Apply centralized institution-aware academic settings as defaults.
        $validated['allow_late_submissions'] = $validated['allow_late_submissions'] ?? \App\Services\SettingService::get('allow_late_submissions', true, $user->university_id);

        if (!empty($validated['allowed_file_types'])) {
            $validated['allowed_file_types'] = array_map('trim', explode(',', $validated['allowed_file_types']));
        } else {
            $validated['allowed_file_types'] = [];
        }

        $billing = app(FeatureBillingService::class);
        $billingReservations = [];

        try {
            // Ensure min_group_size <= max_group_size
            if ($validated['allow_group_submissions'] ?? false) {
                if ($validated['min_group_size'] > $validated['max_group_size']) {
                    return back()->withInput()->withErrors(['min_group_size' => 'Minimum must be less than or equal to maximum group size.']);
                }
            }

            // Ensure min_file_count <= max_file_count
            if ($validated['min_file_count'] > $validated['max_file_count']) {
                return back()->withInput()->withErrors(['min_file_count' => 'Minimum files must be less than or equal to maximum.']);
            }

            $baseBillingId = 'assignment:'.UserFacingError::requestId($request);
            if ($validated['allow_group_submissions'] ?? false) {
                $billingReservations['allow_group_submissions'] = $billing->begin($user, 'allow_group_submissions', $baseBillingId.':group');
            }
            if (! empty($validated['rubric_id'])) {
                $billingReservations['allow_rubrics'] = $billing->begin($user, 'allow_rubrics', $baseBillingId.':rubric');
            }

            $task = SubmissionTask::create([
                ...$validated,
                'course_id' => $course->id,
                'created_by' => Auth::id(),
            ]);

            Log::info('Submission task created successfully', [
                'task_id' => $task->id,
                'task_uuid' => $task->uuid,
            ]);

            foreach ($billingReservations as $feature => $reservation) {
                $billing->complete($user, $reservation, ['submission_task_id' => $task->id, 'course_id' => $course->id]);
            }

            // Fire event to notify enrolled students (only if published)
            if ($task->status === 'published') {
                $enrolledStudents = User::whereHas('enrollments', function ($q) use ($course, $validated) {
                    $q->where('course_id', $course->id)
                        ->where('semester_id', $validated['semester_id'])
                        ->where('status', 'enrolled');
                })->get();

                if ($enrolledStudents->count() > 0) {
                    event(new AssignmentCreated($task, $course, $enrolledStudents));
                }
            }

            return redirect()->route('submission-tasks.lecturer.show', [$course, $task])
                ->with('success', 'Assignment created successfully.');
        } catch (ValidationException $e) {
            foreach ($billingReservations as $reservation) {
                $billing->release($user, $reservation, ['reason' => 'assignment_validation_failed']);
            }
            return back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            foreach ($billingReservations as $reservation) {
                $billing->release($user, $reservation, ['reason' => 'assignment_creation_failed']);
            }
            Log::error('Failed to create submission task', [
                'course_id' => $course->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors(['error' => 'The assignment could not be created right now. Your entered information has been preserved. Please try again.']);
        }
    }

    /**
 * Shared check: ensure task belongs to the course
 */
private function ensureTaskBelongsToCourse(Course $course, SubmissionTask $task): void
{
    if ($task->course_id !== $course->id) {
        abort(404);
    }
}

/**
 * Enforce date ordering and size relationships that the individual field
 * rules cannot express, so an inconsistent assignment can never be saved.
 *
 * @param  array<string,mixed>  $data
 */
private function assertTaskScheduleIsConsistent(array $data): void
{
    $parse = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value) : null;

    $openAt = $parse($data['open_at'] ?? null);
    $dueAt = $parse($data['due_date'] ?? null);
    $closeAt = $parse($data['close_at'] ?? null);
    $lateAt = $parse($data['late_deadline'] ?? null);

    $errors = [];
    if ($openAt && $dueAt && $openAt->greaterThan($dueAt)) {
        $errors['due_date'] = 'The due date must be on or after the open date.';
    }
    if ($dueAt && $closeAt && $dueAt->greaterThan($closeAt)) {
        $errors['close_at'] = 'The close date must be on or after the due date.';
    }
    if ($closeAt && $lateAt && $closeAt->greaterThan($lateAt)) {
        $errors['late_deadline'] = 'The late deadline must be on or after the close date.';
    }

    if (isset($data['min_file_count'], $data['max_file_count']) && (int) $data['min_file_count'] > (int) $data['max_file_count']) {
        $errors['min_file_count'] = 'Minimum files must be less than or equal to the maximum.';
    }

    if (($data['allow_group_submissions'] ?? false) && isset($data['min_group_size'], $data['max_group_size'])
        && (int) $data['min_group_size'] > (int) $data['max_group_size']) {
        $errors['min_group_size'] = 'Minimum group size must be less than or equal to the maximum.';
    }

    if ($errors !== []) {
        throw ValidationException::withMessages($errors);
    }
}

/**
 * Validate and normalize the assignment-type-specific metadata. Each type
 * (project, siwes, seminar, group) requires its own fields, so an
 * assignment cannot be created through an overly generic path.
 *
 * @return array<string,mixed>
 */
private function validateTypeMetadata(Request $request, string $type): array
{
    $rules = AssignmentTypeProfile::rules($type);

    $validated = $rules === [] ? [] : $request->validate($rules);

    return AssignmentTypeProfile::normalize($type, $validated['type_metadata'] ?? []);
}

/**
 * Student view for a specific submission task
 */
public function showForStudent(Course $course, SubmissionTask $task)
{

    $this->ensureTaskBelongsToCourse($course, $task);

    $this->authorize('view', $task);

    $task->load(['requirements', 'attachments', 'rubric']);

    $studentSubmissions = $task->submissions()
        ->where('user_id', Auth::id())
        ->with(['versions', 'comments.user', 'grade'])
        ->latest()
        ->get();

    return view('submission-tasks.student-show', compact(
        'course',
        'task',
        'studentSubmissions'
    ));
}

/**
 * Lecturer view for a specific submission task
 */
public function showForLecturer(Course $course, SubmissionTask $task)
{
    $this->ensureTaskBelongsToCourse($course, $task);

    $this->authorize('view', $task);

    $task->load(['requirements', 'attachments', 'rubric']);

    $submissions = $task->submissions()
        ->with(['user', 'grade'])
        ->latest()
        ->paginate(20);

    $stats = $this->getTaskStats($task);

    $nonSubmitters = User::query()
        ->select(['id', 'student_id', 'first_name', 'last_name', 'email'])
        ->whereHas('enrollments', function ($query) use ($course, $task): void {
            $query->where('course_id', $course->id)
                ->where('semester_id', $task->semester_id)
                ->where('status', 'enrolled');
        })
        ->whereDoesntHave('submissions', fn ($query) => $query->where('submission_task_id', $task->id))
        ->orderBy('first_name')
        ->orderBy('last_name')
        ->paginate(20, ['*'], 'missing_page')
        ->withQueryString();

    return view('submission-tasks.show', compact(
        'course',
        'task',
        'submissions',
        'stats',
        'nonSubmitters'
    ));
}
    /**
     * Edit a submission task
     */
    public function edit(Course $course, SubmissionTask $task)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $task);
        $activeSemester = $this->academicContext->activeSemesterForCourse($course);
        $semesters = $activeSemester ? collect([$activeSemester]) : collect();
        $rubrics = $course->submissionRubrics()->where('is_active', true)->get();
        $typeProfiles = AssignmentTypeProfile::availableDefinitions();

        return view('submission-tasks.edit', compact('course', 'task', 'semesters', 'rubrics', 'typeProfiles'));
    }

    /**
     * Update a submission task
     */
    public function update(Request $request, Course $course, SubmissionTask $task)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'instructions' => 'nullable|string|max:5000',
            'type' => ['required', Rule::in(AssignmentTypeProfile::enabledTypes())],
            'open_at' => 'nullable|date_format:Y-m-d\TH:i',
            'close_at' => 'nullable|date_format:Y-m-d\TH:i',
            'due_date' => 'nullable|date_format:Y-m-d\TH:i',
            'late_deadline' => 'nullable|date_format:Y-m-d\TH:i',
            'allow_late_submissions' => 'boolean',
            'max_resubmissions' => 'nullable|integer|min:0',
            'allow_group_submissions' => 'boolean',
            'min_group_size' => 'integer|min:1|max:10',
            'max_group_size' => 'integer|min:1|max:10',
            'max_file_size_mb' => 'required|integer|min:1|max:'.max(1, intdiv(\App\Services\SettingService::getMaxUploadSize($request->user()?->university_id), 1024 * 1024)),
            'max_file_count' => 'required|integer|min:1|max:20',
            'min_file_count' => 'required|integer|min:1',
            'rubric_id' => 'nullable|exists:submission_rubrics,id',
            'max_score' => 'required|numeric|min:1|max:1000',
            'require_approval_before_grading' => 'boolean',
            'late_submission_penalty_percent' => 'nullable|numeric|min:0|max:100',
            'submission_format' => 'required|in:file,text,both',
        ]);

        if (! empty($validated['rubric_id']) && ! $course->submissionRubrics()->whereKey($validated['rubric_id'])->exists()) {
            throw ValidationException::withMessages(['rubric_id' => 'The selected rubric does not belong to this course.']);
        }

        $this->assertTaskScheduleIsConsistent($validated);

        $validated['type_metadata'] = $this->validateTypeMetadata($request, $validated['type']);

        $task->update($validated);

        return redirect()->route('submission-tasks.lecturer.show', [$course, $task])
            ->with('success', 'Assignment updated successfully.');
    }

    /**
     * Publish a task (make it visible and open for submissions)
     */
    public function publish(Course $course, SubmissionTask $task)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('publish', $task);

        $task->publish();

        // Notify enrolled students of newly published assignment
        $enrolledStudents = User::whereHas('enrollments', function ($q) use ($course, $task) {
            $q->where('course_id', $course->id)
                ->where('semester_id', $task->semester_id)
                ->where('status', 'enrolled');
        })->get();

        if ($enrolledStudents->count() > 0) {
            event(new AssignmentCreated($task, $course, $enrolledStudents));
        }

        return back()->with('success', 'Assignment published and is now available to students.');
    }

    /**
     * Close a task (stop accepting new submissions)
     */
    public function close(Course $course, SubmissionTask $task)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('close', $task);

        $task->close();

        return back()->with('success', 'Assignment closed. Students can no longer submit new work.');
    }

    /**
     * Delete a submission task
     */
    public function destroy(Course $course, SubmissionTask $task)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('submission-tasks.manage.index', $course)
            ->with('success', 'Assignment deleted.');
    }

    /**
     * Upload attachment/template for a task
     */
    public function uploadAttachment(Request $request, Course $course, SubmissionTask $task, MediaSecurityService $media)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $task);

        $validated = $request->validate([
            'file' => 'required|file|max:'.max(1, intdiv(\App\Services\SettingService::getMaxUploadSize($request->user()?->university_id), 1024)),
            'type' => 'required|in:template,guide,rubric,example,other',
            'description' => 'nullable|string|max:500',
            'is_required' => 'boolean',
        ]);

        $file = $request->file('file');
        $asset = $media->store($file, $request->user(), $task, 'institution', ['purpose' => 'submission_task_attachment', 'task_uuid' => $task->uuid]);
        if (! in_array($asset->scan_status, ['clean', 'skipped'], true)) {
            throw ValidationException::withMessages(['file' => 'The attachment did not pass the configured security scan.']);
        }

        $attachment = SubmissionTaskAttachment::create([
            'submission_task_id' => $task->id,
            'file_name' => $asset->original_name,
            'file_path' => $asset->path,
            'disk' => $asset->disk,
            'media_asset_id' => $asset->id,
            'mime_type' => $asset->mime_type,
            'file_size' => $asset->size_bytes,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'is_required' => $validated['is_required'] ?? false,
        ]);
        $asset->update(['attachable_type' => $attachment->getMorphClass(), 'attachable_id' => $attachment->id]);

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(SubmissionTaskAttachment $attachment, SafeFileDeliveryService $files)
    {
        $this->authorize('view', $attachment->task);

        $attachment->loadMissing('mediaAsset');
        if ($attachment->mediaAsset) {
            abort_unless(in_array($attachment->mediaAsset->scan_status, ['clean', 'skipped'], true), 423, 'This file is unavailable until security checks pass.');
        }
        return $files->stream(
            $attachment->disk ?: (string) config('filesystems.default', 'local'),
            $attachment->file_path,
            $attachment->file_name,
            $attachment->mime_type,
            'attachment'
        );
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(Course $course, SubmissionTask $task, SubmissionTaskAttachment $attachment)
    {
        if ($task->course_id !== $course->id || $attachment->submission_task_id !== $task->id) {
            abort(404);
        }

        $this->authorize('update', $task);

        $storage = Storage::disk($attachment->disk ?: config('filesystems.default', 'local'));
        if ($storage->exists($attachment->file_path)) {
            $storage->delete($attachment->file_path);
        }
        $attachment->mediaAsset?->delete();
        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
    }

    /**
     * Show enrolled students and their assignment-specific deadline extensions.
     */
    public function extensions(Course $course, SubmissionTask $task)
    {
        $this->ensureTaskBelongsToCourse($course, $task);
        $this->authorize('grantExtension', $task);

        $enrollments = Enrollment::query()
            ->select(['id', 'user_id', 'course_id', 'semester_id', 'status'])
            ->with('user:id,first_name,last_name,email')
            ->where('course_id', $course->id)
            ->where('semester_id', $task->semester_id)
            ->where('status', 'enrolled')
            ->orderBy('user_id')
            ->paginate(25)
            ->withQueryString();

        $pageStudentIds = collect($enrollments->items())->pluck('user_id');
        $extensions = $task->extensions()
            ->whereIn('student_id', $pageStudentIds)
            ->get()
            ->keyBy('student_id');
        $extensionCount = $task->extensions()->count();

        return view('submission-tasks.extensions', compact('course', 'task', 'enrollments', 'extensions', 'extensionCount'));
    }

    /**
     * Grant deadline extension to a student
     */
    public function grantExtension(Request $request, Course $course, SubmissionTask $task)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('grantExtension', $task);

        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'extended_deadline' => 'required|date_format:Y-m-d\TH:i|after:now',
            'reason' => 'nullable|string|max:1000',
        ]);

        // Verify student is enrolled
        $enrolled = Enrollment::where('user_id', $validated['student_id'])
            ->where('course_id', $course->id)
            ->exists();

        if (! $enrolled) {
            return back()->with('error', 'Selected student is not enrolled in this course.');
        }

        SubmissionExtension::updateOrCreate(
            [
                'submission_task_id' => $task->id,
                'student_id' => $validated['student_id'],
            ],
            [
                'original_deadline' => $task->close_at,
                'extended_deadline' => $validated['extended_deadline'],
                'reason' => $validated['reason'],
                'status' => 'approved',
                'granted_by' => Auth::id(),
            ]
        );

        return back()->with('success', 'Deadline extension granted.');
    }

    /**
     * Remove deadline extension
     */
    public function revokeExtension(Course $course, SubmissionTask $task, SubmissionExtension $extension)
    {
        if ($task->course_id !== $course->id || $extension->submission_task_id !== $task->id) {
            abort(404);
        }

        $this->authorize('grantExtension', $task);

        $extension->delete();

        return back()->with('success', 'Extension revoked.');
    }

    /**
     * Get statistics for a task
     */
    private function getTaskStats(SubmissionTask $task): array
    {
        $firstGradeIds = DB::table('submission_grades')
            ->selectRaw('submission_id, MIN(id) AS id')
            ->groupBy('submission_id');

        $stats = DB::table('submissions')
            ->leftJoinSub($firstGradeIds, 'first_grade', function ($join): void {
                $join->on('first_grade.submission_id', '=', 'submissions.id');
            })
            ->leftJoin('submission_grades', 'submission_grades.id', '=', 'first_grade.id')
            ->where('submissions.submission_task_id', $task->id)
            ->whereNull('submissions.deleted_at')
            ->selectRaw('COUNT(submissions.id) AS total_submissions')
            ->selectRaw('SUM(CASE WHEN submissions.is_late = 1 THEN 1 ELSE 0 END) AS late_submissions')
            ->selectRaw("SUM(CASE WHEN submissions.status = 'graded' THEN 1 ELSE 0 END) AS graded_count")
            ->selectRaw("SUM(CASE WHEN submissions.status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) AS pending_review")
            ->selectRaw('AVG(submission_grades.score) AS average_score')
            ->first();

        return [
            'total_submissions' => (int) ($stats->total_submissions ?? 0),
            'late_submissions' => (int) ($stats->late_submissions ?? 0),
            'graded_count' => (int) ($stats->graded_count ?? 0),
            'pending_review' => (int) ($stats->pending_review ?? 0),
            'average_score' => (float) ($stats->average_score ?? 0),
        ];
    }
}
