<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\GeneratedDocument;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\User;
use App\Services\PdfService;
use App\Services\Commerce\FeatureBillingService;
use App\Support\Errors\UserFacingError;
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

        return $this->withDocumentBilling($user, 'transcript', function () use ($user) {
            $pdfContent = $this->pdfService->generateTranscript($user);
            $filename = 'transcript_'.$user->student_id.'_'.now()->format('Y-m-d').'.pdf';
            return $this->pdfService->downloadResponse($pdfContent, $filename);
        });
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

        return $this->withDocumentBilling($user, 'grade-report:'.$submission->uuid, function () use ($submission) {
            $pdfContent = $this->pdfService->generateGradeReport($submission);
            $filename = 'grade_report_'.$submission->uuid.'.pdf';
            return $this->pdfService->downloadResponse($pdfContent, $filename);
        });
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

        return $this->withDocumentBilling($user, 'course-materials:'.$course->id, function () use ($course, $materials) {
            $pdfContent = $this->pdfService->generateCourseMaterialsPdf($course, $materials);
            $filename = 'materials_'.$course->code.'_'.now()->format('Y-m-d').'.pdf';
            return $this->pdfService->downloadResponse($pdfContent, $filename);
        });
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
        $enrollments = $course->enrollments()
            ->select(['id', 'user_id'])
            ->where('semester_id', $semester->id)
            ->with('user');

        return $this->withDocumentBilling($user, 'batch-transcripts:'.$course->id.':'.$semester->id, function () use ($course, $semester, $enrollments) {
            $zipFileName = 'transcripts_'.$course->code.'_'.$semester->name.'_'.now()->format('Ymd').'.zip';
            $zipPath = storage_path('app/temp/'.$zipFileName);

            if (! file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($enrollments->lazyById(100) as $enrollment) {
                    $student = $enrollment->user;
                    if (! $student) continue;
                    $pdfContent = $this->pdfService->generateTranscript($student);
                    $zip->addFromString('transcript_'.$student->student_id.'.pdf', $pdfContent);
                }
                $zip->close();
            }

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
        });
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
        $submissions = \App\Models\Submission::query()
            ->where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->whereHas('grade')
            ->with('user');

        return $this->withDocumentBilling($user, 'batch-grade-reports:'.$course->id.':'.$semester->id, function () use ($course, $semester, $submissions) {
            $zipFileName = 'grade_reports_'.$course->code.'_'.$semester->name.'_'.now()->format('Ymd').'.zip';
            $zipPath = storage_path('app/temp/'.$zipFileName);

            if (! file_exists(dirname($zipPath))) {
                mkdir(dirname($zipPath), 0755, true);
            }

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                foreach ($submissions->lazyById(100) as $submission) {
                    $pdfContent = $this->pdfService->generateGradeReport($submission);
                    $zip->addFromString('grade_report_'.$submission->user->student_id.'_'.$submission->uuid.'.pdf', $pdfContent);
                }
                $zip->close();
            }

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
        });
    }

    private function withDocumentBilling(User $user, string $operation, callable $callback): mixed
    {
        $billing = app(FeatureBillingService::class);
        $requestId = 'document:'.UserFacingError::requestId(request()).':'.$operation;
        $reservation = $billing->begin($user, 'document_generation', $requestId);

        try {
            $result = $callback();
            $billing->complete($user, $reservation, ['document_operation' => $operation]);
            return $result;
        } catch (\Throwable $exception) {
            $billing->release($user, $reservation, ['reason' => 'document_generation_failed', 'document_operation' => $operation]);
            throw $exception;
        }
    }

}
