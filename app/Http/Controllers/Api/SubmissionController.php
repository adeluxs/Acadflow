<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Submission;
use App\Services\AcademicContextService;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionController extends Controller
{
    use ScopesTenantData;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = $this->scopeCourseQuery(Submission::query()->with(['course', 'versions', 'comments.user']), $user, 'course');
        if ($user->isStudent()) {
            $query->where(fn ($scope) => $scope->where('user_id', $user->id)->orWhereHas('group.members', fn ($members) => $members->where('users.id', $user->id)));
        } elseif ($user->isLecturer()) {
            $query->whereHas('course.lecturerAssignments', fn ($scope) => $scope->where('user_id', $user->id));
        } elseif (! $user->isAdmin()) {
            abort(403);
        }
        $query->when($request->filled('course_id'), fn ($scope) => $scope->where('course_id', $request->integer('course_id')))
            ->when($request->filled('status'), fn ($scope) => $scope->where('status', $request->string('status')->toString()));
        return $query->latest()->paginate(20);
    }

    public function store(Request $request, AcademicContextService $academicContext)
    {
        $this->authorize('create', Submission::class);
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id', 'submission_task_id' => 'nullable|exists:submission_tasks,id',
            'title' => 'required|string|max:255', 'type' => 'required|in:assignment,project,siwes,group,seminar',
            'description' => 'nullable|string', 'content' => 'nullable|string', 'semester_id' => 'nullable|exists:semesters,id',
        ]);
        $course = Course::with('department.faculty')->findOrFail($validated['course_id']);
        $this->assertCourseTenant($request->user(), $course);
        $semester = $request->filled('semester_id') ? Semester::with('academicSession')->find($validated['semester_id']) : $academicContext->activeSemesterForCourse($course);
        if (! $semester) return response()->json(['message' => 'No active semester found.'], 422);
        abort_unless($semester->academicSession?->university_id === $course->department?->faculty?->university_id, 422, 'Semester and course institutions do not match.');
        abort_unless(Enrollment::where('user_id', Auth::id())->where('course_id', $course->id)->where('semester_id', $semester->id)->where('status', 'enrolled')->exists(), 403, 'You must be enrolled in the selected course.');
        if ($validated['submission_task_id'] ?? null) abort_unless($course->submissionTasks()->whereKey($validated['submission_task_id'])->where('type', $validated['type'])->exists(), 422, 'Submission task does not match the course or type.');

        $submission = Submission::create([
            'uuid' => (string) Str::uuid(), 'user_id' => Auth::id(), 'course_id' => $course->id, 'semester_id' => $semester->id,
            'submission_task_id' => $validated['submission_task_id'] ?? null, 'status' => 'draft', 'title' => $validated['title'],
            'type' => $validated['type'], 'description' => $validated['description'] ?? $validated['content'] ?? null,
        ]);
        return response()->json($submission, 201);
    }

    public function show(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('view', $submission);
        return $submission->load(['course', 'versions', 'comments.user', 'grade']);
    }

    public function update(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('update', $submission);
        $validated = $request->validate(['title' => 'sometimes|string|max:255', 'description' => 'nullable|string', 'content' => 'nullable|string']);
        $submission->update(['title' => $validated['title'] ?? $submission->title, 'description' => $validated['description'] ?? $validated['content'] ?? $submission->description]);
        return response()->json($submission);
    }

    public function destroy(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('delete', $submission);
        $submission->delete();
        return response()->json(['message' => 'Submission deleted successfully.']);
    }

    public function download(Request $request, Submission $submission, SafeFileDeliveryService $files)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('view', $submission);
        $version = $submission->versions()->where('is_current', true)->first();
        abort_unless($version, 404, 'No current file found.');
        $version->loadMissing('mediaAsset');
        if ($version->mediaAsset) abort_unless(in_array($version->mediaAsset->scan_status, ['clean', 'skipped'], true), 423, 'File security processing is incomplete or failed.');
        return $files->stream($version->disk ?: (string) config('filesystems.default', 'local'), $version->file_path, $version->file_name, $version->mime_type, 'attachment');
    }

    public function upload(Request $request, Submission $submission, MediaSecurityService $media)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('update', $submission);
        $maxFileSizeKb = (int) (SettingService::getMaxUploadSize() / 1024);
        $request->validate(['file' => ['required', 'file', 'max:'.$maxFileSizeKb]]);
        abort_unless(in_array($submission->status, ['draft', 'correction_requested'], true), 422, 'Files can only be uploaded to an editable submission.');
        $versionCount = $submission->versions()->count();
        abort_if($versionCount >= 10, 422, 'Maximum of 10 submission versions reached.');
        $asset = $media->store($request->file('file'), $request->user(), $submission, 'private', ['purpose' => 'submission_version', 'submission_uuid' => $submission->uuid]);
        abort_unless(in_array($asset->scan_status, ['clean', 'skipped'], true), 422, 'The uploaded file failed the configured security scan.');
        $submission->versions()->update(['is_current' => false]);
        $version = $submission->versions()->create([
            'version_number' => $versionCount + 1, 'file_name' => $asset->original_name, 'file_path' => $asset->path,
            'disk' => $asset->disk, 'media_asset_id' => $asset->id, 'file_size' => $asset->size_bytes, 'mime_type' => $asset->mime_type, 'uploaded_by' => $request->user()->id, 'is_current' => true,
        ]);
        return response()->json(['message' => 'File uploaded and scanned.', 'version' => $version, 'scan_status' => $asset->scan_status], 201);
    }

    public function submit(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('submit', $submission);
        abort_unless($submission->versions()->exists(), 422, 'Submission must include at least one uploaded file.');
        abort_if($submission->versions()->whereHas('mediaAsset', fn ($query) => $query->whereNotIn('scan_status', ['clean', 'skipped']))->exists(), 423, 'A submission file has not passed security processing.');
        abort_unless($request->user()->hasPaidCurrentSemester(), 403, 'Payment is required before submitting work.');
        $deadline = $submission->getEffectiveDeadline();
        $submission->update(['status' => $submission->status === 'correction_requested' ? 'resubmitted' : 'submitted', 'submitted_at' => now(), 'is_late' => $deadline ? now()->greaterThan($deadline) : false]);
        return response()->json(['message' => 'Submitted successfully']);
    }

    public function versions(Request $request, Submission $submission) { $this->assertSubmissionTenant($request, $submission); $this->authorize('view', $submission); return $submission->versions()->latest('version_number')->paginate(20); }
    public function comments(Request $request, Submission $submission) { $this->assertSubmissionTenant($request, $submission); $this->authorize('view', $submission); return $submission->comments()->with('user')->latest()->paginate(50); }

    public function addComment(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('comment', $submission);
        $validated = $request->validate(['content' => 'required|string|max:20000', 'type' => 'nullable|in:general,correction,suggestion', 'parent_id' => 'nullable|exists:submission_comments,id']);
        if ($validated['parent_id'] ?? null) abort_unless($submission->comments()->whereKey($validated['parent_id'])->exists(), 422);
        $comment = $submission->comments()->create($validated + ['user_id' => $request->user()->id, 'status' => 'pending']);
        return response()->json($comment, 201);
    }

    public function grade(Request $request, Submission $submission) { $this->assertSubmissionTenant($request, $submission); $this->authorize('view', $submission); return response()->json($submission->grade()->first()); }

    public function submitGrade(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('grade', $submission);
        abort_unless($submission->status === 'approved', 422, 'Only approved submissions may be graded.');
        $validated = $request->validate(['score' => 'required|numeric|min:0|max:100', 'feedback' => 'nullable|string|max:20000', 'rubric_id' => 'nullable|exists:submission_rubrics,id']);
        if ($validated['rubric_id'] ?? null) abort_unless($submission->course->submissionRubrics()->whereKey($validated['rubric_id'])->exists(), 422);
        $grade = $submission->grade()->updateOrCreate([], ['score' => $validated['score'], 'max_score' => 100, 'feedback' => $validated['feedback'] ?? null, 'rubric_id' => $validated['rubric_id'] ?? null, 'user_id' => $request->user()->id, 'is_final' => true]);
        $submission->update(['status' => 'graded', 'graded_at' => now()]);
        return response()->json($grade);
    }

    public function approve(Request $request, Submission $submission) { $this->assertSubmissionTenant($request, $submission); $this->authorize('approve', $submission); $submission->update(['status' => 'approved']); return response()->json(['message' => 'Approved']); }

    public function reject(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('requestCorrection', $submission);
        $validated = $request->validate(['reason' => 'required|string|max:20000']);
        $submission->update(['status' => 'correction_requested']);
        $submission->comments()->create(['content' => 'Major revision required: '.$validated['reason'], 'user_id' => $request->user()->id, 'type' => 'correction', 'status' => 'pending']);
        return response()->json(['message' => 'Major revision requested.']);
    }

    public function requestCorrection(Request $request, Submission $submission)
    {
        $this->assertSubmissionTenant($request, $submission); $this->authorize('requestCorrection', $submission);
        $validated = $request->validate(['correction_notes' => 'required|string|max:20000']);
        $submission->update(['status' => 'correction_requested']);
        $submission->comments()->create(['content' => 'Correction requested: '.$validated['correction_notes'], 'user_id' => $request->user()->id, 'type' => 'correction', 'status' => 'pending']);
        return response()->json(['message' => 'Correction requested']);
    }

    public function report(Request $request)
    {
        $query = $this->authorizedReportingQuery($request);
        $query->when($request->filled('status'), fn ($scope) => $scope->where('status', $request->string('status')->toString()));
        return $query->selectRaw('status, COUNT(*) as count')->groupBy('status')->get();
    }

    public function export(Request $request)
    {
        return $this->authorizedReportingQuery($request)->with(['course', 'user', 'grade'])->whereIn('status', ['approved','graded'])->paginate(500);
    }

    public function analytics(Request $request)
    {
        $query = $this->authorizedReportingQuery($request);
        return ['total' => (clone $query)->count(), 'pending' => (clone $query)->whereIn('status', ['submitted','resubmitted','under_review'])->count(), 'graded' => (clone $query)->where('status', 'graded')->count(), 'approved' => (clone $query)->where('status', 'approved')->count()];
    }

    private function authorizedReportingQuery(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLecturer(), 403);
        $query = $this->scopeCourseQuery(Submission::query(), $request->user(), 'course');
        if ($request->user()->isLecturer()) $query->whereHas('course.lecturerAssignments', fn ($scope) => $scope->where('user_id', $request->user()->id));
        return $query;
    }

    private function assertSubmissionTenant(Request $request, Submission $submission): void
    {
        $submission->loadMissing('course.department.faculty');
        $this->assertCourseTenant($request->user(), $submission->course);
    }
}
