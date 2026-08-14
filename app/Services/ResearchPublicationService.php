<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\KnowledgePublication;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResearchPublicationService
{
    public function __construct(protected ContentWorkspaceService $workspace) {}

    public function createPublication(ResearchProject $project, User $actor, array $data = []): KnowledgePublication
    {
        $project->loadMissing('researchType', 'sections.document', 'department.faculty');

        if (! in_array($project->status, ['approved', 'archived'], true) || ! $project->approved_at) {
            throw ValidationException::withMessages(['project' => 'Only approved research can be published.']);
        }

        if (! $project->researchType->publication_eligible) {
            throw ValidationException::withMessages(['project' => 'This research type is not eligible for Knowledge Hub publication.']);
        }

        return DB::transaction(function () use ($project, $actor, $data) {
            $approvedSections = $project->sections
                ->filter(fn ($section) => $section->status === 'approved' && $section->document);

            if ($approvedSections->isEmpty()) {
                throw ValidationException::withMessages([
                    'project' => 'At least one approved research section is required before creating a publication.',
                ]);
            }

            $body = $approvedSections
                ->map(fn ($section) => '<h2>'.e($section->title).'</h2>'.(string) $section->document->body)
                ->implode("\n");

            $document = $this->workspace->create([
                'document_type' => 'knowledge_publication',
                'title' => $data['title'] ?? $project->title,
                'body' => $body,
                'status' => 'draft',
                'visibility' => $data['visibility'] ?? 'institution',
                'metadata' => ['source_research_project_uuid' => $project->uuid],
            ], $actor);

            $publication = KnowledgePublication::create([
                'university_id' => $project->university_id,
                'department_id' => $project->department_id,
                'creator_id' => $actor->id,
                'source_research_project_id' => $project->id,
                'content_document_id' => $document->id,
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'] ?? $project->title,
                'content_type' => $data['content_type'] ?? 'research_output',
                'excerpt' => $data['excerpt'] ?? $project->abstract,
                'status' => 'draft',
                'visibility' => $data['visibility'] ?? 'institution',
                'access_type' => 'free',
                'metadata' => ['source_approved_at' => $project->approved_at?->toISOString()],
            ]);

            AuditLog::log(
                'research_publication_created',
                $actor->id,
                KnowledgePublication::class,
                $publication->id,
                null,
                ['source_research_project_id' => $project->id],
                request()?->ip(),
                request()?->userAgent(),
            );

            return $publication->load('document', 'sourceResearchProject');
        });
    }
}
