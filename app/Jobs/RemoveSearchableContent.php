<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SearchDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RemoveSearchableContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $modelClass, public readonly int $modelId) {}

    public function handle(): void
    {
        SearchDocument::query()
            ->where('searchable_type', $this->modelClass)
            ->where('searchable_id', $this->modelId)
            ->delete();
    }
}
