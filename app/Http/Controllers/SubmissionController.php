<?php

namespace App\Http\Controllers;

use App\Events\SubmissionConfirmation;
use App\Events\SubmissionSubmitted;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\SubmissionVersion;
use App\Services\PdfService;
use App\Services\SettingService;
use App\Services\SubscriptionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService)
    {
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

        $enrollments = Enrollment::where('user_id', Auth::id())
            ->where('status', 'enrolled')
            ->with('course')
            ->get();

        $groups = Group::whereHas('members', function ($query) {
            $query->where('user_id', Auth::id());
        })
            ->with('course')
            ->get();

        // Load available submission tasks if task_id is provided
        $taskId = $request->query('task_id');
        $task = null;
        if ($taskId) {
            $task = SubmissionTask::where('id', $taskId)
                ->where('status', 'published')
                ->where('is_visible_to_students', true)
                ->first();
        }

        return view('submissions.create', compact('enrollments', 'groups', 'task'));
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

        // If submission_task_id is provided, verify it belongs to the course
        if ($validated['submission_task_id']) {
            $task = SubmissionTask::findOrFail($validated['submission_task_id']);
            if ($task->course_id != $validated['course_id']) {
                return back()->withInput()->with('error', 'Selected assignment does not belong to the chosen course.');
            }
            if ($task->status !== 'published') {
                return back()->withInput()->with('error', 'This assignment is not available for submissions.');
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

        $semester = Semester::where('is_active', true)->first();

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

        $submission = Submission::create($submissionData);

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

        $submission->versions()->where('is_current', true)->update(['is_current' => false]);

        $studentId = Auth::user()->student_id ?? 'user'.Auth::id();
        $currentVersion = $submission->version;

        foreach ($files as $file) {
            $extension = $file->getClientOriginalExtension();
            $safeType = Str::slug($submission->type);
            $safeDate = now()->format('YmdHis');
            $generatedName = "{$studentId}_{$safeType}_{$safeDate}.{$extension}";
            $path = $file->storeAs('submissions/'.$submission->uuid, $generatedName);

            SubmissionVersion::create([
                'submission_id' => $submission->id,
                'version_number' => $currentVersion,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => Auth::id(),
                'is_current' => true,
            ]);

            $currentVersion++;
        }

        $submission->version = $currentVersion;
        $submission->save();

        return back()->with('success', 'Files uploaded successfully.');
    }

    public function submit(Request $request, Submission $submission)
    {
        $this->authorize('submit', $submission);

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

        return redirect()->route('submissions.show', $submission)
            ->with('success', 'Submission submitted successfully.');
    }

    public function download(SubmissionVersion $version)
    {
        $submission = $version->submission;

        if (! auth()->user()->can('view', $submission)) {
            abort(403);
        }

        return Storage::download($version->file_path, $version->file_name);
    }

    public function destroy(Submission $submission)
    {
        $this->authorizeAction('delete', $submission);

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
        $this->authorizeAction('view', $submission);

        if ($versionId) {
            $version = $submission->versions()->where('id', $versionId)->firstOrFail();
        } else {
            $version = $submission->versions()->where('is_current', true)->firstOrFail();
        }

        if (! Storage::exists($version->file_path)) {
            abort(404, 'File not found');
        }

        $mime = $version->mime_type;

        // For PDFs, display inline
        if (str_contains($mime, 'pdf')) {
            return response(Storage::get($version->file_path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$version->file_name.'"',
            ]);
        }

        // For images, display inline
        if (str_contains($mime, 'image')) {
            return response(Storage::get($version->file_path), 200, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$version->file_name.'"',
            ]);
        }

        // For other files, force download
        return Storage::download($version->file_path, $version->file_name);
    }

    /**
     * Schedule defense for a submission (project/seminar)
     */
    public function scheduleDefense(Request $request, Submission $submission)
    {
        $this->authorizeAction('view', $submission);

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
        $this->authorizeAction('view', $submission);

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
        $this->authorizeAction('view', $submission);

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
            return response()->download(storage_path('app/'.$existingDoc->file_path), $existingDoc->title.'.pdf');
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
        Storage::put($filePath, $pdf->output());

        $doc = GeneratedDocument::create([
            'user_id' => $submission->user_id,
            'submission_id' => $submission->id,
            'template_id' => $templateId,
            'title' => $submission->title,
            'file_path' => $filePath,
            'file_size' => Storage::size($filePath),
            'status' => 'completed',
        ]);

        return response()->download(storage_path('app/'.$filePath), $fileName);
    }

    /**
     * Replace files in a submission (before deadline)
     */
    public function replaceFiles(Request $request, Submission $submission)
    {
        $this->authorizeAction('update', $submission);

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

        // Mark old versions as not current
        $submission->versions()->where('is_current', true)->update(['is_current' => false]);

        $studentId = Auth::user()->student_id ?? 'user'.Auth::id();
        $currentVersion = $submission->version;

        foreach ($request->file('files') as $file) {
            $extension = $file->getClientOriginalExtension();
            $safeType = Str::slug($submission->type);
            $safeDate = now()->format('YmdHis');
            $generatedName = $studentId.'_'.$safeType.'_'.$safeDate.'.'.$extension;
            $path = $file->storeAs('submissions/'.$submission->uuid, $generatedName);

            SubmissionVersion::create([
                'submission_id' => $submission->id,
                'version_number' => $currentVersion,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => Auth::id(),
                'is_current' => true,
            ]);

            $currentVersion++;
        }

        $submission->version = $currentVersion;
        $submission->save();

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
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        if (in_array($submission->status, ['submitted', 'resubmitted'])) {
            $submission->update(['status' => 'under_review']);
        }

        $submission->load(['user', 'course', 'versions', 'comments.user', 'grade']);

        $canGrade = $submission->course->lecturerAssignments()->where('user_id', $user->id)->exists();
        if (! $canGrade) {
            abort(403);
        }

        return view('submissions.review', compact('submission'));
    }

    public function grade(Request $request, Submission $submission)
    {
        $user = Auth::user();
        if (! $user->isLecturer()) {
            abort(403);
        }

        $canGrade = $submission->course->lecturerAssignments()->where('user_id', $user->id)->exists();
        if (! $canGrade) {
            abort(403);
        }

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

        $submission->update(['status' => 'rejected']);

        return back()->with('success', 'Submission rejected.');
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

        $submission->update(['status' => 'correction_required']);

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

    protected function authorizeAction($action, $submission)
    {
        $user = Auth::user();

        if ($action === 'view') {
            if ($submission->user_id === $user->id) {
                return;
            }

            if ($submission->group_id && $submission->group && $submission->group->members()->where('user_id', $user->id)->exists()) {
                return;
            }

            if (! $user->isLecturer()) {
                abort(403);
            }

            return;
        }

        if (in_array($action, ['update', 'submit'])) {
            if ($submission->user_id === $user->id) {
                if (in_array($submission->status, ['draft', 'correction_requested'])) {
                    return;
                }
            }

            if ($submission->group_id && $submission->group && $submission->group->members()->where('user_id', $user->id)->exists()) {
                if (in_array($submission->status, ['draft', 'correction_requested'])) {
                    return;
                }
            }

            abort(403);
        }

        if ($action === 'delete' && $submission->user_id === $user->id && $submission->status === 'draft') {
            return;
        }

        abort(403);
    }
}
