<?php

namespace App\Services;

use App\Models\ContentDocument;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContentWorkspaceService
{
    public function __construct(protected SafeHtmlService $html) {}
    public function create(array $attributes, User $owner): ContentDocument
    {
        return DB::transaction(function () use ($attributes, $owner) {
            if (array_key_exists('body', $attributes)) {
                $attributes['body'] = $this->html->sanitize($attributes['body']);
            }

            $document = ContentDocument::create(array_merge([
                'university_id' => $owner->university_id,
                'owner_id' => $owner->id,
                'status' => 'draft',
                'visibility' => 'private',
                'version_number' => 1,
            ], $attributes));

            $document->versions()->create([
                'author_id' => $owner->id,
                'version_number' => 1,
                'body' => $document->body,
                'change_summary' => 'Initial version',
                'is_snapshot' => true,
                'created_at' => now(),
            ]);

            return $document;
        });
    }

    public function autosave(ContentDocument $document, User $author, string $body, ?string $summary = null, ?int $expectedVersion = null): ContentDocument
    {
        $body = $this->html->sanitize($body);

        return DB::transaction(function () use ($document, $author, $body, $summary, $expectedVersion) {
            $document = ContentDocument::query()->lockForUpdate()->findOrFail($document->id);
            abort_if($expectedVersion !== null && $expectedVersion !== (int) $document->version_number, 409,
                'This document changed in another session. Copy your draft before reloading to compare versions.');
            if ((string) $document->body === $body) {
                $document->forceFill(['autosaved_at' => now()])->save();
                return $document;
            }
            $nextVersion = $document->version_number + 1;

            ContentVersion::create([
                'content_document_id' => $document->id,
                'author_id' => $author->id,
                'version_number' => $nextVersion,
                'body' => $body,
                'change_summary' => $summary ?: 'Auto-saved changes',
                'is_snapshot' => true,
                'created_at' => now(),
            ]);

            $document->update([
                'body' => $body,
                'version_number' => $nextVersion,
                'autosaved_at' => now(),
            ]);

            return $document->fresh('versions');
        });
    }

    public function restore(ContentDocument $document, ContentVersion $version, User $actor): ContentDocument
    {
        abort_unless($version->content_document_id === $document->id, 404);

        return $this->autosave($document, $actor, (string) $version->body, 'Restored version '.$version->version_number);
    }
}
