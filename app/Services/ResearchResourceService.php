<?php

namespace App\Services;

use App\Ai\Support\TextExtractor;
use App\Models\ResearchProject;
use App\Models\ResearchResource;
use App\Models\User;
use App\Services\Media\MediaSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchResourceService
{
    public const KINDS = [
        'topic' => 'Topic and research gap', 'proposal' => 'Proposal and alignment',
        'instrument' => 'Data collection instrument', 'sampling' => 'Sampling plan',
        'ethics' => 'Ethics application draft', 'consent' => 'Informed consent draft',
        'coding' => 'Qualitative coding and themes', 'coded_excerpt' => 'Coded evidence excerpt', 'analysis_plan' => 'Analysis plan',
        'manuscript' => 'Manuscript and submission preparation', 'defence' => 'Defence preparation',
        'guideline' => 'Institutional guideline', 'file' => 'Research document', 'analysis' => 'Computed analysis',
    ];

    public function __construct(private ContentWorkspaceService $workspace, private MediaSecurityService $media) {}

    public function create(ResearchProject $project, User $actor, string $kind, string $title, string $body, array $metadata = []): ResearchResource
    {
        return DB::transaction(function () use ($project, $actor, $kind, $title, $body, $metadata) {
            $document = $this->workspace->create(['document_type' => 'research_resource', 'title' => $title, 'body' => $body, 'visibility' => 'private'], $actor);
            return $project->resources()->create(['created_by' => $actor->id, 'content_document_id' => $document->id, 'kind' => $kind, 'title' => $title, 'metadata' => $metadata]);
        });
    }

    public function upload(ResearchProject $project, User $actor, UploadedFile $file, string $kind, string $title): ResearchResource
    {
        $asset = null;
        try {
            return DB::transaction(function () use ($project, $actor, $file, $kind, $title, &$asset) {
                $resource = $project->resources()->create(['created_by' => $actor->id, 'kind' => $kind, 'title' => $title]);
                // Research documents never enter a public disk, even if general uploads use it.
                $asset = $this->media->store($file, $actor, $resource, 'private', ['purpose' => 'research_'.$kind], 'local');
                abort_unless(in_array($asset->scan_status, ['clean', 'skipped'], true), 422, 'The file could not be inspected.');
                $resource->update(['media_asset_id' => $asset->id]);
                if ($kind === 'guideline') {
                    $this->inspectArchive($file);
                    $text = app(TextExtractor::class)->fromPath($asset->path, $asset->disk);
                    $text = Str::limit(mb_convert_encoding($text, 'UTF-8', 'UTF-8'), 50000, '');
                    $lines = preg_split('/\R/', $text);
                    $headings = collect($lines)->map(fn ($line) => trim($line))->filter(fn ($line) => preg_match('/^(?:chapter\s+(?:[1-9]|one|two|three|four|five)|abstract\b|references\b|appendi(?:x|ces)\b)/i', $line) && mb_strlen($line) <= 180)->unique()->take(30)->values()->all();
                    $document = $this->workspace->create(['document_type' => 'research_guideline', 'title' => $title, 'body' => '<p>'.nl2br(e($text)).'</p>', 'visibility' => 'private'], $actor);
                    $resource->update(['content_document_id' => $document->id, 'metadata' => ['suggested_headings' => $headings, 'extraction' => trim($text) === '' ? 'unavailable' : 'requires_review', 'authority' => 'User-supplied guideline. Confirm extraction and requirements with your institution.']]);
                }
                return $resource->fresh(['document', 'mediaAsset']);
            });
        } catch (\Throwable $e) {
            if ($asset) Storage::disk($asset->disk)->delete($asset->path);
            throw $e;
        }
    }

    private function inspectArchive(UploadedFile $file): void
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'docx') return;
        if (!class_exists(\ZipArchive::class)) throw ValidationException::withMessages(['file' => 'DOCX inspection is unavailable on this server. Upload a PDF or TXT guideline instead.']);
        $zip = new \ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) throw ValidationException::withMessages(['file' => 'The DOCX archive is invalid.']);
        try {
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += $zip->statIndex($i)['size'];
                if ($total > 20 * 1024 * 1024 || $zip->numFiles > 500) throw ValidationException::withMessages(['file' => 'The expanded DOCX exceeds the inspection limit.']);
            }
        } finally { $zip->close(); }
    }

    public function starter(string $kind, ResearchProject $project): string
    {
        $method = data_get($project->metadata, 'research_setup.method.label', 'Selected research design');
        $prompts = match ($kind) {
            'topic' => ['Working topic and scope', 'Problem supported by evidence', 'What the reviewed literature does not yet answer', 'Feasibility and contribution', 'Sources to verify'],
            'proposal' => ['Problem and rationale', 'Aim, objectives and aligned questions', 'Hypotheses only if appropriate', 'Design: '.$method, 'Feasibility, timeline and limitations'],
            'instrument' => ['Instrument purpose and research question', 'Instructions and consent process', 'Questions or measurement items to develop', 'Pilot, validity and revision log'],
            'sampling' => ['Target population or corpus', 'Inclusion and exclusion criteria', 'Recruitment or source selection', 'Size rationale and assumptions', 'Bias, non-response and limitations'],
            'ethics' => ['Protocol summary for institutional review', 'Risks and safeguards', 'Recruitment and voluntary participation', 'Confidentiality, retention and access', 'Committee submission details and pending decisions'],
            'consent' => ['Study purpose in plain language', 'What participation involves', 'Risks and benefits', 'Voluntary participation and withdrawal', 'Data use, confidentiality and contact details'],
            'coding' => ['Source identifier and anonymised excerpt', 'Code label and definition', 'Inclusion and exclusion rules', 'Interpretive memo and reflexivity', 'Candidate theme and contradictory evidence'],
            'analysis_plan' => ['Question and linked data', 'Cleaning and missing-data rules', 'Analysis method and assumptions', 'Uncertainty and sensitivity', 'Reporting plan'],
            'manuscript' => ['Target journal and author instructions', 'Contribution and fit', 'Reporting checklist and actual evidence', 'Authorship, funding and conflicts', 'Cover letter and submission checklist'],
            'defence' => ['Problem and contribution', 'Objectives and methodology justification', 'Evidence for each finding', 'Limitations and alternative explanations', 'Likely questions and evidence-based responses'],
            default => ['Purpose', 'Evidence and source identifiers', 'Notes requiring review'],
        };
        return collect($prompts)->map(fn ($heading) => '<h2>'.e($heading).'</h2><p></p>')->implode('');
    }
}
