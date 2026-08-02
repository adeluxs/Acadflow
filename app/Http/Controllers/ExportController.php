<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\GeneratedDocument;
use App\Models\Semester;
use App\Models\Submission;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

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

    /**
     * Batch generate transcripts for a course/semester
     */
    public function batchTranscripts(Request $request)
    {
        $user = Auth::user();

        if (! $user->isAdmin() && ! $user->isLecturer()) {
            abort(403);
        }

        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $semester = \App\Models\Semester::findOrFail($request->semester_id);
        $students = $course->enrollments()
            ->where('semester_id', $semester->id)
            ->with('user')
            ->get()
            ->pluck('user');

        $zipFileName = 'transcripts_'.$course->code.'_'.$semester->name.'_'.now()->format('Ymd').'.zip';
        $zipPath = storage_path('app/temp/'.$zipFileName);

        if (! file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($students as $student) {
                $pdfContent = $this->pdfService->generateTranscript($student);
                $zip->addFromString('transcript_'.$student->student_id.'.pdf', $pdfContent);
            }
            $zip->close();
        }

        return Storage::download('temp/'.$zipFileName, $zipFileName);
    }

    /**
     * Batch generate grade reports for a course/semester
     */
    public function batchGradeReports(Request $request)
    {
        $user = Auth::user();

        if (! $user->isAdmin() && ! $user->isLecturer()) {
            abort(403);
        }

        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $course = \App\Models\Course::findOrFail($request->course_id);
        $semester = \App\Models\Semester::findOrFail($request->semester_id);
        $submissions = \App\Models\Submission::where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->whereNotNull('grade')
            ->with('user')
            ->get();

        $zipFileName = 'grade_reports_'.$course->code.'_'.$semester->name.'_'.now()->format('Ymd').'.zip';
        $zipPath = storage_path('app/temp/'.$zipFileName);

        if (! file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($submissions as $submission) {
                $pdfContent = $this->pdfService->generateGradeReport($submission);
                $zip->addFromString('grade_report_'.$submission->user->student_id.'_'.$submission->uuid.'.pdf', $pdfContent);
            }
            $zip->close();
        }

        return Storage::download('temp/'.$zipFileName, $zipFileName);
    }
}
