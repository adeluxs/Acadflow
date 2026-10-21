<?php

namespace App\Services;

use App\Jobs\BuildResearchExport;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResearchExportQueue
{
    public function required(ResearchProject $project): bool
    {
        $bytes = DB::table('research_sections')->join('content_documents', 'content_documents.id', '=', 'research_sections.content_document_id')
            ->where('research_project_id', $project->id)->sum(DB::raw('LENGTH(content_documents.body)'));
        return $bytes >= (int) config('performance.queued_export_bytes', 250000)
            || $project->referenceLinks()->count() > 500;
    }

    public function start(ResearchProject $project, User $user, string $format): string
    {
        abort_unless(in_array($format, ['pdf', 'docx'], true), 422);
        abort_if(in_array(config('queue.default'), ['sync', 'null'], true), 503, 'Large exports need a background worker. Please contact your administrator.');
        // The lock also prevents duplicate jobs from simultaneous clicks/tabs.
        $revision = DB::table('research_sections')->join('content_documents', 'content_documents.id', '=', 'research_sections.content_document_id')
            ->where('research_project_id', $project->id)->sum('content_documents.version_number');
        $references = DB::table('academic_reference_links')->join('academic_references', 'academic_references.id', '=', 'academic_reference_links.academic_reference_id')
            ->where('research_project_id', $project->id)->selectRaw('COUNT(*) AS total, MAX(academic_references.updated_at) AS updated')->first();
        $key = 'research.export.request.'.hash('sha256', $user->id.':'.$project->id.':'.$format.':'.$project->updated_at.':'.$revision.':'.$references->total.':'.$references->updated);
        return Cache::lock($key.'.lock', 10)->block(3, function () use ($key, $project, $user, $format): string {
            $existing = Cache::get($key);
            if ($existing && in_array(Cache::get('research.export.'.$existing)['status'] ?? null, ['queued', 'processing', 'completed'], true)) return $existing;
            $id = (string) Str::uuid();
            Cache::put('research.export.'.$id, [
                'id' => $id, 'project_id' => $project->id, 'user_id' => $user->id,
                'format' => $format, 'status' => 'queued', 'created_at' => now()->toIso8601String(),
                'disk' => config('performance.export_disk', 'local'),
                'filename' => (Str::limit(Str::slug($project->title), 120, '') ?: 'research').'.'.$format,
            ], now()->addDay());
            try { BuildResearchExport::dispatch($id)->onQueue('exports'); }
            catch (\Throwable $exception) { Cache::forget('research.export.'.$id); throw $exception; }
            Cache::put($key, $id, now()->addDay());
            return $id;
        });
    }
}
