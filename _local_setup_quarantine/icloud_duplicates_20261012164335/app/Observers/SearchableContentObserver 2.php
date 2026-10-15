<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\IndexSearchableContent;
use App\Jobs\RemoveSearchableContent;
use App\Models\KnowledgePublication;
use Illuminate\Database\Eloquent\Model;

class SearchableContentObserver
{
    public function saved(Model $model): void
    {
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
