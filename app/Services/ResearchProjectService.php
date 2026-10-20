<?php

namespace App\Services;

use App\Models\ResearchProject;
use App\Models\ResearchSection;
use App\Models\ResearchType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResearchProjectService
{
    public function __construct(
        protected ContentWorkspaceService $workspace,
        protected WorkflowService $workflows,
    ) {}

    public function create(array $data, User $owner): ResearchProject
    {
        return DB::transaction(function () use ($data, $owner) {
            $type = ResearchType::query()->with('workflowDefinition.stages')->findOrFail($data['research_type_id']);

            $templateVersion = $type->activeTemplateVersion();
            $methodologies = app(ResearchMethodologyService::class);
            $setup = $methodologies->snapshot($data);
            $sectionSchema = $setup && $setup['report_structure'] !== 'institutional'
                ? $methodologies->sections($setup)
                : $this->sectionsFor($type, $templateVersion);
            if ($setup) {
                $setup['section_schema'] = $sectionSchema;
                $data['metadata'] = array_merge($data['metadata'] ?? [], ['research_setup' => $setup, 'citation_style' => $setup['citation_style']]);
            }
            foreach (array_keys($methodologies->rules()) as $field) unset($data[$field]);

            $project = ResearchProject::create(array_merge($data, [
                'university_id' => $data['university_id'] ?? $owner->university_id,
                'owner_id' => $owner->id,
                'research_template_version_id' => $templateVersion?->id,
                'status' => 'draft',
                'progress' => 0,
                'keywords' => $this->normalizeKeywords($data['keywords'] ?? []),
            ]));

            $project->memberRecords()->create([
                'user_id' => $owner->id,
                'role' => 'lead_author',
                'contribution_percent' => 100,
                'permissions' => ['write', 'comment', 'manage_references'],
            ]);

            foreach ($sectionSchema as $position => $section) {
                $document = $this->workspace->create([
                    'document_type' => 'research_section',
                    'title' => $section['title'],
                    'body' => $section['initial_content'] ?? '',
                    'metadata' => ['section_key' => $section['key'], 'research_project_uuid' => $project->uuid],
                ], $owner);

                ResearchSection::create([
                    'research_project_id' => $project->id,
                    'content_document_id' => $document->id,
                    'key' => $section['key'],
                    'title' => $section['title'],
                    'position' => $position,
                    'is_required' => $section['required'] ?? true,
                    'status' => 'draft',
                    'created_by' => $owner->id,
                    'completion_percent' => trim(strip_tags((string) ($section['initial_content'] ?? ''))) === '' ? 0 : 10,
                ]);
            }

            if ($setup) {
                $stages = $setup['method']['stages'];
                foreach ($stages as $index => $title) {
                    $project->milestones()->create([
                        'title' => $title, 'description' => $setup['method']['decision'],
                        'weight' => round(100 / count($stages), 2),
                        'status' => $index === 0 ? 'in_progress' : 'pending',
                        'due_at' => $project->expected_completion_date
                            ? now()->addDays(max(0, (int) now()->diffInDays($project->expected_completion_date)) * ($index + 1) / count($stages)) : null,
                    ]);
                }
            }
            if ($type->workflowDefinition) {
                $stages = $type->workflowDefinition->stages()->orderBy('position')->get();
                $weight = $stages->isEmpty() ? 0 : round(100 / $stages->count(), 2);
                foreach ($setup ? [] : $stages as $stage) {
                    $project->milestones()->create([
                        'workflow_stage_id' => $stage->id,
                        'title' => $stage->name,
                        'description' => $stage->description,
                        'weight' => $weight,
                        'status' => $stage->is_initial ? 'in_progress' : 'pending',
                        'due_at' => $stage->deadline_days && $project->expected_completion_date ? $project->created_at->addDays($stage->deadline_days) : null,
                    ]);
                }
                $instance = $this->workflows->start($type->workflowDefinition, $project, $owner);
                $project->update([
                    'workflow_instance_id' => $instance->id,
                    'status' => $instance->currentStage?->key ?? 'draft',
                ]);
            }

            return $project->fresh(['researchType', 'sections.document', 'workflowInstance.currentStage']);
        });
    }

    public function recalculateProgress(ResearchProject $project): float
    {
        $sections = $project->sections()->get();
        if ($sections->isEmpty()) {
            return 0.0;
        }

        $progress = round($sections->avg(fn ($section) => $section->status === 'approved' ? 100 : min(99, (float) $section->completion_percent)), 2);
        $project->update(['progress' => $progress]);

        return $progress;
    }

    protected function sectionsFor(ResearchType $type, $templateVersion = null): array
    {
        $schema = $templateVersion?->template_schema ?? $type->template_schema ?? [];
        $configured = $schema['sections'] ?? $schema;
        if (is_array($configured) && $configured !== []) {
            return array_values(array_map(function ($section, $index) {
                if (is_string($section)) {
                    return ['key' => Str::slug($section, '_'), 'title' => $section, 'required' => true];
                }

                return [
                    'key' => $section['key'] ?? Str::slug($section['title'] ?? 'section-'.($index + 1), '_'),
                    'title' => $section['title'] ?? 'Section '.($index + 1),
                    'required' => $section['required'] ?? true,
                    'initial_content' => $section['initial_content'] ?? '',
                ];
            }, $configured, array_keys($configured)));
        }

        return [
            ['key' => 'abstract', 'title' => 'Abstract', 'required' => true],
            ['key' => 'chapter_1', 'title' => 'Chapter 1: Introduction', 'required' => true],
            ['key' => 'chapter_2', 'title' => 'Chapter 2: Literature Review', 'required' => true],
            ['key' => 'chapter_3', 'title' => 'Chapter 3: Methodology', 'required' => true],
            ['key' => 'chapter_4', 'title' => 'Chapter 4: Results and Discussion', 'required' => true],
            ['key' => 'chapter_5', 'title' => 'Chapter 5: Conclusion and Recommendations', 'required' => true],
            ['key' => 'references', 'title' => 'References', 'required' => true],
        ];
    }

    protected function normalizeKeywords(array|string $keywords): array
    {
        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }

        return array_values(array_unique(array_filter(array_map('trim', $keywords))));
    }
}
