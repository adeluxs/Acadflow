<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Submission::with(['course', 'versions', 'comments.user']);

        if ($user->role === 'student') {
            $query->where('user_id', $user->id);
        } elseif ($user->role === 'lecturer') {
            $query->whereHas('course.lecturerAssignments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });

            if ($request->has('course_id')) {
                $query->where('course_id', $request->course_id);
            }
        }

        return $query->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:assignment,project,siwes,group,seminar',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'semester_id' => 'nullable|exists:semesters,id',
        ]);

        $semester = $request->filled('semester_id')
            ? Semester::find($validated['semester_id'])
            : Semester::where('is_active', true)->first();

        if (! $semester) {
            return response()->json(['message' => 'No active semester found.'], 422);
        }

        if (! Enrollment::where('user_id', Auth::id())
            ->where('course_id', $validated['course_id'])
            ->where('semester_id', $semester->id)
            ->where('status', 'enrolled')
            ->exists()) {
            return response()->json(['message' => 'You must be enrolled in the selected course to create a submission.'], 403);
        }

        $submission = Submission::create([
            'uuid' => app('Illuminate\Support\Str')->uuid(),
            'user_id' => Auth::id(),
            'course_id' => $validated['course_id'],
            'semester_id' => $semester->id,
            'status' => 'draft',
            'title' => $validated['title'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? $validated['content'] ?? null,
        ]);

        return response()->json($submission, 201);
    }

    public function show(Submission $submission)
    {
        return $submission->load(['course', 'versions', 'comments.user', 'grade']);
    }

    public function update(Request $request, Submission $submission)
    {
        if ($submission->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! in_array($submission->status, ['draft', 'correction_requested'])) {
            return response()->json(['message' => 'Only draft or correction requested submissions can be updated.'], 422);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'type' => 'in:assignment,project,siwes,group,seminar',
        ]);

        $submission->update([
            'title' => $validated['title'] ?? $submission->title,
            'type' => $validated['type'] ?? $submission->type,
            'description' => $validated['description'] ?? $validated['content'] ?? $submission->description,
        ]);

        return response()->json($submission);
    }

    public function destroy(Submission $submission)
    {
        if ($submission->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($submission->status !== 'draft') {
            return response()->json(['message' => 'Only draft submissions can be deleted.'], 403);
        }

        $submission->delete();

        return response()->json(['message' => 'Submission deleted successfully.']);
    }

    public function download(Submission $submission)
    {
        $version = $submission->versions()->where('is_current', true)->first();

        if (! $version) {
            return response()->json(['message' => 'No file found for this submission.'], 404);
        }

        return Storage::download($version->file_path, $version->file_name);
    }

    public function upload(Request $request, Submission $submission)
    {
        $allowedMimes = implode(',', \App\Services\SettingService::getAllowedExtensions());
        $maxFileSizeKb = (int) (\App\Services\SettingService::getMaxUploadSize() / 1024);

        $request->validate([
            'file' => 'required|file|mimes:' . $allowedMimes . '|max:' . $maxFileSizeKb,
        ]);

        if ($submission->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! in_array($submission->status, ['draft', 'correction_requested'])) {
            return response()->json(['message' => 'Files can only be uploaded to draft or correction requested submissions.'], 422);
        }

        $versionCount = $submission->versions()->count();
        if ($versionCount >= 10) {
            return response()->json(['message' => 'Maximum of 10 submission versions reached.'], 422);
        }

        $file = $request->file('file');
        $safeName = sprintf(
            '%s_%s_%s.%s',
            Auth::user()->student_id ?? Auth::id(),
            str_replace(' ', '_', $submission->type),
            now()->format('YmdHis'),
            $file->getClientOriginalExtension()
        );

        $path = $file->storeAs('submissions/'.$submission->uuid, $safeName);

        $submission->versions()->update(['is_current' => false]);

        $version = $submission->versions()->create([
            'submission_id' => $submission->id,
            'version_number' => $versionCount + 1,
            'file_name' => $safeName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'uploaded_by' => Auth::id(),
            'is_current' => true,
        ]);

        return response()->json(['message' => 'File uploaded', 'version' => $version], 201);
    }

    public function submit(Request $request, Submission $submission)
    {
        if ($submission->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! in_array($submission->status, ['draft', 'correction_requested'])) {
            return response()->json(['message' => 'Only draft or correction requested submissions can be submitted.'], 422);
        }

        if (! $submission->versions()->exists()) {
            return response()->json(['message' => 'Submission must include at least one uploaded file before sending.'], 422);
        }

        if (! Auth::user()->hasPaidCurrentSemester()) {
            return response()->json(['message' => 'Payment is required before submitting work.'], 403);
        }

        $submission->update(['status' => 'submitted', 'submitted_at' => now()]);

        return response()->json(['message' => 'Submitted successfully']);
    }

    public function versions(Submission $submission)
    {
        return $submission->versions()->orderBy('version_number', 'desc')->get();
    }

    public function comments(Submission $submission)
    {
        return $submission->comments()->with('user')->get();
    }

    public function addComment(Request $request, Submission $submission)
    {
        $validated = $request->validate(['content' => 'required|string']);

        $comment = $submission->comments()->create([
            'content' => $validated['content'],
            'user_id' => Auth::id(),
        ]);

        return response()->json($comment, 201);
    }

    public function grade(Submission $submission)
    {
        return response()->json($submission->grade()->first());
    }

    public function submitGrade(Request $request, Submission $submission)
    {
        if (! Auth::user()->canGradeSubmission($submission)) {
            return response()->json(['message' => 'Unauthorized to grade this submission'], 403);
        }

        if ($submission->status !== 'approved') {
            return response()->json(['message' => 'Only approved submissions may be graded.'], 422);
        }

        $validated = $request->validate([
            'score' => 'required|integer|min:0|max:100',
            'feedback' => 'nullable|string',
            'rubric_id' => 'nullable|exists:submission_rubrics,id',
        ]);

        $grade = $submission->grade()->updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'score' => $validated['score'],
                'feedback' => $validated['feedback'] ?? null,
                'rubric_id' => $validated['rubric_id'] ?? null,
                'user_id' => Auth::id(),
                'graded_at' => now(),
                'is_final' => true,
            ]
        );

        $submission->update(['status' => 'graded', 'graded_at' => now()]);

        return response()->json($grade);
    }

    public function approve(Submission $submission)
    {
        if (! Auth::user()->canGradeSubmission($submission)) {
            return response()->json(['message' => 'Unauthorized to approve this submission'], 403);
        }

        $submission->update(['status' => 'approved']);

        return response()->json(['message' => 'Approved']);
    }

    public function reject(Request $request, Submission $submission)
    {
        if (! Auth::user()->canGradeSubmission($submission)) {
            return response()->json(['message' => 'Unauthorized to reject this submission'], 403);
        }

        $validated = $request->validate(['reason' => 'required|string']);

        $submission->update(['status' => 'rejected']);

        $submission->comments()->create([
            'content' => 'Rejected: '.$validated['reason'],
            'user_id' => Auth::id(),
            'type' => 'general',
            'status' => 'resolved',
        ]);

        return response()->json(['message' => 'Rejected']);
    }

    public function requestCorrection(Request $request, Submission $submission)
    {
        if (! Auth::user()->canGradeSubmission($submission)) {
            return response()->json(['message' => 'Unauthorized to request correction for this submission'], 403);
        }

        $validated = $request->validate(['correction_notes' => 'required|string']);

        $submission->update(['status' => 'correction_requested']);

        $submission->comments()->create([
            'content' => 'Correction requested: '.$validated['correction_notes'],
            'user_id' => Auth::id(),
            'type' => 'correction',
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Correction requested']);
    }

    public function report(Request $request)
    {
        $query = Submission::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $query->selectRaw('status, COUNT(*) as count')->groupBy('status')->get();
    }

    public function export(Request $request)
    {
        $submissions = Submission::with(['course', 'student', 'grade'])
            ->where('status', 'approved')
            ->get();

        return response()->json($submissions);
    }

    public function analytics()
    {
        return [
            'total' => Submission::count(),
            'pending' => Submission::where('status', 'submitted')->count(),
            'graded' => Submission::where('status', 'graded')->count(),
            'approved' => Submission::where('status', 'approved')->count(),
        ];
    }
}
