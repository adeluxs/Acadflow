<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\KnowledgeCategory;
use App\Models\ResearchProject;
use App\Models\ResearchType;
use App\Models\User;
use App\Services\ResearchProjectService;
use App\Services\ResearchPublicationService;
use App\Services\ResearchValidationService;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResearchProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ResearchProject::class);
        $user = $request->user();

        $projects = ResearchProject::query()
            ->with(['researchType', 'owner', 'supervisor', 'workflowInstance.currentStage'])
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->where('university_id', $user->university_id))
            ->when($user->isDepartmentAdmin(), fn ($query) => $query->where('department_id', $user->department_id))
            ->when($user->isStudent(), fn ($query) => $query->where(function ($scope) use ($user) {
                $scope->where('owner_id', $user->id)->orWhereHas('memberRecords', fn ($members) => $members->where('user_id', $user->id));
            }))
            ->when($user->isLecturer(), fn ($query) => $query->where(function ($scope) use ($user) {
                $scope->where('supervisor_id', $user->id)
                    ->orWhere('co_supervisor_id', $user->id)
                    ->orWhereHas('memberRecords', fn ($members) => $members->where('user_id', $user->id));
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('research.index', compact('projects'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ResearchProject::class);
        $user = $request->user();

        $types = ResearchType::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('university_id')->orWhere('university_id', $user->university_id))
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereHas('faculty', fn ($faculty) => $faculty->where('university_id', $user->university_id)))
            ->when($user->department_id && ! $user->isAdmin(), fn ($query) => $query->whereKey($user->department_id))
            ->orderBy('name')
            ->get();

        $supervisors = User::query()
            ->where('role', 'lecturer')
            ->where('is_active', true)
            ->when($user->university_id, fn ($query) => $query->where('university_id', $user->university_id))
            ->when($user->department_id, fn ($query) => $query->where('department_id', $user->department_id))
            ->orderBy('first_name')
            ->get();

        return view('research.create', compact('types', 'departments', 'supervisors'));
    }

    public function store(Request $request, ResearchProjectService $projects): RedirectResponse
    {
        $this->authorize('create', ResearchProject::class);
        $user = $request->user();
        $data = $request->validate([
            'research_type_id' => ['required', 'integer', 'exists:research_types,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'research_area' => ['nullable', 'string', 'max:255'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'abstract' => ['nullable', 'string'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'co_supervisor_id' => ['nullable', 'integer', 'different:supervisor_id', 'exists:users,id'],
            'expected_completion_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $type = ResearchType::findOrFail($data['research_type_id']);
        abort_unless($type->university_id === null || $type->university_id === $user->university_id || $user->isSuperAdmin(), 403);

        $department = Department::with('faculty')->findOrFail($data['department_id']);
        abort_unless($user->isSuperAdmin() || $department->faculty?->university_id === $user->university_id, 403);
        $data['university_id'] = $department->faculty?->university_id;
        if (! $user->isAdmin() && $user->department_id) {
            $data['department_id'] = $user->department_id;
        }

        foreach (['supervisor_id', 'co_supervisor_id'] as $field) {
            if (! empty($data[$field])) {
                $supervisor = User::findOrFail($data[$field]);
                abort_unless($supervisor->role === 'lecturer' && ($user->isSuperAdmin() || $supervisor->university_id === $user->university_id), 422);
            }
        }

        $project = $projects->create($data, $user);

        return redirect()->route('research.show', $project)->with('success', 'Research project created with its configured workspace and workflow.');
    }

    public function show(ResearchProject $research): View
    {
        $this->authorize('view', $research);
        $research->load([
            'researchType', 'owner', 'supervisor', 'coSupervisor', 'department',
            'sections.document.versions', 'sections.document.comments.user',
            'workflowInstance.currentStage', 'workflowInstance.definition.stages',
            'workflowInstance.transitions.actor', 'latestValidationReport', 'corrections', 'meetings', 'publications',
        ]);

        $categories = KnowledgeCategory::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('university_id')->orWhere('university_id', $research->university_id))
            ->orderBy('name')
            ->get();

        return view('research.show', compact('research', 'categories'));
    }

    public function update(Request $request, ResearchProject $research): RedirectResponse
    {
        $this->authorize('update', $research);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'research_area' => ['nullable', 'string', 'max:255'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'abstract' => ['nullable', 'string'],
            'expected_completion_date' => ['nullable', 'date'],
        ]);
        $data['keywords'] = array_values(array_filter(array_map('trim', explode(',', (string) ($data['keywords'] ?? '')))));
        $research->update($data);

        return back()->with('success', 'Project details updated.');
    }

    public function transition(Request $request, ResearchProject $research, WorkflowService $workflows): RedirectResponse
    {
        $this->authorize('transition', $research);
        $data = $request->validate([
            'target_stage' => ['required', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($research->workflowInstance, 422, 'This project has no configured workflow.');

        $instance = $workflows->transition(
            $research->workflowInstance,
            $data['target_stage'],
            $request->user(),
            $data['action'] ?? 'advance',
            $data['note'] ?? null,
        );

        $stage = $instance->currentStage;
        $updates = ['status' => $stage->key];
        if ($stage->is_final) {
            if (str_contains($stage->key, 'archive')) {
                $updates['status'] = 'archived';
                $updates['archived_at'] = now();
                $updates['approved_at'] = $research->approved_at ?? now();
            } else {
                $updates['status'] = 'approved';
                $updates['approved_at'] = now();
            }
        }
        $research->update($updates);

        return back()->with('success', 'Research workflow moved to '.$stage->name.'.');
    }

    public function validateProject(Request $request, ResearchProject $research, ResearchValidationService $validation): RedirectResponse
    {
        $this->authorize('validate', $research);
        $report = $validation->queue($research, $request->user());

        return back()->with('success', 'Validation and similarity analysis queued. Report #'.$report->id.' will update when processing completes.');
    }

    public function publish(Request $request, ResearchProject $research, ResearchPublicationService $publishing): RedirectResponse
    {
        $this->authorize('publish', $research);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'visibility' => ['required', 'in:public,institution'],
        ]);

        if (! empty($data['category_id'])) {
            $category = KnowledgeCategory::findOrFail($data['category_id']);
            abort_unless(
                $category->university_id === null || $category->university_id === $research->university_id,
                403,
                'The selected category is outside this research project university.'
            );
        }

        $publication = $publishing->createPublication($research, $request->user(), $data);

        return redirect()->route('knowledge.manage.edit', $publication)->with('success', 'A linked Knowledge Hub draft was created from the approved research.');
    }
}
