<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ResearchProject;
use App\Models\ResearchSection;
use App\Models\ResearchType;
use App\Models\User;
use App\Services\ContentWorkspaceService;
use App\Services\ResearchProjectService;
use App\Services\ResearchPublicationService;
use App\Services\ResearchValidationService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ResearchProject::class);
        $user = $request->user();
        $query = ResearchProject::query()->with(['researchType', 'owner', 'supervisor', 'workflowInstance.currentStage']);
        if (! $user->isSuperAdmin()) $query->where('university_id', $user->university_id);
        if ($user->isDepartmentAdmin()) $query->where('department_id', $user->department_id);
        if ($user->isStudent()) $query->where(fn ($scope) => $scope->where('owner_id', $user->id)->orWhereHas('memberRecords', fn ($members) => $members->where('user_id', $user->id)));
        if ($user->isLecturer()) $query->where(fn ($scope) => $scope->where('supervisor_id', $user->id)->orWhere('co_supervisor_id', $user->id)->orWhereHas('memberRecords', fn ($members) => $members->where('user_id', $user->id)));
        $query->when($request->filled('status'), fn ($scope) => $scope->where('status', $request->string('status')->toString()));

        return $query->latest('updated_at')->paginate(20);
    }

    public function store(Request $request, ResearchProjectService $projects)
    {
        $this->authorize('create', ResearchProject::class);
        $user = $request->user();
        $data = $request->validate([
            'research_type_id' => 'required|integer|exists:research_types,id', 'department_id' => 'required|integer|exists:departments,id',
            'title' => 'required|string|max:255', 'research_area' => 'nullable|string|max:255', 'keywords' => 'nullable', 'abstract' => 'nullable|string',
            'supervisor_id' => 'nullable|integer|exists:users,id', 'co_supervisor_id' => 'nullable|integer|different:supervisor_id|exists:users,id',
            'expected_completion_date' => 'nullable|date|after_or_equal:today',
        ]);
        $type = ResearchType::findOrFail($data['research_type_id']);
        abort_unless($type->university_id === null || $user->isSuperAdmin() || $type->university_id === $user->university_id, 403);
        if (! $user->isAdmin() && $user->department_id) {
            $data['department_id'] = $user->department_id;
        }
        $department = Department::with('faculty')->findOrFail($data['department_id']);
        abort_unless($user->isSuperAdmin() || $department->faculty?->university_id === $user->university_id, 403);
        $data['university_id'] = $department->faculty?->university_id;
        foreach (['supervisor_id', 'co_supervisor_id'] as $field) {
            if (! empty($data[$field])) {
                $supervisor = User::findOrFail($data[$field]);
                abort_unless($supervisor->isLecturer() && ($user->isSuperAdmin() || $supervisor->university_id === $data['university_id']), 422);
            }
        }

        return response()->json($projects->create($data, $user), 201);
    }

    public function show(Request $request, ResearchProject $research)
    {
        $this->authorize('view', $research);
        return $research->load([
            'researchType', 'templateVersion', 'owner', 'supervisor', 'coSupervisor', 'department', 'memberRecords.user',
            'sections.document.versions.author', 'sections.document.comments.user', 'workflowInstance.currentStage', 'workflowInstance.definition.stages',
            'workflowInstance.transitions.actor', 'latestValidationReport', 'corrections', 'meetings.attendees', 'milestones', 'tasks', 'archives', 'publications',
        ]);
    }

    public function update(Request $request, ResearchProject $research)
    {
        $this->authorize('update', $research);
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255', 'research_area' => 'nullable|string|max:255', 'keywords' => 'nullable',
            'abstract' => 'nullable|string', 'expected_completion_date' => 'nullable|date',
        ]);
        if (array_key_exists('keywords', $data)) $data['keywords'] = is_array($data['keywords']) ? $data['keywords'] : array_values(array_filter(array_map('trim', explode(',', (string) $data['keywords']))));
        $research->update($data);
        return response()->json($research->fresh());
    }

    public function updateSection(Request $request, ResearchProject $research, ResearchSection $section, ContentWorkspaceService $workspace)
    {
        $this->authorize('update', $research);
        abort_unless($section->research_project_id === $research->id && ! $section->locked_at, 423, 'This section is locked or outside the project.');
        $data = $request->validate(['body' => 'required|string|max:2000000', 'summary' => 'nullable|string|max:255']);
        $workspace->autosave($section->document, $request->user(), $data['body'], $data['summary'] ?? 'API section autosave');
        $section->update(['status' => $section->status === 'approved' ? 'approved' : 'in_progress', 'completion_percent' => trim(strip_tags($data['body'])) === '' ? 0 : 50]);
        app(ResearchProjectService::class)->recalculateProgress($research);
        return response()->json($section->fresh('document.versions'));
    }

    public function transition(Request $request, ResearchProject $research, WorkflowService $workflows)
    {
        $this->authorize('transition', $research);
        $data = $request->validate(['target_stage' => 'required|string|max:100', 'action' => 'nullable|string|max:100', 'note' => 'nullable|string|max:2000']);
        abort_unless($research->workflowInstance, 422, 'No configured workflow exists.');
        $instance = $workflows->transition($research->workflowInstance, $data['target_stage'], $request->user(), $data['action'] ?? 'advance', $data['note'] ?? null);
        $stage = $instance->currentStage;
        $updates = ['status' => $stage->key];
        if ($stage->is_final) {
            $updates += str_contains($stage->key, 'archive')
                ? ['status' => 'archived', 'archived_at' => now(), 'approved_at' => $research->approved_at ?? now()]
                : ['status' => 'approved', 'approved_at' => now()];
        }
        $research->update($updates);
        return response()->json($research->fresh('workflowInstance.currentStage'));
    }

    public function validateProject(Request $request, ResearchProject $research, ResearchValidationService $validation)
    {
        $this->authorize('validate', $research);
        return response()->json($validation->queue($research, $request->user()), 202);
    }

    public function publish(Request $request, ResearchProject $research, ResearchPublicationService $publishing)
    {
        $this->authorize('publish', $research);
        $data = $request->validate(['title' => 'nullable|string|max:255', 'excerpt' => 'nullable|string|max:2000', 'category_id' => 'nullable|integer|exists:knowledge_categories,id', 'visibility' => 'required|in:public,institution']);
        return response()->json($publishing->createPublication($research, $request->user(), $data), 201);
    }
}
