<?php

namespace App\Services\Discovery;

use App\Models\ContentDocument;
use App\Models\CourseMaterial;
use App\Models\KnowledgePublication;
use App\Models\ResearchProject;
use App\Models\SearchDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SearchIndexService
{
    public function __construct(private readonly LocalEmbeddingService $embeddings) {}

    public function index(Model $model): SearchDocument
    {
        $payload = $this->payloadFor($model);
        $body = trim(strip_tags((string) ($payload['body'] ?? '')));
        $checksum = hash('sha256', json_encode([$payload['title'], $payload['summary'], $body, $payload['metadata']], JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($model, $payload, $body, $checksum) {
            $document = SearchDocument::updateOrCreate(
                ['searchable_type' => $model->getMorphClass(), 'searchable_id' => $model->getKey()],
                [
                    'university_id' => $payload['university_id'],
                    'content_type' => $payload['content_type'],
                    'title' => $payload['title'],
                    'summary' => $payload['summary'],
                    'body' => $body,
                    'keywords' => implode(' ', $payload['keywords']),
                    'visibility' => $payload['visibility'],
                    'access_type' => $payload['access_type'],
                    'embedding' => $this->embeddings->embed($payload['title'].' '.$payload['summary'].' '.$body),
                    'embedding_dimensions' => 64,
                    'checksum' => $checksum,
                    'metadata' => $payload['metadata'],
                    'indexed_at' => now(),
                ]
            );

            $document->chunks()->delete();
            foreach ($this->chunks($body) as $position => $chunk) {
                $document->chunks()->create([
                    'position' => $position,
                    'heading' => $chunk['heading'],
                    'content' => $chunk['content'],
                    'token_count' => $this->tokenCount($chunk['content']),
                    'embedding' => $this->embeddings->embed($chunk['heading'].' '.$chunk['content']),
                    'checksum' => hash('sha256', $chunk['content']),
                    'metadata' => ['locator' => $chunk['locator']],
                ]);
            }

            return $document->fresh('chunks');
        });
    }

    public function remove(Model $model): void
    {
        SearchDocument::query()
            ->where('searchable_type', $model->getMorphClass())
            ->where('searchable_id', $model->getKey())
            ->delete();
    }

    /** @return array<string,mixed> */
    private function payloadFor(Model $model): array
    {
        if ($model instanceof KnowledgePublication) {
            $model->loadMissing('document', 'tags', 'category');
            return [
                'university_id' => $model->university_id,
                'content_type' => 'knowledge_publication',
                'title' => $model->title,
                'summary' => $model->excerpt,
                'body' => $model->document?->body,
                'keywords' => $model->tags->pluck('name')->push($model->category?->name)->filter()->all(),
                'visibility' => $model->visibility,
                'access_type' => $model->access_type,
                'metadata' => ['slug' => $model->slug, 'creator_id' => $model->creator_id, 'department_id' => $model->department_id],
            ];
        }

        if ($model instanceof ResearchProject) {
            $model->loadMissing('sections.document');
            return [
                'university_id' => $model->university_id,
                'content_type' => 'research_project',
                'title' => $model->title,
                'summary' => $model->abstract,
                'body' => $model->sections->map(fn ($section) => $section->title."\n".$section->document?->body)->implode("\n\n"),
                'keywords' => $model->keywords ?? [],
                'visibility' => 'private',
                'access_type' => 'restricted',
                'metadata' => ['uuid' => $model->uuid, 'owner_id' => $model->owner_id, 'department_id' => $model->department_id],
            ];
        }

        if ($model instanceof ContentDocument) {
            return [
                'university_id' => $model->university_id,
                'content_type' => $model->document_type,
                'title' => $model->title,
                'summary' => Str::limit(strip_tags((string) $model->body), 500),
                'body' => $model->body,
                'keywords' => $model->metadata['keywords'] ?? [],
                'visibility' => $model->visibility,
                'access_type' => $model->visibility === 'public' ? 'free' : 'restricted',
                'metadata' => ['uuid' => $model->uuid, 'owner_id' => $model->owner_id],
            ];
        }

        if ($model instanceof CourseMaterial) {
            return [
                'university_id' => $model->course?->department?->faculty?->university_id,
                'content_type' => 'course_material',
                'title' => $model->title,
                'summary' => $model->description,
                'body' => $model->description,
                'keywords' => [],
                'visibility' => 'course',
                'access_type' => 'restricted',
                'metadata' => ['course_id' => $model->course_id, 'uploaded_by' => $model->uploaded_by],
            ];
        }

        throw new \InvalidArgumentException('This model is not supported by the shared search index.');
    }

    /** @return list<array{heading:string,content:string,locator:string}> */
    private function chunks(string $text, int $maxWords = 350): array
    {
        if ($text === '') {
            return [['heading' => 'Document', 'content' => '', 'locator' => 'document']];
        }

        $paragraphs = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];
        $chunks = [];
        $buffer = '';
        $position = 1;
        foreach ($paragraphs as $paragraph) {
            if ($this->tokenCount($buffer.' '.$paragraph) > $maxWords && trim($buffer) !== '') {
                $chunks[] = ['heading' => 'Section '.$position, 'content' => trim($buffer), 'locator' => 'chunk-'.$position];
                $position++;
                $buffer = '';
            }
            $buffer .= ($buffer === '' ? '' : "\n\n").$paragraph;
        }
        if (trim($buffer) !== '') {
            $chunks[] = ['heading' => 'Section '.$position, 'content' => trim($buffer), 'locator' => 'chunk-'.$position];
        }

        return $chunks;
    }

    private function tokenCount(string $text): int
    {
        return count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
