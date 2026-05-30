<?php

namespace App\Http\Controllers;

use App\Events\AssignmentCreated;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\SubmissionExtension;
use App\Models\SubmissionTask;
use App\Models\SubmissionTaskAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SubmissionTaskController extends Controller
{
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
    public function availableForStudent(Course $course)
    {
        $this->authorize('view', $course);

        $tasks = $course->submissionTasks()
            ->where('status', 'published')
            ->where('is_visible_to_students', true)
            ->orderBy('open_at', 'asc')
            ->get();

        // Load submission count for each task
        $tasks->load('submissions');

        return view('submission-tasks.available', compact('course', 'tasks'));
    }

    /**
     * Create a new submission task
     */
    public function create(Course $course)
    {
        $this->authorize('createForCourse', $course);

        $semesters = Semester::active()->get();
        $rubrics = $course->submissionRubrics()->where('is_active', true)->get();

        return view('submission-tasks.create', compact('course', 'semesters', 'rubrics'));
    }

    /**
     * Store a new submission task
     */
    public function store(Request $request, Course $course)
    {
        $user = Auth::user();

        $this->authorize('createForCourse', $course);

        Log::info('Creating submission task', [
            'user_id' => Auth::id(),
            'course_id' => $course->id,
            'request_data' => $request->except(['_token']),
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'instructions' => 'nullable|string|max:5000',
            'type' => 'required|in:assignment,project,siwes,group,seminar',
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
            'max_file_size_mb' => 'required|integer|min:1|max:500',
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

        Log::info('Validation passed', ['validated_keys' => array_keys($validated)]);

        if (!empty($validated['allowed_file_types'])) {
            $validated['allowed_file_types'] = array_map('trim', explode(',', $validated['allowed_file_types']));
        } else {
            $validated['allowed_file_types'] = [];
        }

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

            $task = SubmissionTask::create([
                ...$validated,
                'course_id' => $course->id,
                'created_by' => Auth::id(),
            ]);

            Log::info('Submission task created successfully', [
                'task_id' => $task->id,
                'task_uuid' => $task->uuid,
            ]);

            // Check feature limits for group submissions and rubrics
            $subscription = $user->activeSubscription()->first();
            if ($subscription && $subscription->plan) {
                $plan = $subscription->plan;
                if ($task->allow_group_submissions && ! $plan->allow_group_submissions) {
                    $task->update(['allow_group_submissions' => false]);
                }
                if ($task->rubric_id && ! $plan->allow_rubrics) {
                    $task->update(['rubric_id' => null]);
                }
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

            return redirect()->route('submission-tasks.show', [$course, $task])
                ->with('success', 'Assignment created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create submission task', [
                'course_id' => $course->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors(['error' => 'Failed to create assignment: ' . $e->getMessage()]);
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
 * Student view for a specific submission task
 */
public function showForStudent(Course $course, SubmissionTask $task)
{

    $this->ensureTaskBelongsToCourse($course, $task);

    $this->authorize('view', $task);

    $task->load(['requirements', 'attachments', 'rubric']);

    $studentSubmissions = $task->submissions()
        ->where('user_id', Auth::id())
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

    $enrolledStudentIds = Enrollment::where('course_id', $course->id)
        ->where('semester_id', $task->semester_id)
        ->where('status', 'enrolled')
        ->pluck('user_id')
        ->toArray();

    $submittedStudentIds = $task->submissions()->pluck('user_id')->toArray();

    $nonSubmitters = User::whereIn('id', $enrolledStudentIds)
        ->whereNotIn('id', $submittedStudentIds)
        ->get();

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
        $semesters = Semester::active()->get();
        $rubrics = $course->submissionRubrics()->where('is_active', true)->get();

        return view('submission-tasks.edit', compact('course', 'task', 'semesters', 'rubrics'));
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
            'type' => 'required|in:assignment,project,siwes,group,seminar',
            'open_at' => 'nullable|date_format:Y-m-d\TH:i',
            'close_at' => 'nullable|date_format:Y-m-d\TH:i',
            'due_date' => 'nullable|date_format:Y-m-d\TH:i',
            'late_deadline' => 'nullable|date_format:Y-m-d\TH:i',
            'allow_late_submissions' => 'boolean',
            'max_resubmissions' => 'nullable|integer|min:0',
            'allow_group_submissions' => 'boolean',
            'min_group_size' => 'integer|min:1|max:10',
            'max_group_size' => 'integer|min:1|max:10',
            'max_file_size_mb' => 'required|integer|min:1|max:500',
            'max_file_count' => 'required|integer|min:1|max:20',
            'min_file_count' => 'required|integer|min:1',
            'rubric_id' => 'nullable|exists:submission_rubrics,id',
            'max_score' => 'required|numeric|min:1|max:1000',
            'require_approval_before_grading' => 'boolean',
            'late_submission_penalty_percent' => 'nullable|numeric|min:0|max:100',
            'submission_format' => 'required|in:file,text,both',
        ]);

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
    public function uploadAttachment(Request $request, Course $course, SubmissionTask $task)
    {
        if ($task->course_id !== $course->id) {
            abort(404);
        }

        $this->authorize('update', $task);

        $validated = $request->validate([
            'file' => 'required|file|max:51200',
            'type' => 'required|in:template,guide,rubric,example,other',
            'description' => 'nullable|string|max:500',
            'is_required' => 'boolean',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs(
            'assignment-attachments/'.$task->uuid,
            $file->getClientOriginalName()
        );

        SubmissionTaskAttachment::create([
            'submission_task_id' => $task->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $validated['description'],
            'type' => $validated['type'],
            'is_required' => $validated['is_required'] ?? false,
        ]);

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    /**
     * Download attachment
     */
    public function downloadAttachment(SubmissionTaskAttachment $attachment)
    {
        $this->authorize('view', $attachment->task);

        return Storage::download($attachment->file_path, $attachment->file_name);
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

        Storage::delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Attachment deleted.');
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
        $submissions = $task->submissions()->get();
        $lateSubmissions = $submissions->filter(fn ($s) => $s->is_late)->count();
        $gradedSubmissions = $submissions->filter(fn ($s) => $s->status === 'graded')->count();

        return [
            'total_submissions' => $submissions->count(),
            'late_submissions' => $lateSubmissions,
            'graded_count' => $gradedSubmissions,
            'pending_review' => $submissions->filter(fn ($s) => $s->status === 'submitted' || $s->status === 'under_review')->count(),
            'average_score' => $submissions->average(fn ($s) => $s->grade?->score) ?? 0,
        ];
    }
}
