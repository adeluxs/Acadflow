<?php

namespace App\Jobs;

use App\Services\Discovery\SearchIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexSearchableContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly string $modelClass, public readonly int $modelId) {}

    public function handle(SearchIndexService $index): void
    {
        $model = $this->modelClass::query()->find($this->modelId);
        if ($model) {
            $index->index($model);
        }
    }
}
