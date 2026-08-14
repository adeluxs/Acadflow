<?php

namespace App\Http\Controllers;

use App\Models\ResearchProject;
use App\Models\ResearchType;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResearchConfigurationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);
        $tenant = $request->user()->isSuperAdmin() ? null : $request->user()->university_id;
        $scope = fn ($query) => $query->when($tenant, fn ($q) => $q->where(fn ($s) => $s->whereNull('university_id')->orWhere('university_id', $tenant)));

        $workflows = $scope(WorkflowDefinition::query())->with(['stages', 'instances'])->orderBy('name')->get();
        $types = $scope(ResearchType::query())->with(['workflowDefinition.stages', 'templateVersions'])->orderBy('name')->get();

        return view('research.configuration', compact('workflows', 'types'));
    }

    public function storeWorkflow(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validateWorkflow($request);
        $universityId = $request->user()->isSuperAdmin() ? ($data['university_id'] ?? null) : $request->user()->university_id;
        $this->assertUniqueWorkflowKey($universityId, $data['key']);

        DB::transaction(function () use ($data, $universityId): void {
            $workflow = WorkflowDefinition::create([
                'university_id' => $universityId,
                'key' => $data['key'],
                'name' => $data['name'],
                'subject_type' => ResearchProject::class,
                'description' => $data['description'] ?? null,
                'settings' => $data['settings'],
                'is_active' => $data['is_active'],
            ]);
            $this->replaceStages($workflow, $data['stages']);
        });

        return back()->with('success', 'Research workflow created with validated stages and transition rules.');
    }

    public function updateWorkflow(Request $request, WorkflowDefinition $workflow): RedirectResponse
    {
        $this->authorizeWorkflow($request, $workflow);
        $data = $this->validateWorkflow($request);
        $universityId = $request->user()->isSuperAdmin() ? ($data['university_id'] ?? $workflow->university_id) : $request->user()->university_id;
        $this->assertUniqueWorkflowKey($universityId, $data['key'], $workflow->id);

        DB::transaction(function () use ($workflow, $data, $universityId): void {
            if ($workflow->instances()->exists()) {
                // Definitions already used by active/history records are versioned instead of mutated.
                $originalKey = $workflow->key;
                $workflow->update(['key' => $originalKey.'-retired-'.$workflow->id, 'is_active' => false]);
                $replacement = WorkflowDefinition::create([
                    'university_id' => $universityId,
                    'key' => $data['key'],
                    'name' => $data['name'],
                    'subject_type' => ResearchProject::class,
                    'description' => $data['description'] ?? null,
                    'settings' => array_merge($data['settings'], ['supersedes_workflow_id' => $workflow->id]),
                    'is_active' => $data['is_active'],
                ]);
                $this->replaceStages($replacement, $data['stages']);
                ResearchType::where('workflow_definition_id', $workflow->id)->update(['workflow_definition_id' => $replacement->id]);
                return;
            }

            $workflow->update([
                'university_id' => $universityId,
                'key' => $data['key'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'settings' => $data['settings'],
                'is_active' => $data['is_active'],
            ]);
            $this->replaceStages($workflow, $data['stages']);
        });

        return back()->with('success', 'Workflow saved. Used definitions were safely versioned for future projects.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $this->validateType($request);
        $universityId = $request->user()->isSuperAdmin() ? ($data['university_id'] ?? null) : $request->user()->university_id;
        $workflow = WorkflowDefinition::findOrFail($data['workflow_definition_id']);
        $this->assertTenantMatch($request, $workflow->university_id);
        $this->assertUniqueTypeSlug($universityId, $data['slug']);

        ResearchType::create($this->typeAttributes($data, $universityId));
        return back()->with('success', 'Research type created and connected to the selected workflow.');
    }

    public function updateType(Request $request, ResearchType $type): RedirectResponse
    {
        $this->authorizeType($request, $type);
        $data = $this->validateType($request);
        $universityId = $request->user()->isSuperAdmin() ? ($data['university_id'] ?? $type->university_id) : $request->user()->university_id;
        $workflow = WorkflowDefinition::findOrFail($data['workflow_definition_id']);
        $this->assertTenantMatch($request, $workflow->university_id);
        $this->assertUniqueTypeSlug($universityId, $data['slug'], $type->id);
        $type->update($this->typeAttributes($data, $universityId));
        return back()->with('success', 'Research type configuration updated for future projects.');
    }

    private function validateWorkflow(Request $request): array
    {
        $data = $request->validate([
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'settings' => ['nullable'],
            'stages' => ['required'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['settings'] = $this->jsonObject($data['settings'] ?? [], 'settings');
        $data['stages'] = $this->jsonArray($data['stages'], 'stages');
        $data['is_active'] = $request->boolean('is_active', true);
        $this->validateStages($data['stages']);
        return $data;
    }

    private function validateType(Request $request): array
    {
        $data = $request->validate([
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'workflow_definition_id' => ['required', 'integer', 'exists:workflow_definitions,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'template_schema' => ['required'],
            'validation_rules' => ['nullable'],
            'similarity_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'publication_eligible' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['template_schema'] = $this->jsonObject($data['template_schema'], 'template_schema');
        $data['validation_rules'] = $this->jsonObject($data['validation_rules'] ?? [], 'validation_rules');
        $data['publication_eligible'] = $request->boolean('publication_eligible');
        $data['is_active'] = $request->boolean('is_active', true);
        return $data;
    }

    private function validateStages(array $stages): void
    {
        if (count($stages) < 2) throw ValidationException::withMessages(['stages' => 'At least two workflow stages are required.']);
        $keys = [];
        $initial = 0;
        $final = 0;
        foreach ($stages as $index => $stage) {
            if (! is_array($stage) || empty($stage['key']) || empty($stage['name'])) throw ValidationException::withMessages(['stages' => "Stage {$index} requires key and name."]);
            if (! preg_match('/^[a-z0-9_\-]+$/', $stage['key'])) throw ValidationException::withMessages(['stages' => "Stage {$stage['key']} has an invalid key."]);
            if (in_array($stage['key'], $keys, true)) throw ValidationException::withMessages(['stages' => "Duplicate stage key: {$stage['key']}."]);
            $keys[] = $stage['key'];
            $initial += ! empty($stage['is_initial']) ? 1 : 0;
            $final += ! empty($stage['is_final']) ? 1 : 0;
        }
        if ($initial !== 1 || $final !== 1) throw ValidationException::withMessages(['stages' => 'Exactly one initial and one final stage are required.']);
        foreach ($stages as $stage) {
            foreach ((array) data_get($stage, 'settings.allowed_transitions', []) as $target) {
                if (! in_array($target, $keys, true)) throw ValidationException::withMessages(['stages' => "Stage {$stage['key']} targets missing stage {$target}."]);
            }
        }
    }

    private function replaceStages(WorkflowDefinition $workflow, array $stages): void
    {
        $workflow->stages()->delete();
        foreach (array_values($stages) as $position => $stage) {
            WorkflowStage::create([
                'workflow_definition_id' => $workflow->id,
                'key' => $stage['key'],
                'name' => $stage['name'],
                'position' => $position,
                'deadline_days' => $stage['deadline_days'] ?? null,
                'actor_roles' => array_values(array_unique((array) ($stage['actor_roles'] ?? []))),
                'settings' => (array) ($stage['settings'] ?? []),
                'requirements' => (array) ($stage['requirements'] ?? []),
                'is_initial' => (bool) ($stage['is_initial'] ?? false),
                'is_final' => (bool) ($stage['is_final'] ?? false),
            ]);
        }
    }

    private function typeAttributes(array $data, ?int $universityId): array
    {
        return [
            'university_id' => $universityId,
            'workflow_definition_id' => $data['workflow_definition_id'],
            'name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?? null,
            'template_schema' => $data['template_schema'], 'validation_rules' => $data['validation_rules'],
            'similarity_threshold' => $data['similarity_threshold'], 'publication_eligible' => $data['publication_eligible'], 'is_active' => $data['is_active'],
        ];
    }

    private function jsonObject(mixed $value, string $field): array
    {
        $decoded = $this->decodeJson($value, $field);
        if (! is_array($decoded) || array_is_list($decoded)) throw ValidationException::withMessages([$field => 'A JSON object is required.']);
        return $decoded;
    }

    private function jsonArray(mixed $value, string $field): array
    {
        $decoded = $this->decodeJson($value, $field);
        if (! is_array($decoded) || ! array_is_list($decoded)) throw ValidationException::withMessages([$field => 'A JSON array is required.']);
        return $decoded;
    }

    private function decodeJson(mixed $value, string $field): mixed
    {
        if (is_array($value)) return $value;
        try { return json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw ValidationException::withMessages([$field => 'Invalid JSON: '.$e->getMessage()]); }
    }

    private function assertUniqueWorkflowKey(?int $universityId, string $key, ?int $ignore = null): void
    {
        $exists = WorkflowDefinition::query()->where('key', $key)->where(function ($q) use ($universityId) { $universityId === null ? $q->whereNull('university_id') : $q->where('university_id', $universityId); })->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists();
        if ($exists) throw ValidationException::withMessages(['key' => 'This workflow key already exists in the selected scope.']);
    }

    private function assertUniqueTypeSlug(?int $universityId, string $slug, ?int $ignore = null): void
    {
        $exists = ResearchType::query()->where('slug', $slug)->where(function ($q) use ($universityId) { $universityId === null ? $q->whereNull('university_id') : $q->where('university_id', $universityId); })->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists();
        if ($exists) throw ValidationException::withMessages(['slug' => 'This research type slug already exists in the selected scope.']);
    }

    private function authorizeAdmin(Request $request): void { abort_unless($request->user()?->isAdmin(), 403); }
    private function authorizeWorkflow(Request $request, WorkflowDefinition $workflow): void { $this->authorizeAdmin($request); $this->assertTenantMatch($request, $workflow->university_id); }
    private function authorizeType(Request $request, ResearchType $type): void { $this->authorizeAdmin($request); $this->assertTenantMatch($request, $type->university_id); }
    private function assertTenantMatch(Request $request, ?int $universityId): void { abort_unless($request->user()->isSuperAdmin() || $universityId === null || $universityId === $request->user()->university_id, 403); }
}
