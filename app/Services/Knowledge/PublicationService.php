<?php

namespace App\Services\Knowledge;

use App\Models\KnowledgeCategory;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeTag;
use App\Models\User;
use App\Services\ContentWorkspaceService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicationService
{
    public function __construct(private readonly ContentWorkspaceService $workspace) {}

    public function createDraft(array $data, User $creator): KnowledgePublication
    {
        $this->assertCategoryScope($data['category_id'] ?? null, $creator);

        return DB::transaction(function () use ($data, $creator): KnowledgePublication {
            $document = $this->workspace->create([
                'document_type' => 'knowledge_publication',
                'editor_mode' => $data['editor_mode'] ?? 'rich_text',
                'title' => $data['title'],
                'body' => $data['body'] ?? '',
                'status' => 'draft',
                'visibility' => $data['visibility'] ?? 'private',
                'word_count' => $this->wordCount($data['body'] ?? ''),
                'last_synced_at' => now(),
            ], $creator);

            $publication = KnowledgePublication::create([
                'university_id' => $creator->university_id,
                'department_id' => $creator->department_id,
                'creator_id' => $creator->id,
                'content_document_id' => $document->id,
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'],
                'doi' => $data['doi'] ?? null,
                'content_type' => $data['content_type'] ?? 'academic_article',
                'language' => $data['language'] ?? 'en',
                'excerpt' => $data['excerpt'] ?? null,
                'status' => 'draft',
                'visibility' => $data['visibility'] ?? 'private',
                'access_type' => $data['access_type'] ?? 'free',
                'price' => ($data['access_type'] ?? 'free') === 'premium' ? Money::fromMinor(Money::toMinor((string)($data['price'] ?? '0'))) : '0.00',
                'reading_time_minutes' => $this->readingTime($data['body'] ?? ''),
                'metadata' => array_filter(['copyright' => $data['copyright'] ?? null]),
            ]);

            $this->syncTags($publication, $data['tags'] ?? []);

            return $publication->fresh(['document', 'category', 'tags']);
        });
    }

    public function updateDraft(KnowledgePublication $publication, array $data, User $actor): KnowledgePublication
    {
        $this->assertCategoryScope($data['category_id'] ?? null, $actor);

        return DB::transaction(function () use ($publication, $data, $actor): KnowledgePublication {
            $body = (string) ($data['body'] ?? $publication->document?->body ?? '');
            $this->workspace->autosave($publication->document, $actor, $body, 'Knowledge Hub content update');
            $publication->update([
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'] ?? $publication->title,
                'doi' => $data['doi'] ?? null,
                'content_type' => $data['content_type'] ?? $publication->content_type,
                'language' => $data['language'] ?? $publication->language ?? 'en',
                'excerpt' => $data['excerpt'] ?? null,
                'visibility' => $data['visibility'] ?? $publication->visibility,
                'access_type' => $data['access_type'] ?? $publication->access_type,
                'price' => ($data['access_type'] ?? $publication->access_type) === 'premium' ? Money::fromMinor(Money::toMinor((string)($data['price'] ?? $publication->price ?? '0'))) : '0.00',
                'reading_time_minutes' => $this->readingTime($body),
                'status' => in_array($publication->status, ['changes_requested', 'rejected'], true) ? 'draft' : $publication->status,
                'metadata' => array_merge($publication->metadata ?? [], array_filter(['copyright' => $data['copyright'] ?? null], fn ($value) => $value !== null)),
            ]);
            $publication->document->update([
                'title' => $publication->title,
                'visibility' => $publication->visibility,
                'word_count' => $this->wordCount($body),
                'last_synced_at' => now(),
            ]);
            $this->syncTags($publication, $data['tags'] ?? []);

            return $publication->fresh(['document.versions', 'category', 'tags']);
        });
    }

    private function assertCategoryScope(?int $categoryId, User $actor): void
    {
        if (! $categoryId) return;
        $category = KnowledgeCategory::findOrFail($categoryId);
        abort_unless($category->university_id === null || $actor->isSuperAdmin() || $category->university_id === $actor->university_id, 403);
    }

    private function syncTags(KnowledgePublication $publication, array|string $tags): void
    {
        $values = is_array($tags) ? $tags : explode(',', $tags);
        $ids = collect($values)->map(fn ($tag) => trim((string) $tag))->filter()->unique(fn ($tag) => Str::lower($tag))->take(15)
            ->map(fn ($name) => KnowledgeTag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id);
        $publication->tags()->sync($ids->all());
    }

    private function wordCount(string $body): int { return str_word_count(strip_tags($body)); }
    private function readingTime(string $body): int { return max(1, (int) ceil($this->wordCount($body) / 220)); }
}
