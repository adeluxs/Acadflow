<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Submission;
use App\Services\DocumentGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentGenerationService $documents)
    {
    }

    public function templates(Request $request)
    {
        $user = $request->user();
        $query = DocumentTemplate::query()->where('is_active', true);

        if ($request->filled('department_id')) {
            $query->where(function ($builder) use ($request): void {
                $builder->whereNull('department_id')->orWhere('department_id', $request->integer('department_id'));
            });
        } elseif (! $user->isAdmin()) {
            $departmentId = $user->department_id;
            $query->where(fn ($builder) => $builder->whereNull('department_id')->when(
                $departmentId,
                fn ($nested) => $nested->orWhere('department_id', $departmentId)
            ));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return $query->orderByDesc('is_default')->orderBy('name')->paginate(20);
    }

    public function index(Request $request)
    {
        return GeneratedDocument::with('template')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);
    }

    public function show(Request $request, GeneratedDocument $document)
    {
        $this->assertCanAccess($request, $document);

        return $document->load('template');
    }

    public function download(Request $request, GeneratedDocument $document)
    {
        $this->assertCanAccess($request, $document);

        if ($document->status !== 'ready' || ! Storage::exists($document->file_path)) {
            return response()->json(['message' => 'Document file is not ready or no longer exists.'], 404);
        }

        return Storage::download($document->file_path, $this->downloadName($document));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:document_templates,id'],
            'submission_id' => ['required', 'integer', 'exists:submissions,id'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $submission = Submission::with('course.department')->findOrFail($validated['submission_id']);
        $template = DocumentTemplate::where('is_active', true)->findOrFail($validated['template_id']);
        $user = $request->user();

        abort_unless($submission->user_id === $user->id || $user->isAdmin(), 403);
        abort_unless(in_array($submission->status, ['approved', 'graded'], true), 422, 'Only approved or graded submissions can be exported.');
        abort_unless(
            $template->department_id === null || $template->department_id === $submission->course?->department_id,
            422,
            'The selected template does not belong to this submission department.'
        );

        $document = $this->documents->generate($submission, $template, $validated['title']);

        return response()->json($document, 201);
    }

    private function assertCanAccess(Request $request, GeneratedDocument $document): void
    {
        abort_unless($document->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
    }

    private function downloadName(GeneratedDocument $document): string
    {
        return str($document->title)->slug()->append('.pdf')->toString();
    }
}
