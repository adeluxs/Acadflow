<?php

namespace App\Http\Controllers;

use App\Models\ContentVersion;
use App\Models\ResearchProject;
use App\Models\ResearchResource;
use App\Services\ContentWorkspaceService;
use App\Services\FeatureAccessService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\ResearchAnalysisService;
use App\Services\ResearchMethodologyService;
use App\Services\ResearchResourceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResearchPlanController extends Controller
{
    public function show(Request $request, ResearchProject $research)
    {
        $this->authorize('view', $research);
        $research->load(['sections.document:id,version_number', 'milestones']);
        $resources = $research->resources()->with('document:id,version_number')
            ->when(! $request->boolean('archived'), fn ($query) => $query->whereNull('metadata->archived_at'))
            ->latest()->orderByDesc('id')->paginate(25, ['*'], 'resources_page')->withQueryString();
        $activeResource = $request->filled('resource')
            ? $research->resources()->with(['document', 'mediaAsset'])->where('uuid', $request->query('resource'))->first()
            : null;
        // Coding tools need labels/metadata, never the bodies of every resource.
        $research->setRelation('resources', $research->resources()->whereIn('kind', ['coded_excerpt', 'file', 'coding', 'instrument'])->get());
        $activeResource?->load(['document.versions' => fn($query) => $query->limit(15)]);
        $kinds = ResearchResourceService::KINDS;
        $setup = data_get($research->metadata, 'research_setup', []);
        $datasetsEnabled = FeatureAccessService::effectiveStatus('research_datasets', $request->user()->university_id) === FeatureAccessService::STATUS_ENABLED;
        return response()->view('research.plan', compact('research', 'resources', 'activeResource', 'kinds', 'setup', 'datasetsEnabled'))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, ResearchProject $research)
    {
        $this->authorize('update', $research);
        $data = $request->validate([
            'objectives' => ['nullable', 'string', 'max:6000'],
            'citation_style' => ['required', Rule::in(array_keys(config('research_methodologies.styles')))],
            'citation_instructions' => ['nullable', 'string', 'max:3000'],
            'citation_template' => app(ResearchMethodologyService::class)->citationTemplateRules(),
            'format_font' => ['required', Rule::in(['serif', 'sans-serif'])],
            'format_size' => ['required', 'integer', 'between:10,14'],
            'format_spacing' => ['required', 'numeric', 'between:1,2'],
            'format_margin' => ['required', 'integer', 'between:15,40'],
            'requirements_reviewed' => ['nullable', 'array', 'max:30'],
            'requirements_reviewed.*' => ['integer', 'between:0,29'],
            'reporting_review' => ['nullable', 'array', 'max:60'],
            'reporting_review.*' => ['string', Rule::in(collect(config('research_methodologies.frameworks'))->flatMap(fn($framework, $key) => collect($framework['checks'])->keys()->map(fn($index) => $key.':'.$index))->all())],
        ]);
        DB::transaction(function () use ($research, $data) {
            $project = ResearchProject::query()->lockForUpdate()->findOrFail($research->id);
            $metadata = $project->metadata ?? [];
            $metadata['citation_style'] = $data['citation_style'];
            $metadata['research_setup'] = array_merge($metadata['research_setup'] ?? [], $data, ['requirements_reviewed' => array_values(array_unique($data['requirements_reviewed'] ?? [])), 'reporting_review' => array_values(array_unique($data['reporting_review'] ?? []))]);
            $project->update(['metadata' => $metadata]);
        });
        return back()->with('success', 'Research plan and export formatting saved.');
    }

    public function storeResource(Request $request, ResearchProject $research, ResearchResourceService $resources)
    {
        $this->authorize('update', $research);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_diff(array_keys(ResearchResourceService::KINDS), ['analysis', 'coded_excerpt']))],
            'title' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:5120', 'mimes:pdf,docx,txt,csv,xlsx,png,jpg,jpeg'],
        ]);
        if ($request->hasFile('file') && in_array(strtolower($request->file('file')->getClientOriginalExtension()), ['csv', 'xlsx'])) {
            abort_unless(FeatureAccessService::effectiveStatus('research_datasets', $request->user()->university_id) === FeatureAccessService::STATUS_ENABLED, 403, 'Dataset uploads are disabled by your administrator.');
        }
        if ($data['kind'] === 'guideline') $request->validate(['file' => ['required', 'file', 'mimes:pdf,docx,txt', 'max:5120']]);
        if ($request->hasFile('file')) {
            $resource = $resources->upload($research, $request->user(), $request->file('file'), $data['kind'], $data['title']);
        } else {
            $resource = $resources->create($research, $request->user(), $data['kind'], $data['title'], $resources->starter($data['kind'], $research));
        }
        return redirect()->route('research.plan', ['research' => $research, 'resource' => $resource->uuid])->with('success', 'Research resource added. Drafts require your review and completion.');
    }

    public function saveResource(Request $request, ResearchProject $research, ResearchResource $resource, ContentWorkspaceService $workspace)
    {
        $this->authorize('update', $research);
        abort_unless($resource->research_project_id === $research->id && $resource->document, 404);
        abort_if(in_array($resource->kind, ['analysis', 'guideline', 'coded_excerpt']), 423, 'Keep computed results and original guideline extractions unchanged; create a separate interpretation note.');
        $data = $request->validate(['body' => ['nullable', 'string', 'max:200000'], 'expected_version' => ['required', 'integer', 'min:1']]);
        $document = $workspace->autosave($resource->document, $request->user(), (string) ($data['body'] ?? ''), 'Research resource edited', $data['expected_version']);
        if ($request->expectsJson()) return response()->json(['saved' => true, 'version' => $document->version_number]);
        return back()->with('success', 'Resource version saved.');
    }

    public function restoreResource(Request $request, ResearchProject $research, ResearchResource $resource, ContentVersion $version, ContentWorkspaceService $workspace)
    {
        $this->authorize('update', $research);
        abort_unless($resource->research_project_id === $research->id && $resource->document && ! in_array($resource->kind, ['analysis', 'guideline', 'coded_excerpt']), 404);
        $workspace->restore($resource->document, $version, $request->user());
        return back()->with('success', 'Earlier resource restored as a new version.');
    }

    public function download(ResearchProject $research, ResearchResource $resource, SafeFileDeliveryService $files)
    {
        $this->authorize('view', $research);
        abort_unless($resource->research_project_id === $research->id, 404);
        $asset = $resource->mediaAsset;
        abort_unless($asset && in_array($asset->scan_status, ['clean', 'skipped']) && ! $asset->quarantined_at, 404);
        return $files->stream($asset->disk, $asset->path, $asset->original_name, $asset->mime_type);
    }

    public function applyGuideline(Request $request, ResearchProject $research, ResearchResource $resource, ContentWorkspaceService $workspace)
    {
        $this->authorize('update', $research);
        abort_unless($resource->research_project_id === $research->id && $resource->kind === 'guideline', 404);
        $data = $request->validate(['headings' => ['required', 'string', 'max:6000'], 'confirmed' => ['accepted']]);
        $headings = collect(preg_split('/\R/', $data['headings']))->map(fn ($line) => trim($line))->filter()->unique()->values();
        abort_unless($headings->count() >= 1 && $headings->count() <= 30 && $headings->every(fn ($h) => mb_strlen($h) <= 180), 422, 'Use 1–30 headings of at most 180 characters.');
        DB::transaction(function () use ($research, $resource, $headings, $request, $workspace) {
            $project = ResearchProject::query()->lockForUpdate()->findOrFail($research->id);
            abort_if(in_array($project->status, ['approved', 'archived']), 423, 'An approved project needs an authorised amendment.');
            $existing = $project->sections()->lockForUpdate()->get();
            $ordered = [];
            foreach ($headings as $title) {
                $section = $existing->first(fn ($s) => mb_strtolower(trim($s->title)) === mb_strtolower($title));
                if (! $section) {
                    $doc = $workspace->create(['document_type' => 'research_section', 'title' => $title, 'body' => ''], $request->user());
                    $section = $project->sections()->create(['content_document_id' => $doc->id, 'created_by' => $request->user()->id, 'key' => 'guideline_'.Str::random(12), 'title' => $title, 'position' => 0, 'is_required' => true, 'status' => 'draft']);
                }
                $ordered[] = $section->id;
            }
            $ordered = array_values(array_unique(array_merge($ordered, $existing->pluck('id')->all())));
            foreach ($ordered as $position => $id) $project->sections()->whereKey($id)->update(['position' => $position]);
            $metadata = $project->metadata ?? [];
            $metadata['research_setup']['guideline_resource_uuid'] = $resource->uuid;
            $metadata['research_setup']['guideline_confirmed_at'] = now()->toIso8601String();
            $metadata['research_setup']['section_schema'] = $project->sections()->get()->map(fn ($s) => ['key' => $s->key, 'title' => $s->title, 'required' => $s->is_required])->all();
            $project->update(['metadata' => $metadata]);
        });
        return back()->with('success', 'Confirmed headings applied first. Existing sections and all writing are preserved after them. Manage optional sections in the control centre.');
    }

    public function analyse(Request $request, ResearchProject $research, ResearchAnalysisService $analysis, ResearchResourceService $resources)
    {
        $this->authorize('update', $research);
        abort_unless(FeatureAccessService::effectiveStatus('research_datasets', $request->user()->university_id) === FeatureAccessService::STATUS_ENABLED, 403, 'Dataset tools are disabled by your administrator.');
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'], 'deidentified' => ['accepted']]);
        $result = $analysis->describe(file_get_contents($request->file('file')->getRealPath()));
        $body = '<h2>Descriptive analysis</h2><p>Observations: '.$result['rows'].'</p><p>'.e($result['missing_rule']).'</p>';
        foreach ($result['columns'] as $column) {
            $body .= '<h3>'.e($column['column']).'</h3><ul>';
            foreach ($column as $key => $value) if ($key !== 'column') $body .= '<li>'.e(str_replace('_', ' ', $key)).': '.e($value === null ? 'Not estimable' : (string) $value).'</li>';
            $body .= '</ul>';
        }
        $body .= '<p>'.e($result['interpretation']).'</p>';
        $resource = $resources->create($research, $request->user(), 'analysis', 'Descriptive analysis · '.now()->format('d M H:i'), $body, $result + ['input_sha256' => hash_file('sha256', $request->file('file')->getRealPath()), 'computed_by' => $request->user()->id]);
        return redirect()->route('research.plan', ['research' => $research, 'resource' => $resource->uuid])->with('success', 'Summary computed from your uploaded observations. Raw CSV was not retained by this analysis tool.');
    }

    public function insertResource(Request $request, ResearchProject $research, ResearchResource $resource, ContentWorkspaceService $workspace)
    {
        $this->authorize('update', $research);
        abort_unless($resource->research_project_id === $research->id && $resource->document && $resource->kind !== 'guideline', 404);
        $data = $request->validate(['destination' => ['required', 'string', 'max:80']]);
        $parts = explode(':', $data['destination']);
        abort_unless(count($parts) === 2 && Str::isUuid($parts[0]) && ctype_digit($parts[1]), 422, 'Select a project section.');
        $section = $research->sections()->where('uuid', $parts[0])->firstOrFail();
        DB::transaction(function () use ($section, $resource, $workspace, $request, $parts, $research) {
            $section = $research->sections()->lockForUpdate()->findOrFail($section->id);
            abort_if($section->locked_at, 423, 'The destination section is locked.');
            $before = (string) $section->document->body;
            $doc = $workspace->autosave($section->document, $request->user(), $before.'<h2>'.e($resource->title).'</h2>'.$resource->document->body, 'Inserted project resource '.$resource->uuid, (int) $parts[1]);
            $section->update(['status' => 'in_progress', 'completion_percent' => min(95, max(10, str_word_count(strip_tags($doc->body)) / 10)), 'approved_at' => null, 'approved_by' => null]);
            app(\App\Services\ResearchCollaborationService::class)->recordEdit($section, $request->user(), $before, $doc->body, $doc->versions()->latest('version_number')->value('id'));
            app(\App\Services\ResearchProjectService::class)->recalculateProgress($research);
        });
        return redirect()->route('research.show', ['research' => $research, 'section' => $section->uuid])->with('success', 'Resource inserted as a recoverable section version. Review its context before submission.');
    }

    public function codeExcerpt(Request $request, ResearchProject $research, ResearchResourceService $resources)
    {
        $this->authorize('update', $research);
        $data = $request->validate([
            'source_uuid' => ['required', 'uuid'], 'locator' => ['required', 'string', 'max:180'],
            'excerpt' => ['required', 'string', 'max:5000'], 'code' => ['required', 'string', 'max:100'],
            'definition' => ['required', 'string', 'max:1000'], 'theme' => ['nullable', 'string', 'max:150'],
            'memo' => ['nullable', 'string', 'max:3000'], 'deidentified' => ['accepted'],
        ]);
        $source = $research->resources()->where('uuid', $data['source_uuid'])->whereIn('kind', ['file', 'coding', 'instrument'])->firstOrFail();
        $body = '<h2>'.e($data['code']).'</h2><p>Source: '.e($source->title).' · '.e($data['locator']).'</p><blockquote>'.nl2br(e($data['excerpt'])).'</blockquote><p>Code definition: '.e($data['definition']).'</p><p>Theme: '.e($data['theme'] ?? 'Unassigned').'</p><p>Interpretive memo: '.nl2br(e($data['memo'] ?? '')).'</p>';
        $resource = $resources->create($research, $request->user(), 'coded_excerpt', $data['code'].' · '.$data['locator'], $body, [
            'source_uuid' => $source->uuid, 'locator' => $data['locator'], 'code' => $data['code'], 'definition' => $data['definition'], 'theme' => $data['theme'] ?? null,
            'provenance' => 'Researcher-supplied excerpt and interpretation; not generated data.',
        ]);
        return redirect()->route('research.plan', ['research' => $research, 'resource' => $resource->uuid])->with('success', 'Coded excerpt saved with its source, definition and interpretive memo.');
    }

    public function archiveResource(Request $request, ResearchProject $research, ResearchResource $resource)
    {
        $this->authorize('update', $research);
        abort_unless($resource->research_project_id === $research->id, 404);
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        $resource->update(['metadata' => array_merge($resource->metadata ?? [], ['archived_at' => $data['archived'] ? now()->toIso8601String() : null])]);
        return redirect()->route('research.plan', $research)->with('success', 'Library updated. Resource content and versions are preserved.');
    }

    public function sampleSize(Request $request, ResearchProject $research, ResearchAnalysisService $analysis, ResearchResourceService $resources)
    {
        $this->authorize('update', $research);
        $data = $request->validate(['confidence' => ['required', Rule::in([90, 95, 99])], 'margin' => ['required', 'numeric', 'between:0.001,0.25'], 'proportion' => ['required', 'numeric', 'between:0.001,0.999'], 'population' => ['nullable', 'integer', 'min:1', 'max:1000000000'], 'response_rate' => ['required', 'numeric', 'between:0.01,1']]);
        $result = $analysis->sampleSize($data['confidence'], $data['margin'], $data['proportion'], $data['population'] ?? null, $data['response_rate']);
        $body = '<h2>Single-proportion sample planning</h2><ul>';
        foreach ($result as $key => $value) $body .= '<li>'.e(str_replace('_', ' ', $key)).': '.e((string) $value).'</li>';
        $resource = $resources->create($research, $request->user(), 'analysis', 'Sample-size planning', $body.'</ul>', $result);
        return redirect()->route('research.plan', ['research' => $research, 'resource' => $resource->uuid])->with('success', 'Sample-size assumptions and calculation saved for review.');
    }
}
