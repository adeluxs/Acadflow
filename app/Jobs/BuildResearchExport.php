<?php

namespace App\Jobs;

use App\Models\ResearchProject;
use App\Services\ResearchDocumentExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BuildResearchExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 240;
    public int $tries = 2;
    public int $backoff = 30;

    public function __construct(public string $exportId) {}

    public function handle(ResearchDocumentExportService $exporter): void
    {
        $key = 'research.export.'.$this->exportId;
        $state = Cache::get($key);
        if (! $state || $state['status'] === 'completed') return;
        $state['status'] = 'processing';
        Cache::put($key, $state, now()->addDay());
        $research = ResearchProject::findOrFail($state['project_id']);
        $path = 'research-exports/'.now()->format('Y-m-d').'/'.$this->exportId.'.'.$state['format'];
        $disk = Storage::disk($state['disk'] ?? 'local');
        if ($state['format'] === 'docx') {
            $temporary = $exporter->docx($research);
            $stream = fopen($temporary, 'rb');
            try { $written = $disk->put($path, $stream); }
            finally { if (is_resource($stream)) fclose($stream); @unlink($temporary); }
        } else {
            $research->load(['sections.document', 'owner', 'supervisor', 'department', 'university', 'referenceLinks.reference']);
            $written = $disk->put($path, Pdf::loadView('research.export', compact('research'))->setPaper('a4', 'portrait')->output());
        }
        if (! $written) throw new \RuntimeException('Unable to store research export.');
        Cache::put($key, array_merge($state, ['status' => 'completed', 'path' => $path]), now()->addDay());
    }

    public function failed(\Throwable $exception): void
    {
        $key = 'research.export.'.$this->exportId;
        $state = Cache::get($key);
        if ($state) Cache::put($key, array_merge($state, ['status' => 'failed']), now()->addDay());
    }
}
