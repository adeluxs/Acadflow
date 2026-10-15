<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\MaterialAnnotation;
use App\Services\Student\AnnotationSummaryService;
use Illuminate\Http\Request;

/**
 * Student highlights and margin notes on a material, with an AI revision summary.
 */
class MaterialAnnotationController extends Controller
{
    public function __construct(private readonly AnnotationSummaryService $summaries) {}

    public function store(Request $request, Course $course, CourseMaterial $material)
    {
        abort_unless((int) $material->course_id === (int) $course->id, 404);
        $this->authorize('view', $material);

        $data = $request->validate([
            'quote' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        if (trim((string) ($data['quote'] ?? '')) === '' && trim((string) ($data['note'] ?? '')) === '') {
            return back()->withErrors(['note' => 'Add a highlight or a note.']);
        }

        MaterialAnnotation::query()->create([
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
            'material_id' => $material->id,
            'quote' => $data['quote'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Highlight saved.');
    }

    public function destroy(Request $request, MaterialAnnotation $annotation)
    {
        abort_unless($annotation->user_id === $request->user()->id, 404);

        $annotation->delete();

        return back()->with('success', 'Highlight removed.');
    }

    public function summarize(Request $request, Course $course, CourseMaterial $material)
    {
        abort_unless((int) $material->course_id === (int) $course->id, 404);
        $this->authorize('view', $material);

        $annotations = MaterialAnnotation::query()
            ->where('user_id', $request->user()->id)
            ->where('material_id', $material->id)
            ->get();

        $summary = $this->summaries->summarize($request->user(), $material, $annotations);

        return response()->json([
            'success' => $summary !== null,
            'summary' => $summary ?? 'Add some highlights first, then generate a summary.',
        ]);
    }
}
