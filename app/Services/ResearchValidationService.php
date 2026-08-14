<?php

namespace App\Services;

use App\Ai\AiManager;
use App\Jobs\ValidateResearchProject;
use App\Models\ResearchProject;
use App\Models\ResearchValidationReport;
use App\Models\User;
use App\Services\AcademicIntegrity\PlagiarismService;
use Illuminate\Support\Facades\DB;

class ResearchValidationService
{
    public function __construct(protected AiManager $ai, protected PlagiarismService $plagiarism) {}

    public function queue(ResearchProject $project, User $actor): ResearchValidationReport
    {
        $report = ResearchValidationReport::create(['research_project_id' => $project->id, 'requested_by' => $actor->id, 'status' => 'queued', 'summary' => 'Validation and provider-independent similarity analysis are queued.']);
        ValidateResearchProject::dispatch($report)->onQueue('ai');
        return $report;
    }

    public function process(ResearchValidationReport $report): ResearchValidationReport
    {
        $report->loadMissing('project.sections.document', 'project.researchType', 'project.templateVersion', 'project.corrections', 'requester');
        $project = $report->project;
        $actor = $report->requester ?: $project->owner;
        $report->update(['status' => 'processing', 'summary' => 'Validation is processing.']);

        try {
            $sections = $project->sections->map(fn ($section) => ['key' => $section->key, 'title' => $section->title, 'status' => $section->status, 'content' => strip_tags((string) $section->document?->body)]);
            $text = $sections->map(fn ($section) => $section['title']."\n".$section['content'])->implode("\n\n");
            $requirements = $project->templateVersion?->template_schema ?? $project->researchType?->template_schema ?? [];
            $payload = [
                'type' => 'research', 'text' => $text, 'word_count' => str_word_count($text), 'sections' => $sections->all(),
                'research_requirements' => ['required_sections' => collect($requirements['sections'] ?? [])->where('required', true)->values()->all()],
                'open_corrections' => $project->corrections->where('status', 'open')->count(), 'project_title' => $project->title,
                'citation_style' => $project->templateVersion?->citation_style ?? $project->metadata['citation_style'] ?? 'apa',
            ];

            $validation = $this->ai->analyze('research_validator', $payload, $actor, 'research:'.$project->uuid);
            $similarity = $this->plagiarism->check($project, $text, $actor, ['threshold' => (float) $project->researchType->similarity_threshold]);

            DB::transaction(function () use ($report, $validation, $similarity): void {
                $report->update([
                    'status' => $validation->success && $similarity->status === 'completed' ? 'completed' : 'failed',
                    'readiness_score' => $validation->score,
                    'similarity_score' => $similarity->similarity_score,
                    'source' => $validation->source,
                    'summary' => $validation->summary,
                    'findings' => [
                        'request_id' => $validation->requestId,
                        'status' => $validation->status,
                        'validation' => $validation->findings,
                        'evidence' => $validation->evidence,
                        'suggested_actions' => $validation->suggestedActions,
                        'confidence' => $validation->confidence,
                        'human_review_required' => true,
                        'similarity_check_uuid' => $similarity->uuid,
                        'similarity_matches' => $similarity->matches->map(fn ($match) => $match->only(['source_type','source_identifier','source_title','source_url','source_excerpt','target_locations','similarity_score','citation_status','provider']))->all(),
                        'similarity_is_not_misconduct_decision' => true,
                    ],
                    'completed_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            report($exception);
            $report->update(['status' => 'failed', 'summary' => 'Validation could not be completed. The project remains available for editing.', 'findings' => ['error' => class_basename($exception), 'human_review_required' => true], 'completed_at' => now()]);
        }
        return $report->fresh();
    }
}
