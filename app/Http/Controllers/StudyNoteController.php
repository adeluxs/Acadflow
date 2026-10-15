<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\StudyNote;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;

/**
 * Student-owned study notes, optionally saved from an AI answer.
 */
class StudyNoteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notes = StudyNote::query()
            ->where('user_id', $user->id)
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->with('course:id,uuid,code,name')
            ->latest('id')
            ->paginate(20);

        $courses = Course::query()
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $user->id)->where('status', 'enrolled'))
            ->orderBy('code')
            ->get(['id', 'uuid', 'code', 'name']);

        return view('student.study-notes', compact('notes', 'courses'));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:200000'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'source' => ['nullable', 'in:manual,ai_answer,material_summary'],
        ]);

        $courseId = isset($data['course_id']) ? (int) $data['course_id'] : null;
        if ($courseId) {
            $course = Course::with('department.faculty')->findOrFail($courseId);
            abort_unless($user->canAccessCourse($course), 403, 'You do not have access to that course.');
        }

        $note = StudyNote::query()->create([
            'user_id' => $user->id,
            'course_id' => $courseId,
            'title' => $data['title'],
            'content' => HtmlSanitizer::clean($this->toHtml($data['content'])),
            'source' => $data['source'] ?? StudyNote::SOURCE_MANUAL,
        ]);

        AuditLog::log('study_note_created', $user->id, StudyNote::class, $note->id, null, ['source' => $note->source], $request->ip(), $request->userAgent());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'note_id' => $note->uuid, 'message' => 'Note saved.']);
        }

        return back()->with('success', 'Note saved.');
    }

    public function destroy(Request $request, StudyNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 404);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    private function toHtml(string $content): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($content)) ?: [];
        $html = collect($paragraphs)
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph))).'</p>')
            ->implode("\n");

        return $html;
    }
}
