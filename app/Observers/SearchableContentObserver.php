<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\IndexSearchableContent;
use App\Jobs\RemoveSearchableContent;
use App\Models\KnowledgePublication;
use App\Models\ContentDocument;
use Illuminate\Database\Eloquent\Model;

class SearchableContentObserver
{
    public function saved(Model $model): void
    {
        // Reading a publication and updating editor bookkeeping do not change
        // searchable content. Preserve no-change saves used after tag syncing.
        $bookkeeping = match (true) {
            $model instanceof KnowledgePublication => ['view_count', 'bookmark_count', 'comment_count', 'share_count', 'download_count', 'updated_at'],
            $model instanceof ContentDocument => ['autosaved_at', 'last_synced_at', 'version_number', 'locked_at', 'locked_by', 'updated_at'],
            default => [],
        };
        if (! $model->wasRecentlyCreated && $model->getChanges() !== [] && array_diff(array_keys($model->getChanges()), $bookkeeping) === []) {
            return;
        }

        if ($model instanceof KnowledgePublication && ! $model->isPublished()) {
            RemoveSearchableContent::dispatch($model::class, (int) $model->getKey())->afterCommit();
            return;
        }

        IndexSearchableContent::dispatch($model::class, (int) $model->getKey())->afterCommit();
    }

    public function deleted(Model $model): void
    {
        RemoveSearchableContent::dispatch($model::class, (int) $model->getKey())->afterCommit();
    }

    public function restored(Model $model): void
    {
        $this->saved($model);
    }
}
