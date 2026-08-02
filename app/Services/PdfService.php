<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    /**
     * Generate transcript for student
     */
    public function generateTranscript(User $student, array $options = []): string
    {
        $submissions = $student->submissions()
            ->with(['course', 'task'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = [
            'student' => $student,
            'submissions' => $submissions,
            'generated_at' => now(),
            'options' => $options,
        ];

        $pdf = Pdf::loadView('pdfs.transcript', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate grade report for a submission
     */
    public function generateGradeReport(Submission $submission): string
    {
        $submission->load(['user', 'course', 'task', 'versions', 'attachments']);

        $data = [
            'submission' => $submission,
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('pdfs.grade-report', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate course materials index
     */
    public function generateCourseMaterialsPdf(Course $course, $materials): string
    {
        $data = [
            'course' => $course,
            'materials' => $materials,
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('pdfs.course-materials', $data);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->output();
    }

    /**
     * Generate course grades PDF
     */
    public function generateCourseGradesPdf(Course $course, $submissions): string
    {
        $data = [
            'course' => $course,
            'submissions' => $submissions,
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('pdfs.course-grades', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Download PDF response
     */
    public function downloadResponse(string $pdfContent, string $filename)
    {
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Stream PDF in browser
     */
    public function streamResponse(string $pdfContent, string $filename)
    {
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
