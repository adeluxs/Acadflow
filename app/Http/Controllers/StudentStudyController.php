<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Support\TextExtractor;
use App\Models\CourseMaterial;
use App\Models\StudentStudySession;
use App\Models\StudyNote;
use App\Services\Ai\PracticeQuizService;
use App\Services\FeatureAccessService;
use App\Services\Media\MediaSecurityService;
use App\Services\Student\StudySourceService;
use App\Services\Student\StudyWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StudentStudyController extends Controller
{
    public function __construct(private readonly StudyWorkspaceService $workspace, private readonly StudySourceService $sources) {}

    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'course_id' => ['nullable', 'integer', 'min:1']]);
        $user = $request->user();
        $courseQuery = $this->workspace->courses($user);
        $courses = (clone $courseQuery)->select(['id', 'uuid', 'code', 'name'])->orderBy('code')->simplePaginate(12, ['*'], 'courses_page')->withQueryString();
        $selectedCourse = $request->filled('course_id') ? (clone $courseQuery)->select(['id', 'uuid', 'code', 'name'])->findOrFail($request->integer('course_id')) : null;
        $materials = FeatureAccessService::canAccessFeature('course_materials', $user) ? CourseMaterial::query()
            ->select(['id', 'uuid', 'course_id', 'title', 'topic', 'type'])->where('is_visible', true)->whereIn('course_id', $courseQuery->select('courses.id'))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->when($search = trim((string) $request->input('q', '')), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('topic', 'like', '%'.$search.'%')))
            ->with('course:id,uuid,code,name')->latest('id')->simplePaginate(12, ['*'], 'materials_page')->withQueryString() : null;
        $notes = StudyNote::query()->where('user_id', $user->id)->whereIn('course_id', $this->workspace->courses($user)->select('courses.id'))
            ->select(['id', 'uuid', 'course_id', 'title'])->with('course:id,uuid,code')->latest('id')->simplePaginate(6, ['*'], 'notes_page')->withQueryString();
        $today = $this->workspace->today($user);

        return view('student.study.index', compact('courses', 'selectedCourse', 'materials', 'notes', 'today'));
    }

    public function startMaterial(Request $request, CourseMaterial $material)
    {
        $this->sources->authorizeMaterial($request->user(), $material);
        $session = StudentStudySession::firstOrCreate(['user_id' => $request->user()->id, 'material_id' => $material->id], ['course_id' => $material->course_id, 'last_studied_at' => now()]);

        return redirect()->route('study.show', $session);
    }

    public function startNote(Request $request, StudyNote $note)
    {
        abort_unless((int) $note->user_id === (int) $request->user()->id && $note->course, 404);
        $this->sources->authorizeCourse($request->user(), $note->course);
        $session = StudentStudySession::firstOrCreate(['user_id' => $request->user()->id, 'note_id' => $note->id], ['course_id' => $note->course_id, 'last_studied_at' => now()]);

        return redirect()->route('study.show', $session);
    }

    public function import(Request $request, MediaSecurityService $media, TextExtractor $extractor)
    {
        $data = $request->validate(['course_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:200'], 'content' => ['nullable', 'string', 'max:20000', 'required_without:file'], 'file' => ['nullable', 'file', 'mimes:pdf,docx,txt', 'max:5120', 'required_without:content']]);
        if ($request->hasFile('file') && $request->filled('content')) {
            throw ValidationException::withMessages(['file' => 'Choose either pasted text or a file for this note.']);
        }
        $course = $this->workspace->courses($request->user())->findOrFail($data['course_id']);
        $this->sources->authorizeCourse($request->user(), $course);
        $text = trim((string) ($data['content'] ?? ''));
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if (strtolower($file->getClientOriginalExtension()) === 'docx') {
                $zip = new \ZipArchive;
                if ($zip->open($file->getRealPath()) !== true) {
                    throw ValidationException::withMessages(['file' => 'This Word document could not be opened.']);
                }
                try {
                    if ($zip->numFiles > 2000) {
                        throw ValidationException::withMessages(['file' => 'This document has too many parts. Paste a shorter passage instead.']);
                    }
                    $size = 0;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $size += (int) ($zip->statIndex($i)['size'] ?? 0);
                        if ($size > 20 * 1024 * 1024) {
                            throw ValidationException::withMessages(['file' => 'This document is too large when unpacked. Paste a shorter passage instead.']);
                        }
                    }
                } finally {
                    $zip->close();
                }
            }
            $asset = $media->store($file, $request->user(), visibility: 'private', diskOverride: 'local');
            try {
                abort_unless(in_array($asset->scan_status, ['clean', 'skipped'], true), 423);
                $text = $this->sources->readable($extractor->fromPath($asset->path, $asset->disk));
            } finally {
                Storage::disk($asset->disk)->delete($asset->path);
                $asset->forceDelete();
            }
        }
        if (mb_strlen($text) < 20) {
            throw ValidationException::withMessages(['content' => 'Add at least a short paragraph of readable text. Scanned images need text extraction before importing.']);
        }
        $session = DB::transaction(function () use ($request, $course, $data, $text) {
            $note = StudyNote::create(['user_id' => $request->user()->id, 'course_id' => $course->id, 'title' => $data['title'], 'content' => '<p>'.nl2br(e(mb_substr($text, 0, 20000))).'</p>', 'source' => StudyNote::SOURCE_MANUAL]);

            return StudentStudySession::create(['user_id' => $request->user()->id, 'course_id' => $course->id, 'note_id' => $note->id, 'last_studied_at' => now()]);
        });

        return redirect()->route('study.show', $session)->with('success', 'Your private study note is ready.');
    }

    public function show(Request $request, StudentStudySession $session)
    {
        $text = $this->sources->sessionText($request->user(), $session);
        $quiz = $session->quizzes()->where('user_id', $request->user()->id)->where('status', 'ready')->select(['id', 'uuid', 'study_session_id', 'title', 'next_review_at', 'last_practiced_at'])->latest('id')->first();
        $sourceChanged = $session->source_fingerprint && ! hash_equals($session->source_fingerprint, hash('sha256', $text));

        return view('student.study.show', compact('session', 'text', 'quiz', 'sourceChanged'));
    }

    public function explain(Request $request, StudentStudySession $session)
    {
        $this->sources->authorizeSession($request->user(), $session);
        $data = $request->validate(['question' => ['required', 'string', 'min:5', 'max:2000'], 'selected_text' => ['nullable', 'string', 'max:2000']]);
        $result = $this->workspace->explain($request->user(), $session, $data['question'], $data['selected_text'] ?? '');
        if (! $result['success']) {
            return back()->withInput()->withErrors(['question' => $result['answer']]);
        }

        return redirect()->to(route('study.show', $session).'#explanation');
    }

    public function practice(Request $request, StudentStudySession $session, PracticeQuizService $quizzes)
    {
        $this->sources->authorizeSession($request->user(), $session);
        $quiz = $quizzes->generate($request->user(), $session->course, mb_substr($session->title(), 0, 200), $session->material, 5, 'mixed', $session);
        $session->update(['last_studied_at' => now()]);

        return redirect()->route('practice-quizzes.show', $quiz);
    }
}
