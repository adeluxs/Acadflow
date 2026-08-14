<?php

namespace App\Services;

use App\Models\ResearchArchive;
use App\Models\ResearchProject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ResearchArchiveService
{
    public function seal(ResearchProject $project, User $actor): ResearchArchive
    {
        $project->loadMissing(['sections.document.versions', 'corrections', 'validationReports', 'workflowInstance.transitions', 'memberRecords.user', 'referenceLinks.reference', 'templateVersion']);
        if (! in_array($project->status, ['approved', 'archived'], true) || ! $project->approved_at) {
            throw ValidationException::withMessages(['project' => 'Only approved research can be sealed.']);
        }
        return DB::transaction(function () use ($project, $actor) {
            $version = ((int) $project->archives()->max('version')) + 1;
            $archive = $project->archives()->create(['version' => $version, 'generated_by' => $actor->id, 'status' => 'processing', 'disk' => 'local']);
            $manifest = $this->manifest($project, $archive);
            $relative = 'research-archives/'.$project->uuid.'/v'.$version.'.zip';
            $absolute = Storage::disk('local')->path($relative);
            if (! is_dir(dirname($absolute))) mkdir(dirname($absolute), 0755, true);
            $zip = new ZipArchive();
            if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('Unable to create archive package.');
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $zip->addFromString('research.html', $this->html($project));
            $zip->addFromString('audit/workflow.json', json_encode($project->workflowInstance?->transitions?->toArray() ?? [], JSON_PRETTY_PRINT));
            $zip->addFromString('audit/validation-reports.json', json_encode($project->validationReports->toArray(), JSON_PRETTY_PRINT));
            $zip->addFromString('audit/corrections.json', json_encode($project->corrections->toArray(), JSON_PRETTY_PRINT));
            foreach ($project->sections as $section) {
                $zip->addFromString('sections/'.str_pad((string) $section->position, 3, '0', STR_PAD_LEFT).'-'.$section->key.'.html', (string) $section->document?->body);
                $zip->addFromString('versions/'.$section->key.'.json', json_encode($section->document?->versions?->toArray() ?? [], JSON_PRETTY_PRINT));
            }
            $zip->close();
            $checksum = hash_file('sha256', $absolute);
            $archive->update(['status' => 'sealed', 'package_path' => $relative, 'checksum' => $checksum, 'manifest' => $manifest, 'sealed_at' => now()]);
            $project->update(['status' => 'archived', 'archived_at' => $project->archived_at ?? now()]);
            return $archive->fresh();
        });
    }

    public function verify(ResearchArchive $archive): bool
    {
        if (! $archive->package_path || ! Storage::disk($archive->disk)->exists($archive->package_path)) return false;
        return hash_file('sha256', Storage::disk($archive->disk)->path($archive->package_path)) === $archive->checksum;
    }

    private function manifest(ResearchProject $project, ResearchArchive $archive): array
    {
        return ['schema' => 'acadflow.research.archive.v1', 'archive_uuid' => $archive->uuid, 'project_uuid' => $project->uuid, 'title' => $project->title, 'approved_at' => $project->approved_at?->toISOString(), 'template_version_uuid' => $project->templateVersion?->uuid, 'sections' => $project->sections->map(fn ($s) => ['uuid' => $s->uuid, 'key' => $s->key, 'title' => $s->title, 'status' => $s->status, 'content_checksum' => hash('sha256', (string) $s->document?->body)])->all(), 'members' => $project->memberRecords->map(fn ($m) => ['user_id' => $m->user_id, 'role' => $m->role, 'contribution_percent' => $m->contribution_percent])->all(), 'sealed_at' => now()->toISOString(), 'immutable' => true];
    }

    private function html(ResearchProject $project): string
    {
        $sections = $project->sections->map(fn ($s) => '<section><h2>'.e($s->title).'</h2>'.(string) $s->document?->body.'</section>')->implode("\n");
        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($project->title).'</title><style>body{font-family:serif;max-width:850px;margin:40px auto;line-height:1.6}h1,h2{page-break-after:avoid}</style></head><body><h1>'.e($project->title).'</h1><p>'.e((string) $project->abstract).'</p>'.$sections.'</body></html>';
    }
}
