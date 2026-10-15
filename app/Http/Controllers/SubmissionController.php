<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Events\CorrectionRequested;
use App\Events\SubmissionApproved;
use App\Events\SubmissionConfirmation;
use App\Events\SubmissionSubmitted;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Defense;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionRubric;
use App\Models\SubmissionTask;
use App\Models\SubmissionVersion;
use App\Services\AcademicContextService;
use App\Services\Assignments\AssignmentTypeProfile;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\NotificationService;
use App\Services\PdfService;
use App\Services\SettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function __construct(
        private MediaSecurityService $mediaSecurityService,
        private AcademicContextService $academicContext,
        private SafeFileDeliveryService $fileDelivery,
        private NotificationService $notifications,
    ) {
    }

    public function index()
    {
        if (! Gate::allows('viewAny', Submission::class)) {
            abort(403);
        }

        $submissions = Submission::where('user_id', Auth::id())
            ->with(['course', 'semester'])
            ->latest()
            ->paginate(10);

        return view('submissions.index', compact('submissions'));
    }

    /**
     * Student submission dashboard showing all submissions with stats
     */
    public function dashboard()
    {
        if (! Gate::allows('viewAny', Submission::class)) {
            abort(403);
        }

        $submissions = Submission::where('user_id', Auth::id())
            ->with(['course', 'semester', 'task', 'grade', 'versions', 'comments'])
            ->latest()
            ->paginate(20);

        // Calculate stats from the current page of results.
        $page = $submissions->getCollection();
        $stats = [
            'submitted' => $page->whereIn('status', ['submitted', 'resubmitted', 'under_review'])->count(),
            'graded' => $page->where('status', 'graded')->count(),
            'correction_requested' => $page->where('status', 'correction_requested')->count(),
            'approved' => $page->where('status', 'approved')->count(),
        ];

        // Upcoming and overdue published assignments the student has not yet
        // started, so the dashboard surfaces what needs attention next.
        $enrolledCourseIds = Enrollment::query()
            ->where('user_id', Auth::id())
            ->where('status', 'enrolled')
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $startedTaskIds = Submission::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('submission_task_id')
            ->pluck('submission_task_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $dueSoon = SubmissionTask::query()
            ->whereIn('course_id', $enrolledCourseIds ?: [-1])
            ->where('status', 'published')
            ->where('is_visible_to_students', true)
            ->whereNotIn('id', $startedTaskIds ?: [-1])
            ->where(function ($query) {
                $query->whereNotNull('due_date')->orWhereNotNull('close_at');
            })
            // `uuid` is required: Course uses uuid as its route key, so omitting it
            // makes route() treat the course parameter as missing.
            ->with(['course:id,uuid,code,name'])
            ->orderByRaw('COALESCE(close_at, due_date) ASC')
            ->limit(8)
            ->get();

        return view('submissions.student-dashboard', compact('submissions', 'stats', 'dueSoon'));
    }

    public function create(Request $request)
    {
        if (! Gate::allows('create', Submission::class)) {
            abort(403);
        }

        // Availability is determined by the student's own enrollments
        // (course + semester), exactly like SubmissionTaskPolicy::view. A
        // university filter must not be applied here: it wrongly excluded
        // independent students (no university) and courses without a
        // department/faculty chain.
        $enrollments = Enrollment::query()
            ->where('user_id', Auth::id())
            ->where('status', 'enrolled')
            ->with(['course.department.faculty', 'semester.academicSession'])
            ->get();

        $groups = Group::whereHas('members', function ($query) {
            $query->where('user_id', Auth::id());
        })
            ->with('course')
            ->get();

        $availableTasks = SubmissionTask::query()
            ->where('status', 'published')
            ->where('is_visible_to_students', true)
            // Disabled assignment types (e.g. SIWES) must not be selectable.
            ->whereIn('type', AssignmentTypeProfile::enabledTypes())
            ->where(function ($query) use ($enrollments): void {
                if ($enrollments->isEmpty()) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                foreach ($enrollments as $enrollment) {
                    $query->orWhere(function ($inner) use ($enrollment): void {
                        $inner->where('course_id', $enrollment->course_id)
                            ->where('semester_id', $enrollment->semester_id);
                    });
                }
            })
            ->with('course')
            ->orderBy('due_date')
            ->get();

        // A task can only be opened when it belongs to one of the student's active enrollments.
        $taskId = $request->query('task_id');
        $task = $taskId ? $availableTasks->firstWhere('id', (int) $taskId) : null;
        if ($taskId && ! $task) {
            return redirect()->route('submissions.create')
                ->with('error', 'That assignment is not available for your active courses.');
        }

        // Keep view data explicit. The create template displays the remaining
        // resubmission allowance whenever a task is selected; calculating it
        // here avoids an undefined local Blade variable and keeps the rule in
        // one testable place.
        $resubmitsLeft = null;
        if ($task) {
            $existingSubmission = $task->submissions()
                ->where('user_id', Auth::id())
                ->latest('id')
                ->first();
            $resubmitsLeft = $task->max_resubmissions
                ? max(0, (int) $task->max_resubmissions - (int) ($existingSubmission?->resubmission_count ?? 0))
                : '∞';
        }

        return view('submissions.create', compact(
            'enrollments', 'groups', 'task', 'availableTasks', 'resubmitsLeft'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_task_id' => 'nullable|exists:submission_tasks,id',
            'course_id' => 'required|exists:courses,id',
            'type' => 'required|in:assignment,project,siwes,group,seminar',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string|max:100000',
            'group_id' => 'nullable|exists:groups,id',
        ]);

        $task = null;

        $course = Course::with('department.faculty')->findOrFail($validated['course_id']);
        $activeSemester = $this->academicContext->activeSemesterForCourse($course);
        $enrollment = Enrollment::query()
            ->where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->where('status', 'enrolled')
            ->when($activeSemester, fn ($query) => $query->where('semester_id', $activeSemester->id))
            ->with('semester.academicSession')
            ->latest('id')
            ->first();

        if (! $enrollment || ! $enrollment->semester) {
            return back()->withInput()->with('error', 'You must be actively enrolled in this course before creating a submission.');
        }

        // Active enrollment (verified above) is the authorization boundary for
        // creating a submission, exactly as in create(). Applying the student's
        // own university here contradicted create() and produced a spurious
        // "Access Denied" page for legitimately enrolled independent students
        // (no university on their profile) and for students whose profile
        // university differs from the course's institution. Cross-institution
        // enrollments are recorded for auditing but must not block the student.
        $courseUniversityId = $course->department?->faculty?->university_id;
        if ($courseUniversityId !== null && (int) $courseUniversityId !== (int) Auth::user()->university_id) {
            Log::warning('Cross-institution submission enrollment detected', [
                'user_id' => Auth::id(),
                'course_id' => $course->id,
                'course_university_id' => (int) $courseUniversityId,
                'user_university_id' => Auth::user()->university_id,
            ]);
        }

        // If submission_task_id is provided, verify it belongs to the course and active semester.
        if ($validated['submission_task_id']) {
            $task = SubmissionTask::findOrFail($validated['submission_task_id']);
            if ($task->course_id != $validated['course_id']) {
                return back()->withInput()->with('error', 'Selected assignment does not belong to the chosen course.');
            }
            if ($task->status !== 'published' || ! $task->is_visible_to_students || $task->semester_id !== $enrollment->semester_id) {
                return back()->withInput()->with('error', 'This assignment is not available in your active semester.');
            }
            if (! AssignmentTypeProfile::isEnabled($task->type)) {
                return back()->withInput()->with('error', 'This assignment type is currently unavailable.');
            }
        }

        if ($validated['type'] === 'group') {
            if (! $validated['group_id']) {
                return back()->withInput()->with('error', 'Please select a group for group submissions.');
            }

            $group = Group::where('id', $validated['group_id'])
                ->whereHas('members', function ($query) {
                    $query->where('user_id', Auth::id());
                })
                ->first();

            if (! $group) {
                return back()->withInput()->with('error', 'You must select a valid group you belong to.');
            }

            if ($group->course_id !== $validated['course_id']) {
                return back()->withInput()->with('error', 'The selected group does not belong to the chosen course.');
            }
        }

        // Validate optional initial file uploads using the same rules as the
        // standalone upload endpoint (institution upload policy + allowed mimes).
        $maxFileSizeBytes = SettingService::getMaxUploadSize($request->user()->university_id);
        $maxFileSizeKb = intdiv($maxFileSizeBytes, 1024);
        $allowedMimes = implode(',', SettingService::getAllowedExtensions($request->user()->university_id));

        $request->validate([
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|mimes:'.$allowedMimes.'|max:'.$maxFileSizeKb,
        ]);

        // Written submissions are stored (sanitized) on the submission's
        // description. The required input depends on the assignment's format.
        $format = $task?->submission_format ?? 'file';
        $files = $request->file('files', []);
        $content = null;
        if (in_array($format, ['text', 'both'], true) || $request->filled('content')) {
            $content = \App\Support\HtmlSanitizer::clean($request->input('content'));
        }

        $plainContent = trim(\App\Support\HtmlSanitizer::toPlainText($content));
        $hasContent = $plainContent !== '';
        $hasFiles = is_array($files) && count($files) > 0;

        if ($format === 'text' && ! $hasContent) {
            throw ValidationException::withMessages(['content' => 'Please write your submission before saving.']);
        }
        if ($format === 'both' && ! $hasContent && ! $hasFiles) {
            throw ValidationException::withMessages(['content' => 'Add a written answer or attach at least one file before saving.']);
        }

        if (count($files) > 10) {
            return back()->withInput()->with('error', 'You can attach at most 10 files per submission.');
        }

        $semester = $enrollment->semester;

        $submissionData = [
            'user_id' => Auth::id(),
            'semester_id' => $semester?->id,
            'uuid' => Str::uuid(),
            'status' => 'draft',
            'submission_task_id' => $validated['submission_task_id'] ?? null,
            'course_id' => $validated['course_id'],
            'type' => $validated['type'],
            'title' => $validated['title'],
            'description' => $content ?? ($validated['description'] ?? null),
        ];

        if (! empty($validated['group_id'])) {
            $submissionData['group_id'] = $validated['group_id'];
        }

        // Atomic operation: create the submission and its initial version(s)
        // in a single transaction so a partial failure (e.g. storage error)
        // never leaves an orphaned submission with missing files.
        $submission = DB::transaction(function () use ($submissionData, $files, $request) {
            $submission = Submission::create($submissionData);

            if (empty($files)) {
                return $submission;
            }

            // If the draft already has versions, mark them as not current
            // before attaching the new ones so there is always exactly one
            // current version.
            $submission->versions()->where('is_current', true)->update(['is_current' => false]);

            $nextVersion = $submission->version ?? 1;
            foreach ($files as $file) {
                $this->createScannedVersion($submission, $file, $nextVersion++);
            }

            // Persist the next version counter so subsequent uploads continue
            // the sequence.
            $submission->version = $nextVersion;
            $submission->save();

            return $submission;
        });

        // The create form's primary action ("Submit Assignment") posts with
        // intent=submit so the student completes the submission in a single
        // step. A plain store (no intent) keeps the draft lifecycle unchanged:
        // the submission stays a draft until the student explicitly submits.
        if ($request->input('intent') === 'submit') {
            $error = $this->completeSubmission($submission->fresh());

            if ($error !== null) {
                // The draft is preserved so no work is lost when the lifecycle
                // guards (window, file policy, security scan) reject the submit.
                return redirect()->route('submissions.show', $submission)->with('error', $error);
            }

            return redirect()->route('submissions.show', $submission)
                ->with('success', $submission->is_late
                    ? 'Submission received after the deadline — it has been flagged as late.'
                    : 'Submission submitted successfully. AI analysis is running in the background.');
        }

        return redirect()->route('submissions.show', $submission)
            ->with('success', 'Draft saved. Review it and submit when you are ready.');
    }

    public function show(Submission $submission)
    {
        $this->authorize('view', $submission);

        // `task` is eager-loaded so the detail page can render the assignment
        // context (task title, max score, format) without extra queries.
        $submission->load(['versions', 'comments.user', 'grade', 'course', 'group', 'task']);
        $aiAnalyses = \App\Models\AiAnalysis::query()
            ->where('submission_id', $submission->id)
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->get();

        return view('submissions.show', compact('submission', 'aiAnalyses'));
    }

    public function edit(Submission $submission)
    {
        $this->authorize('update', $submission);

        // The draft editor renders the written answer and needs the assignment
        // format (task) plus the uploaded files for context.
        $submission->loadMissing(['task', 'versions', 'course']);

        return view('submissions.edit', compact('submission'));
    }

    public function update(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:100000',
        ]);

        if ($request->has('description')) {
            // Store the written answer sanitized, exactly like the create flow,
            // so a draft edit can never introduce unsafe markup.
            $validated['description'] = \App\Support\HtmlSanitizer::clean($request->input('description'));
        } else {
            // File-only drafts never render the written-answer field; leave the
            // stored value untouched instead of wiping it.
            unset($validated['description']);
        }

        $submission->update($validated);

        return redirect()->route('submissions.show', $submission)
            ->with('success', 'Draft updated.');
    }

    public function upload(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);

        // Get effective upload limit from user's plan
        $maxFileSizeBytes = SettingService::getMaxUploadSize($request->user()->university_id);
        $maxFileSizeKb = intdiv($maxFileSizeBytes, 1024);
        $allowedMimes = implode(',', SettingService::getAllowedExtensions($request->user()->university_id));

        $validated = $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:'.$allowedMimes.'|max:'.$maxFileSizeKb,
        ]);

        $files = $request->file('files');
        $existingCount = $submission->versions()->count();
        $newCount = count($files);

        if ($existingCount + $newCount > 20) {
            return back()->with('error', 'A submission can have no more than 20 versions.');
        }

        DB::transaction(function () use ($submission, $files): void {
            $submission->versions()->where('is_current', true)->update(['is_current' => false]);
            $currentVersion = max(1, (int) $submission->version);
            foreach ($files as $file) {
                $this->createScannedVersion($submission, $file, $currentVersion++);
            }
            $submission->update(['version' => $currentVersion]);
        });

        return back()->with('success', 'Files uploaded successfully.');
    }

    public function submit(Request $request, Submission $submission)
    {
        $this->authorize('submit', $submission);

        $error = $this->completeSubmission($submission);

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return redirect()->route('submissions.show', $submission)
            ->with('success', $submission->is_late
                ? 'Submission received after the deadline — it has been flagged as late.'
                : 'Submission submitted successfully. AI analysis is running in the background.');
    }

    /**
     * Apply the submission lifecycle transition: eligibility, window, format and
     * file-policy guards, late handling, confirmation/notification events and
     * deferred AI analysis. Shared by the explicit submit action and by the
     * create form's single-step "Submit Assignment" flow so there is exactly one
     * source of truth for what "submitted" means.
     *
     * Returns null on success, or a user-facing error message otherwise. When an
     * error is returned the submission is left untouched (still a draft) so no
     * work is lost.
     */
    private function completeSubmission(Submission $submission): ?string
    {
        $task = $submission->task;

        if ($task && ! AssignmentTypeProfile::isEnabled($task->type)) {
            return 'This assignment type is currently unavailable.';
        }

        if ($task && ($task->status !== 'published' || ! $task->is_visible_to_students)) {
            return 'This submission task is no longer accepting work.';
        }
        if ($task) {
            // The window is evaluated against the student's acceptance deadline,
            // which honours the late window and any approved extension.
            $acceptance = $task->acceptanceDeadline($submission->user);
            if (($task->open_at && now()->isBefore($task->open_at))
                || ($acceptance && now()->isAfter($acceptance))) {
                return 'This assignment is not currently accepting submissions.';
            }
        }

        // The required submission content depends on the assignment's format:
        // text uses the written answer, file uses uploads, both accept either.
        $format = $task?->submission_format ?? 'file';
        $hasFiles = $submission->versions()->exists();
        $hasText = trim(\App\Support\HtmlSanitizer::toPlainText($submission->description)) !== '';

        if ($format === 'text' && ! $hasText) {
            return 'Write your submission before submitting.';
        }
        if ($format === 'file' && ! $hasFiles) {
            return 'Upload at least one file before submitting.';
        }
        if ($format === 'both' && ! $hasFiles && ! $hasText) {
            return 'Add your written answer or upload at least one file before submitting.';
        }

        if ($hasFiles && $submission->versions()->whereHas('mediaAsset', fn ($query) => $query->whereNotIn('scan_status', ['clean', 'skipped']))->exists()) {
            return 'A submission file has not passed security processing.';
        }

        // Enforce the assignment's file policy before accepting the submission.
        if ($task && $hasFiles) {
            $fileCount = $submission->versions()->count();
            if ($task->acceptsFiles()) {
                $minFiles = max(1, (int) ($task->min_file_count ?? 1));
                if ($fileCount < $minFiles) {
                    return "This assignment requires at least {$minFiles} file(s).";
                }
            }
            if ($task->max_file_count && $fileCount > (int) $task->max_file_count) {
                return "This assignment allows at most {$task->max_file_count} file(s).";
            }
            $allowed = array_values(array_filter(array_map('strtolower', (array) ($task->allowed_file_types ?? []))));
            if ($allowed !== []) {
                $extensions = $submission->versions()->pluck('file_name')
                    ->map(fn ($name) => strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)));
                $blocked = $extensions->unique()->diff($allowed)->values();
                if ($blocked->isNotEmpty()) {
                    return 'This assignment does not accept file type(s): '.$blocked->implode(', ').'.';
                }
            }
        }

        // Late handling: compare against the effective deadline, which honours an
        // approved per-student extension before falling back to the task deadline.
        $submittedAt = now();
        $isLate = false;
        $deadline = null;
        if ($task) {
            $deadline = $task->getEffectiveDeadline($submission->user);
        } elseif ($submission->close_at) {
            $deadline = $submission->close_at;
        }
        if ($deadline) {
            $deadline = \Illuminate\Support\Carbon::parse($deadline);
            $isLate = $submittedAt->greaterThan($deadline);
        }

        $status = $submission->status === 'correction_requested' ? 'resubmitted' : 'submitted';
        $isResubmission = $status === 'resubmitted';

        $submission->update([
            'status' => $status,
            'submitted_at' => $submittedAt,
            'is_late' => $isLate,
            'resubmission_count' => $isResubmission ? ((int) $submission->resubmission_count) + 1 : $submission->resubmission_count,
            'last_resubmitted_at' => $isResubmission ? $submittedAt : $submission->last_resubmitted_at,
        ]);

        if ($isLate && $task && $deadline) {
            \App\Models\LateSubmission::updateOrCreate(
                ['submission_id' => $submission->id],
                [
                    'submission_task_id' => $task->id,
                    'submitted_at' => $submittedAt,
                    'deadline_at' => $deadline,
                    'minutes_late' => (int) max(0, $deadline->diffInMinutes($submittedAt)),
                    'penalty_applied_percent' => (float) ($task->late_submission_penalty_percent ?? 0),
                ]
            );
        }

        // Fire submission confirmation to student
        event(new SubmissionConfirmation($submission, $submission->user));

        // Notify lecturer(s) of the course
        $lecturers = $submission->course->lecturerAssignments()->with('user')->get()->pluck('user')->filter();
        foreach ($lecturers as $lecturer) {
            event(new SubmissionSubmitted($submission, $submission->course, $lecturer));
        }

        // Trigger asynchronous AI analysis (validator + plagiarism) without blocking.
        event(new \App\Events\SubmissionAiAnalysisRequested($submission, $submission->user));

        return null;
    }

    public function download(SubmissionVersion $version)
    {
        $submission = $version->submission;

        if (! auth()->user()->can('view', $submission)) {
            abort(403);
        }

        $this->assertVersionIsSafe($version);
        return $this->fileDelivery->stream($version->disk ?: (string) config('filesystems.default', 'local'), $version->file_path, $version->file_name, $version->mime_type, 'attachment');
    }

    public function destroy(Submission $submission)
    {
        $this->authorize('delete', $submission);

        if ($submission->status !== 'draft') {
            return back()->with('error', 'Only draft submissions can be deleted.');
        }

        $submission->delete();

        return redirect()->route('submissions.index')->with('success', 'Submission deleted.');
    }

    /**
     * View submission file inline (PDF viewer)
     */
    public function viewFile(Submission $submission, $versionId = null)
    {
        $this->authorize('view', $submission);

        if ($versionId) {
            $version = $submission->versions()->where('id', $versionId)->firstOrFail();
        } else {
            $version = $submission->versions()->where('is_current', true)->firstOrFail();
        }

        $this->assertVersionIsSafe($version);
        $mime = $version->mime_type ?: 'application/octet-stream';
        $inline = str_contains($mime, 'pdf') || str_contains($mime, 'image');

        return $this->fileDelivery->stream(
            $version->disk ?: (string) config('filesystems.default', 'local'),
            $version->file_path,
            $version->file_name,
            $mime,
            $inline ? 'inline' : 'attachment',
            $inline ? ['Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'"] : []
        );
    }

    /**
     * Schedule defense for a submission (project/seminar)
     */
    public function scheduleDefense(Request $request, Submission $submission)
    {
        $this->authorize('grade', $submission);

        // Only allow defense scheduling for approved/graded project or seminar submissions
        if (! in_array($submission->type, ['project', 'seminar'])) {
            return back()->with('error', 'Defense scheduling is only available for projects and seminars.');
        }

        if (! in_array($submission->status, ['approved', 'graded'])) {
            return back()->with('error', 'Submission must be approved or graded before scheduling defense.');
        }

        $existingDefense = Defense::where('submission_id', $submission->id)->first();

        return view('defenses.schedule', compact('submission', 'existingDefense'));
    }

    /**
     * Store defense schedule
     */
    public function storeDefense(Request $request, Submission $submission)
    {
        $this->authorize('grade', $submission);

        if (! in_array($submission->type, ['project', 'seminar'])) {
            return back()->with('error', 'Defense scheduling is only available for projects and seminars.');
        }

        $validated = $request->validate([
            'scheduled_at' => 'required|date_format:Y-m-d\TH:i',
            'duration_minutes' => 'required|integer|min:15|max:180',
            'venue' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        Defense::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'course_id' => $submission->course_id,
                'lecturer_id' => Auth::id(),
                'scheduled_at' => $validated['scheduled_at'],
                'duration_minutes' => $validated['duration_minutes'],
                'venue' => $validated['venue'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'scheduled',
            ]
        );

        return back()->with('success', 'Defense scheduled successfully.');
    }

    /**
     * Generate formal document from approved submission (Final Year Project, SIWES, etc.)
     */
    public function generateDocument(Request $request, Submission $submission)
    {
        $this->authorize('view', $submission);

        if ($submission->status !== 'approved' && $submission->status !== 'graded') {
            return back()->with('error', 'Submission must be approved or graded to generate documents.');
        }

        $validated = $request->validate([
            'template_id' => 'nullable|exists:document_templates,id',
            'document_type' => 'required|in:project,siwes,seminar,group',
        ]);

        $templateId = $validated['template_id'];
        if (! $templateId) {
            // Try to find default template for this type
            $template = DocumentTemplate::where('type', $validated['document_type'])
                ->where('is_default', true)
                ->first();
            $templateId = $template?->id;
        }

        // Check if document already generated
        $existingDoc = GeneratedDocument::where('submission_id', $submission->id)
            ->where('template_id', $templateId)
            ->first();

        if ($existingDoc) {
            return $this->fileDelivery->stream((string) config('filesystems.default', 'local'), $existingDoc->file_path, $existingDoc->title.'.pdf', 'application/pdf', 'attachment');
        }

        // Generate document
        $pdfService = new PdfService;
        $fileName = Str::slug($submission->title).'_'.now()->format('YmdHis').'.pdf';
        $filePath = 'generated-documents/'.$submission->id.'/'.$fileName;

        $data = [
            'submission' => $submission->load(['user', 'course', 'grade']),
            'generated_at' => now(),
            'document_type' => $validated['document_type'],
        ];

        $pdf = Pdf::loadView('pdfs.'.$validated['document_type'], $data);
        $pdf->setPaper('a4', 'portrait');
        $pdfBytes = $pdf->output();
        Storage::put($filePath, $pdfBytes);

        $doc = GeneratedDocument::create([
            'user_id' => $submission->user_id,
            'submission_id' => $submission->id,
            'template_id' => $templateId,
            'title' => $submission->title,
            'file_path' => $filePath,
            'file_size' => strlen($pdfBytes),
            'status' => 'ready',
        ]);

        return $this->fileDelivery->stream((string) config('filesystems.default', 'local'), $filePath, $fileName, 'application/pdf', 'attachment');
    }

    /**
     * Replace files in a submission (before deadline)
     */
    public function replaceFiles(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);

        // Check if deadline allows editing
        $task = $submission->task;
        if ($task && now() > ($task->late_deadline ?? $task->close_at)) {
            return back()->with('error', 'Deadline has passed, cannot edit files.');
        }

        // Get effective upload limit from user's plan
        $maxFileSizeBytes = SettingService::getMaxUploadSize($request->user()->university_id);
        $maxFileSizeKb = intdiv($maxFileSizeBytes, 1024);
        $allowedMimes = implode(',', SettingService::getAllowedExtensions($request->user()->university_id));

        $validated = $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:'.$allowedMimes.'|max:'.$maxFileSizeKb,
        ]);

        DB::transaction(function () use ($submission, $request): void {
            $submission->versions()->where('is_current', true)->update(['is_current' => false]);
            $currentVersion = max(1, (int) $submission->version);
            foreach ($request->file('files') as $file) {
                $this->createScannedVersion($submission, $file, $currentVersion++);
            }
            $submission->update(['version' => $currentVersion]);
        });

        return back()->with('success', 'Files updated successfully.');
    }

    public function lecturerIndex(Request $request)
    {
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        $filters = $request->validate([
            'course_id' => 'nullable|integer|exists:courses,id',
            'task_id' => 'nullable|integer|exists:submission_tasks,id',
            'status' => 'nullable|in:draft,submitted,resubmitted,under_review,graded,correction_requested,approved',
            'q' => 'nullable|string|max:100',
        ]);

        $courseIds = Course::query()
            ->whereHas('lecturerAssignments', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id');

        $submissions = Submission::query()
            ->whereIn('course_id', $courseIds)
            ->with(['user', 'course', 'grade'])
            ->when($filters['course_id'] ?? null, fn ($query, $courseId) => $query->where('course_id', $courseId))
            ->when($filters['task_id'] ?? null, fn ($query, $taskId) => $query->where('submission_task_id', $taskId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', '%'.$term.'%')
                        ->orWhereHas('user', function ($userQuery) use ($term) {
                            $userQuery->where('first_name', 'like', '%'.$term.'%')
                                ->orWhere('last_name', 'like', '%'.$term.'%')
                                ->orWhere('email', 'like', '%'.$term.'%');
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $courses = Course::query()->whereIn('id', $courseIds)->orderBy('code')->get(['id', 'code', 'name']);
        $tasks = SubmissionTask::query()->whereIn('course_id', $courseIds)->orderByDesc('id')->get(['id', 'title', 'course_id']);

        return view('submissions.lecturer-index', compact('submissions', 'courses', 'tasks'));
    }

    /**
     * Apply the same review decision to many submissions at once. Each item is
     * authorised individually so a lecturer can never act outside their courses.
     */
    public function bulk(Request $request)
    {
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,request_correction',
            'submission_ids' => 'required|array|min:1|max:100',
            'submission_ids.*' => 'integer|exists:submissions,id',
        ]);

        $submissions = Submission::query()->whereIn('id', $validated['submission_ids'])->get();
        $affected = 0;

        foreach ($submissions as $submission) {
            try {
                if ($validated['action'] === 'approve') {
                    $this->authorize('approve', $submission);
                    $submission->update(['status' => 'approved']);
                    event(new SubmissionApproved($submission, $submission->user));
                } else {
                    $this->authorize('requestCorrection', $submission);
                    $submission->update(['status' => 'correction_requested']);
                    event(new CorrectionRequested($submission, $submission->user));
                }
                $affected++;
            } catch (\Illuminate\Auth\Access\AuthorizationException) {
                // Skip submissions this lecturer is not responsible for.
            }
        }

        AuditLog::log('submission_bulk_action', $user->id, Submission::class, null, null, [
            'action' => $validated['action'],
            'count' => $affected,
        ], request()->ip(), request()->userAgent());

        return back()->with('success', $affected.' submission(s) updated.');
    }

    public function review(Submission $submission)
    {
        $this->authorize('grade', $submission);

        if (in_array($submission->status, ['submitted', 'resubmitted'], true)) {
            $submission->update(['status' => 'under_review']);
        }

        $submission->load(['user', 'course', 'versions', 'comments.user', 'grade']);

        return view('submissions.review', compact('submission'));
    }

    public function grade(Request $request, Submission $submission)
    {
        $this->authorize('grade', $submission);

        $task = $submission->task;
        $rubric = $task?->rubric;
        $maxScore = ($task && $task->max_score !== null) ? (float) $task->max_score : 100.0;

        $validated = $request->validate([
            'score' => 'nullable|numeric|min:0|max:'.$maxScore,
            'feedback' => 'nullable|string|max:5000',
            'criteria' => 'nullable|array',
            'criteria.*' => 'nullable|numeric|min:0',
            'criteria_feedback' => 'nullable|array',
            'criteria_feedback.*' => 'nullable|string|max:1000',
        ]);

        // Rubric-based grading: when the task has a rubric and criterion scores
        // are supplied, clamp each to its maximum and derive the total if the
        // lecturer did not type an overall score.
        $criteriaScores = null;
        if ($rubric && ! empty($validated['criteria'])) {
            $criteriaScores = $this->normalizeCriteriaScores($rubric, $validated['criteria']);
            if (! isset($validated['score'])) {
                $validated['score'] = array_sum($criteriaScores);
            }
        }

        $criteriaFeedback = null;
        if (! empty($validated['criteria_feedback'])) {
            $criteriaFeedback = [];
            foreach ($validated['criteria_feedback'] as $criterion => $note) {
                $note = trim((string) $note);
                if ($note !== '') {
                    $criteriaFeedback[(string) $criterion] = $note;
                }
            }
            if ($criteriaFeedback === []) {
                $criteriaFeedback = null;
            }
        }

        if (! isset($validated['score'])) {
            throw ValidationException::withMessages(['score' => 'Enter a score, or complete the rubric, before saving the grade.']);
        }

        $rawScore = min((float) $validated['score'], $maxScore);

        // Apply the assignment's late penalty at grade time, honouring the
        // task's configured percentage, and record the before/after values.
        $penaltyPercent = $submission->is_late ? (float) $submission->getLatePenalty() : 0.0;
        $score = $penaltyPercent > 0
            ? round(max(0.0, $rawScore - ($rawScore * $penaltyPercent / 100)), 2)
            : $rawScore;

        $submission->update(['status' => 'graded', 'graded_at' => now()]);

        $submission->grade()->updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'user_id' => Auth::id(),
                'score' => $score,
                'max_score' => $maxScore,
                'rubric_id' => $rubric?->id,
                'feedback' => $validated['feedback'] ?? null,
                'criteria_scores' => $criteriaScores,
                'criteria_feedback' => $criteriaFeedback,
                'is_final' => true,
            ]
        );

        // Immutable history of every grade recorded for this submission.
        \App\Models\SubmissionGradeHistory::create([
            'submission_id' => $submission->id,
            'graded_by' => Auth::id(),
            'score' => $score,
            'max_score' => $maxScore,
            'criteria_scores' => $criteriaScores,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        if ($penaltyPercent > 0) {
            \App\Models\LateSubmission::where('submission_id', $submission->id)->update([
                'score_before_penalty' => $rawScore,
                'score_after_penalty' => $score,
                'penalty_applied_percent' => $penaltyPercent,
            ]);
        }

        // Grading is a high-accountability action (and may apply an AI
        // suggestion), so record an explicit audit entry.
        AuditLog::log(
            'submission_graded',
            Auth::id(),
            Submission::class,
            $submission->id,
            null,
            ['score' => $score, 'max_score' => $maxScore, 'rubric_id' => $rubric?->id, 'late_penalty_percent' => $penaltyPercent],
            request()->ip(),
            request()->userAgent(),
        );

        // Let the student know the grade and feedback are available.
        $this->notifications->send(
            $submission->user,
            NotificationType::GRADE_POSTED,
            'Grade posted',
            "Your submission '{$submission->title}' has been graded.",
            [
                'submission_id' => $submission->uuid,
                'score' => $score,
                'max_score' => $maxScore,
                'url' => route('submissions.show', $submission),
            ]
        );

        return back()->with('success', 'Grade submitted.');
    }

    /**
     * Clamp rubric criterion scores to the rubric's defined maxima.
     *
     * @param  array<int|string,mixed>  $submitted
     * @return array<string,float>
     */
    private function normalizeCriteriaScores(SubmissionRubric $rubric, array $submitted): array
    {
        $criteria = is_array($rubric->criteria) ? $rubric->criteria : [];
        $normalized = [];

        foreach ($criteria as $index => $criterion) {
            $key = is_array($criterion)
                ? (string) ($criterion['key'] ?? $criterion['name'] ?? $index)
                : (string) $index;

            if (! array_key_exists($key, $submitted) && ! array_key_exists($index, $submitted)) {
                continue;
            }

            $value = (float) ($submitted[$key] ?? $submitted[$index]);
            $max = is_array($criterion)
                ? (float) ($criterion['points'] ?? $criterion['max_points'] ?? $criterion['weight'] ?? 0)
                : 0.0;

            $normalized[$key] = round(max(0.0, $max > 0 ? min($value, $max) : $value), 2);
        }

        return $normalized;
    }

    public function comment(Request $request, Submission $submission)
    {
        $this->authorize('comment', $submission);

        $user = Auth::user();
        // The commenting endpoint is reviewer-only: a student cannot leave
        // feedback on their own submission through this route.
        if ($user->id === $submission->user_id || ! $user->canViewCourseSubmissions($submission->course)) {
            abort(403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
        ]);

        $submission->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['comment'],
            'type' => 'general',
            'status' => 'pending',
        ]);

        // Notify the student that new feedback is available.
        $this->notifications->send(
            $submission->user,
            NotificationType::FEEDBACK_POSTED,
            'New feedback',
            "New feedback was added to '{$submission->title}'.",
            [
                'submission_id' => $submission->uuid,
                'url' => route('submissions.show', $submission),
            ]
        );

        return back()->with('success', 'Comment added.');
    }

    public function approve(Request $request, Submission $submission)
    {
        $this->authorize('approve', $submission);

        $submission->update(['status' => 'approved']);

        AuditLog::log('submission_approved', Auth::id(), Submission::class, $submission->id, null, null, request()->ip(), request()->userAgent());

        event(new SubmissionApproved($submission, $submission->user));

        return back()->with('success', 'Submission approved and the student has been notified.');
    }

    public function reject(Request $request, Submission $submission)
    {
        $this->authorize('reject', $submission);

        $submission->update(['status' => 'correction_requested']);

        AuditLog::log('submission_rejected', Auth::id(), Submission::class, $submission->id, null, null, request()->ip(), request()->userAgent());

        event(new CorrectionRequested($submission, $submission->user));

        return back()->with('success', 'Major corrections requested and the student has been notified.');
    }

    public function compare(Request $request, Submission $submission)
    {
        $this->authorize('view', $submission);

        $validated = $request->validate([
            'version_a' => 'required|exists:submission_versions,id',
            'version_b' => 'required|exists:submission_versions,id|different:version_a',
        ]);

        $versions = $submission->versions()
            ->whereIn('id', [$validated['version_a'], $validated['version_b']])
            ->get();

        if ($versions->count() !== 2) {
            return back()->with('error', 'Please select two valid versions to compare.');
        }

        return view('submissions.compare', compact('submission', 'versions'));
    }

    public function requestCorrection(Submission $submission)
    {
        $this->authorize('requestCorrection', $submission);

        $submission->update(['status' => 'correction_requested']);

        AuditLog::log('submission_correction_requested', Auth::id(), Submission::class, $submission->id, null, null, request()->ip(), request()->userAgent());

        event(new CorrectionRequested($submission, $submission->user));

        return back()->with('success', 'Correction requested and the student has been notified.');
    }

    public function exportGrades(Request $request)
    {
        $user = Auth::user();

        if (! $user->isLecturer() && ! $user->isDepartmentAdmin() && ! $user->isUniversityAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'format' => 'nullable|in:csv,pdf',
        ]);

        $course_id = $validated['course_id'];

        // Scope the export to courses the actor is actually responsible for, so
        // admins cannot pull grades for other institutions/departments.
        $course = Course::with('department.faculty')->findOrFail($course_id);
        if (! $user->canViewCourseSubmissions($course)) {
            abort(403, 'You cannot export grades for this course.');
        }

        $format = $validated['format'] ?? 'csv';

        $submissions = Submission::where('course_id', $course_id)
            ->whereIn('status', ['graded', 'approved'])
            ->with('user', 'grade')
            ->get();

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="grades-course-'.$course_id.'.csv"',
            ];

            $callback = function () use ($submissions) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Student ID', 'Student Name', 'Title', 'Type', 'Score', 'Max Score', 'Submitted At', 'Graded At']);

                foreach ($submissions as $sub) {
                    fputcsv($handle, [
                        $sub->user->student_id ?? '',
                        $sub->user->first_name.' '.$sub->user->last_name,
                        $sub->title,
                        $sub->type,
                        $sub->grade?->score ?? '',
                        $sub->grade?->max_score ?? 100,
                        $sub->submitted_at?->format('Y-m-d H:i:s') ?? '',
                        $sub->graded_at?->format('Y-m-d H:i:s') ?? '',
                    ]);
                }

                fclose($handle);
            };

            return response()->stream($callback, 200, $headers);
        }

        // PDF export
        $course = Course::findOrFail($course_id);
        $pdfService = new PdfService;
        $pdfContent = $pdfService->generateCourseGradesPdf($course, $submissions);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="grades-'.$course->code.'-'.now()->format('Y-m-d').'.pdf"',
        ]);
    }

    private function createScannedVersion(Submission $submission, \Illuminate\Http\UploadedFile $file, int $versionNumber): SubmissionVersion
    {
        $asset = $this->mediaSecurityService->store(
            $file,
            Auth::user(),
            $submission,
            'private',
            ['purpose' => 'submission_version', 'submission_uuid' => $submission->uuid]
        );

        if ($asset->scan_status === 'infected') {
            throw ValidationException::withMessages(['files' => 'An uploaded file failed malware scanning and was quarantined.']);
        }

        if (! in_array($asset->scan_status, ['clean', 'skipped'], true)) {
            throw ValidationException::withMessages(['files' => 'The file could not pass the configured security scan.']);
        }

        return $submission->versions()->create([
            'version_number' => $versionNumber,
            'file_name' => $asset->original_name,
            'file_path' => $asset->path,
            'disk' => $asset->disk,
            'media_asset_id' => $asset->id,
            'file_size' => $asset->size_bytes,
            'mime_type' => $asset->mime_type,
            'uploaded_by' => Auth::id(),
            'is_current' => true,
        ]);
    }

    private function assertVersionIsSafe(SubmissionVersion $version): void
    {
        $version->loadMissing('mediaAsset');
        if ($version->mediaAsset) {
            abort_unless(in_array($version->mediaAsset->scan_status, ['clean', 'skipped'], true), 423, 'This file is unavailable until security checks pass.');
        }
    }

}
