<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\SubmissionConfirmation;
use App\Events\SubmissionSubmitted;
use App\Models\Course;
use App\Models\Defense;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\SubmissionVersion;
use App\Services\AcademicContextService;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\PdfService;
use App\Services\SettingService;
use App\Services\SubscriptionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private MediaSecurityService $mediaSecurityService,
        private AcademicContextService $academicContext,
        private SafeFileDeliveryService $fileDelivery,
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

        // Calculate stats
        $stats = [
            'submitted' => $submissions->whereIn('status', ['submitted', 'resubmitted', 'under_review'])->count(),
            'graded' => $submissions->where('status', 'graded')->count(),
            'correction_requested' => $submissions->where('status', 'correction_requested')->count(),
            'approved' => $submissions->where('status', 'approved')->count(),
        ];

        return view('submissions.student-dashboard', compact('submissions', 'stats'));
    }

    public function create(Request $request)
    {
        if (! Gate::allows('create', Submission::class)) {
            abort(403);
        }

        $universityId = Auth::user()->university_id;
        $enrollments = Enrollment::where('user_id', Auth::id())
            ->where('status', 'enrolled')
            ->whereHas('semester', fn ($query) => $query->where('is_active', true)
                ->whereHas('academicSession', fn ($session) => $session->where('university_id', $universityId)))
            ->whereHas('course.department.faculty', fn ($query) => $query->where('university_id', $universityId))
            ->with(['course.department.faculty', 'semester'])
            ->get();

        $groups = Group::whereHas('members', function ($query) {
            $query->where('user_id', Auth::id());
        })
            ->with('course')
            ->get();

        $courseIds = $enrollments->pluck('course_id')->map(fn ($id) => (int) $id)->all();
        $semesterIds = $enrollments->pluck('semester_id')->map(fn ($id) => (int) $id)->all();

        $availableTasks = SubmissionTask::query()
            ->whereIn('course_id', $courseIds ?: [-1])
            ->whereIn('semester_id', $semesterIds ?: [-1])
            ->where('status', 'published')
            ->where('is_visible_to_students', true)
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

        return view('submissions.create', compact('enrollments', 'groups', 'task', 'availableTasks'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_task_id' => 'nullable|exists:submission_tasks,id',
            'course_id' => 'required|exists:courses,id',
            'type' => 'required|in:assignment,project,siwes,group,seminar',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'group_id' => 'nullable|exists:groups,id',
        ]);

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

        if ($course->department?->faculty?->university_id !== Auth::user()->university_id) {
            abort(403, 'The selected course is outside your institution.');
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
        // standalone upload endpoint (plan limit + allowed mimes).
        $maxFileSizeBytes = $this->subscriptionService->getUploadLimitForUser($request->user());
        $maxFileSizeKb = $maxFileSizeBytes / 1024;
        $allowedMimes = implode(',', SettingService::getAllowedExtensions());

        $request->validate([
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|mimes:'.$allowedMimes.'|max:'.$maxFileSizeKb,
        ]);

        $files = $request->file('files', []);
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
            'description' => $validated['description'] ?? null,
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

        return redirect()->route('submissions.show', $submission);
    }

    public function show(Submission $submission)
    {
        $this->authorize('view', $submission);

        $submission->load(['versions', 'comments.user', 'grade', 'course', 'group']);

        return view('submissions.show', compact('submission'));
    }

    public function edit(Submission $submission)
    {
        $this->authorize('update', $submission);

        return view('submissions.edit', compact('submission'));
    }

    public function update(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $submission->update($validated);

        return redirect()->route('submissions.show', $submission);
    }

    public function upload(Request $request, Submission $submission)
    {
        $this->authorize('update', $submission);

        // Get effective upload limit from user's plan
        $maxFileSizeBytes = $this->subscriptionService->getUploadLimitForUser($request->user());
        $maxFileSizeKb = $maxFileSizeBytes / 1024;
        $allowedMimes = implode(',', SettingService::getAllowedExtensions());

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

        if (! $submission->versions()->exists()) {
            return back()->with('error', 'Upload at least one file before submitting.');
        }
        if ($submission->versions()->whereHas('mediaAsset', fn ($query) => $query->whereNotIn('scan_status', ['clean', 'skipped']))->exists()) {
            return back()->with('error', 'A submission file has not passed security processing.');
        }
        if ($submission->task && ($submission->task->status !== 'published' || ! $submission->task->is_visible_to_students)) {
            return back()->with('error', 'This submission task is no longer accepting work.');
        }

        $status = $submission->status === 'correction_requested' ? 'resubmitted' : 'submitted';

        $submission->update([
            'status' => $status,
            'submitted_at' => now(),
        ]);

        // Fire submission confirmation to student
        event(new SubmissionConfirmation($submission, $submission->user));

        // Notify lecturer(s) of the course
        $lecturers = $submission->course->lecturerAssignments()->with('user')->get()->pluck('user')->filter();
        foreach ($lecturers as $lecturer) {
            event(new SubmissionSubmitted($submission, $submission->course, $lecturer));
        }

        // Trigger asynchronous AI analysis (validator + plagiarism) without blocking.
        event(new \App\Events\SubmissionAiAnalysisRequested($submission, $submission->user));

        return redirect()->route('submissions.show', $submission)
            ->with('success', 'Submission submitted successfully. AI analysis is running in the background.');
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
        $maxFileSizeBytes = $this->subscriptionService->getUploadLimitForUser($request->user());
        $maxFileSizeKb = $maxFileSizeBytes / 1024;
        $allowedMimes = implode(',', SettingService::getAllowedExtensions());

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

    public function lecturerIndex()
    {
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        // Get submissions for courses taught by this lecturer
        $submissions = Submission::whereHas('course.lecturerAssignments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with(['user', 'course', 'grade'])
            ->latest()
            ->paginate(20);

        return view('submissions.lecturer-index', compact('submissions'));
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

        $validated = $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $submission->update(['status' => 'graded']);
        $submission->grade()->updateOrCreate(
            ['submission_id' => $submission->id],
            ['user_id' => Auth::id(), 'score' => $validated['score'], 'feedback' => $validated['feedback'] ?? null]
        );

        return back()->with('success', 'Grade submitted.');
    }

    public function comment(Request $request, Submission $submission)
    {
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        $canComment = $submission->course->lecturerAssignments()->where('user_id', $user->id)->exists();
        if (! $canComment) {
            abort(403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $submission->comments()->create([
            'user_id' => $user->id,
            'comment' => $validated['comment'],
            'type' => 'feedback',
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function approve(Request $request, Submission $submission)
    {
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        $canApprove = $submission->course->lecturerAssignments()->where('user_id', $user->id)->exists();
        if (! $canApprove) {
            abort(403);
        }

        $submission->update(['status' => 'approved']);

        return back()->with('success', 'Submission approved.');
    }

    public function reject(Request $request, Submission $submission)
    {
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        $canApprove = $submission->course->lecturerAssignments()->where('user_id', $user->id)->exists();
        if (! $canApprove) {
            abort(403);
        }

        $submission->update(['status' => 'correction_requested']);

        return back()->with('success', 'Major corrections requested.');
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
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        $canRequest = $submission->course->lecturerAssignments()->where('user_id', $user->id)->exists();
        if (! $canRequest) {
            abort(403);
        }

        $submission->update(['status' => 'correction_requested']);

        return back()->with('success', 'Student notified to correct submission.');
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
