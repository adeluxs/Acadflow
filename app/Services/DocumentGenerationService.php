<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentGenerationService
{
    public function generate(Submission $submission, DocumentTemplate $template, string $title): GeneratedDocument
    {
        $submission->loadMissing([
            'user',
            'course.department.faculty.university',
            'semester',
            'task',
            'grade',
            'versions' => fn ($query) => $query->where('is_current', true),
        ]);

        $document = GeneratedDocument::create([
            'user_id' => $submission->user_id,
            'submission_id' => $submission->id,
            'template_id' => $template->id,
            'title' => $title,
            'file_path' => 'pending',
            'file_size' => 0,
            'status' => 'processing',
        ]);

        try {
            $pdf = $this->renderPdf($submission, $template, $title);
            $path = 'generated-documents/'.$submission->uuid.'/'.$document->uuid.'.pdf';
            Storage::put($path, $pdf);

            if (! Storage::exists($path)) {
                throw new RuntimeException('The generated PDF could not be persisted.');
            }

            $document->update([
                'file_path' => $path,
                'file_size' => strlen($pdf),
                'status' => 'ready',
            ]);
        } catch (\Throwable $exception) {
            $document->update(['status' => 'failed']);
            report($exception);
            throw $exception;
        }

        return $document->fresh('template');
    }

    private function renderPdf(Submission $submission, DocumentTemplate $template, string $title): string
    {
        $data = [
            'submission' => $submission,
            'template' => $template,
            'title' => $title,
            'generated_at' => now(),
            'document_type' => $template->type,
        ];

        $templateHtml = $this->trustedTemplateHtml($template);
        $pdf = $templateHtml !== null
            ? Pdf::loadHtml($this->replacePlaceholders($templateHtml, $data))
            : Pdf::loadView($this->fallbackView($template->type), $data);

        return $pdf->setPaper('a4', 'portrait')->output();
    }

    private function trustedTemplateHtml(DocumentTemplate $template): ?string
    {
        $path = trim((string) $template->template_path);

        if ($path === '' || ! Storage::exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['html', 'htm'], true)) {
            return null;
        }

        return Storage::get($path);
    }

    /**
     * Templates are administrator-controlled HTML. Values are escaped before insertion,
     * while the submitted description is converted to safe paragraphs.
     */
    private function replacePlaceholders(string $html, array $data): string
    {
        /** @var Submission $submission */
        $submission = $data['submission'];
        $university = $submission->course?->department?->faculty?->university;
        $studentName = trim(($submission->user?->first_name ?? '').' '.($submission->user?->last_name ?? ''));
        $description = nl2br(e((string) ($submission->description ?? '')));

        return strtr($html, [
            '{{title}}' => e((string) $data['title']),
            '{{submission_title}}' => e((string) $submission->title),
            '{{student_name}}' => e($studentName),
            '{{student_id}}' => e((string) ($submission->user?->student_id ?? 'N/A')),
            '{{course_name}}' => e((string) ($submission->course?->name ?? '')),
            '{{course_code}}' => e((string) ($submission->course?->code ?? '')),
            '{{department}}' => e((string) ($submission->course?->department?->name ?? '')),
            '{{faculty}}' => e((string) ($submission->course?->department?->faculty?->name ?? '')),
            '{{university}}' => e((string) ($university?->name ?? config('app.name'))),
            '{{description}}' => $description,
            '{{generated_at}}' => e(now()->toDayDateTimeString()),
        ]);
    }

    private function fallbackView(string $type): string
    {
        $view = 'pdfs.'.Str::slug($type);

        return view()->exists($view) ? $view : 'pdfs.project';
    }
}
