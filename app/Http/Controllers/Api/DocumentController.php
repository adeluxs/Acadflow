<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function templates(Request $request)
    {
        $query = DocumentTemplate::query()->where('is_active', true);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return $query->paginate(20);
    }

    public function index(Request $request)
    {
        return GeneratedDocument::with('template')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    public function show(GeneratedDocument $document)
    {
        if ($document->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return $document->load('template');
    }

    public function download(GeneratedDocument $document)
    {
        if ($document->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (! Storage::exists($document->file_path)) {
            return response()->json(['message' => 'Document file not found'], 404);
        }

        return Storage::download($document->file_path, $document->title ?? basename($document->file_path));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:document_templates,id',
            'submission_id' => 'required|exists:submissions,id',
            'title' => 'required|string|max:255',
        ]);

        $submission = Submission::findOrFail($validated['submission_id']);

        if ($submission->user_id !== Auth::id() && ! Auth::user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $path = 'generated_documents/'.Str::uuid().'.pdf';
        Storage::put($path, 'Generated document placeholder for '.$validated['title']);

        $document = GeneratedDocument::create([
            'uuid' => Str::uuid(),
            'user_id' => Auth::id(),
            'submission_id' => $submission->id,
            'template_id' => $validated['template_id'],
            'title' => $validated['title'],
            'file_path' => $path,
            'file_size' => Storage::size($path),
            'status' => 'generated',
        ]);

        return response()->json($document, 201);
    }
}
