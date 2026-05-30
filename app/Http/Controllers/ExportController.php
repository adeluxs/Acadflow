<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Submission;
use App\Services\PdfService;
use Illuminate\Support\Facades\Auth;

class ExportController extends Controller
{
    public function __construct(private PdfService $pdfService) {}

    /**
     * Export student transcript as PDF
     */
    public function transcript()
    {
        $user = Auth::user();

        // Check if subscription allows document generation
        $subscription = $user->activeSubscription()->first();
        if ($subscription && $subscription->plan && ! $subscription->plan->allow_document_generation) {
            abort(403, 'Your subscription plan does not allow document generation.');
        }

        $pdfContent = $this->pdfService->generateTranscript($user);
        $filename = 'transcript_'.$user->student_id.'_'.now()->format('Y-m-d').'.pdf';

        return $this->pdfService->downloadResponse($pdfContent, $filename);
    }

    /**
     * Export grade report for a submission
     */
    public function gradeReport(Submission $submission)
    {
        $user = Auth::user();

        // Authorization: only student who owns submission or lecturer/Admin
        if ($submission->user_id !== $user->id && ! $user->isLecturer() && ! $user->isAdmin()) {
            abort(403);
        }

        $pdfContent = $this->pdfService->generateGradeReport($submission);
        $filename = 'grade_report_'.$submission->uuid.'.pdf';

        return $this->pdfService->downloadResponse($pdfContent, $filename);
    }

    /**
     * Export course materials index as PDF (lecturer only)
     */
    public function courseMaterials(Course $course)
    {
        $user = Auth::user();

        if (! $user->isLecturer() && ! $user->isAdmin()) {
            abort(403);
        }

        $materials = $course->materials()
            ->where('is_visible', true)
            ->with('uploader')
            ->orderBy('topic')
            ->orderBy('week_number')
            ->orderBy('sequence_order')
            ->get();

        $pdfContent = $this->pdfService->generateCourseMaterialsPdf($course, $materials);
        $filename = 'materials_'.$course->code.'_'.now()->format('Y-m-d').'.pdf';

        return $this->pdfService->downloadResponse($pdfContent, $filename);
    }

    /**
     * List user's generated documents
     */
    public function myDocuments()
    {
        $user = Auth::user();

        $documents = GeneratedDocument::where('user_id', $user->id)
            ->with(['submission', 'template'])
            ->latest()
            ->paginate(20);

        return view('documents.index', compact('documents'));
    }

    /**
     * Download generated document
     */
    public function downloadDocument(GeneratedDocument $document)
    {
        $user = Auth::user();

        if ($document->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403);
        }

        if (! Storage::exists($document->file_path)) {
            abort(404, 'Document not found');
        }

        return Storage::download($document->file_path, $document->title.'.pdf');
    }
}
