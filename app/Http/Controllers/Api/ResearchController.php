<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\ResearchProject;
use App\Models\ResearchSection;
use App\Models\ResearchType;
use App\Models\User;
use App\Services\ContentWorkspaceService;
use App\Services\FeatureAccessService;
use App\Services\IndependentAcademicContextService;
use App\Services\ResearchInvitationService;
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
        $institutionExpansionEnabled = FeatureAccessService::effectiveStatus('research_institutional_expansion', $user->university_id) === FeatureAccessService::STATUS_ENABLED;
        $institutionPreview = $institutionExpansionEnabled || $user->isAdmin();
        $relations = ['researchType', 'owner', 'workflowInstance.currentStage'];
        if ($institutionPreview) {
            $relations[] = 'supervisor';
        }
        $query = ResearchProject::query()->with($relations);

        if (! $user->isSuperAdmin()) {
            $query->where(function ($scope) use ($user, $institutionPreview): void {
                if ($institutionPreview && $user->university_id) {
                    $scope->where(function ($institutional) use ($user): void {
                        $institutional->where('workspace_mode', 'institutional')
                            ->where('university_id', $user->university_id);
                        if ($user->isDepartmentAdmin()) {
                            $institutional->where('department_id', $user->department_id);
                        }
                    });
                } else {
                    $scope->whereRaw('1 = 0');
                }

                $scope->orWhere(function ($independent) use ($user): void {
                    $independent->where('workspace_mode', 'independent')
                        ->where(function ($participant) use ($user): void {
                            $participant->where('owner_id', $user->id)
                                ->orWhere('supervisor_id', $user->id)
                                ->orWhere('co_supervisor_id', $user->id)
                                ->orWhereHas('memberRecords', fn ($members) => $members->where('user_id', $user->id));
                        });
                });
            });
        }

        $query->when($request->filled('status'), fn ($scope) => $scope->where('status', $request->string('status')->toString()));

        return $query->latest('updated_at')->paginate(20);
    }

    public function store(
        Request $request,
        ResearchProjectService $projects,
        IndependentAcademicContextService $independent,
        ResearchInvitationService $invitations
    ) {
        $this->authorize('create', ResearchProject::class);
        $user = $request->user();
        $data = $request->validate([
            'research_type_id' => 'nullable|integer|exists:research_types,id',
            'workspace_mode' => 'nullable|in:independent,institutional',
            'institutional_project' => 'nullable|boolean',
            'department_id' => 'nullable|integer|exists:departments,id',
            'title' => 'required|string|max:255',
            'research_area' => 'nullable|string|max:255',
            'keywords' => 'nullable',
            'abstract' => 'nullable|string',
            // supervisor_id remains accepted for backwards-compatible clients, but is converted to an invitation.
            'supervisor_id' => 'nullable|integer|exists:users,id',
            'supervisor_invitee_id' => 'nullable|integer|exists:users,id',
            'supervisor_message' => 'nullable|string|max:1000',
            'co_supervisor_id' => 'nullable|integer|exists:users,id',
            'expected_completion_date' => 'nullable|date|after_or_equal:today',
        ] + app(\App\Services\ResearchMethodologyService::class)->rules());

        $institutionExpansionEnabled = FeatureAccessService::effectiveStatus(
            'research_institutional_expansion',
            $user->university_id
        ) === FeatureAccessService::STATUS_ENABLED;
        $requestedInstitutional = $request->boolean('institutional_project')
            || (($data['workspace_mode'] ?? null) === 'institutional');

        if (! $institutionExpansionEnabled) {
            abort_if(
                $requestedInstitutional
                || ! empty($data['department_id'])
                || ! empty($data['supervisor_invitee_id'])
                || ! empty($data['supervisor_id'])
                || ! empty($data['co_supervisor_id']),
                403,
                'Institution-linked research is currently disabled by the platform administrator.'
            );
            $workspaceMode = 'independent';
        } else {
            $workspaceMode = ($requestedInstitutional && $user->university_id) ? 'institutional' : 'independent';
        }
        $data['workspace_mode'] = $workspaceMode;

        $supervisorInviteeId = $data['supervisor_invitee_id'] ?? $data['supervisor_id'] ?? null;
        $supervisorMessage = $data['supervisor_message'] ?? null;
        unset($data['institutional_project'], $data['supervisor_invitee_id'], $data['supervisor_message'], $data['supervisor_id'], $data['co_supervisor_id']);

        if (empty($data['research_type_id']) && $workspaceMode === 'independent') {
            $data['research_type_id'] = $independent->ensureResearchType()->id;
        }
        abort_unless(! empty($data['research_type_id']), 422, 'Research type is required for institutional research workspaces.');

        $type = ResearchType::findOrFail($data['research_type_id']);
        abort_unless($type->university_id === null || $user->isSuperAdmin() || $type->university_id === $user->university_id, 403);

        if ($workspaceMode === 'independent') {
            $context = $independent->context();
            $data['university_id'] = $context['university']->id;
            $data['department_id'] = $context['department']->id;
            $data['academic_session_id'] = $context['session']->id;
            $data['semester_id'] = $context['semester']->id;
        } else {
            abort_unless($user->university_id, 422, 'Connect an institution before creating an institutional research workspace.');
            abort_unless(! empty($data['department_id']), 422, 'Department is required for an institutional research workspace.');
            if (! $user->isAdmin() && $user->department_id) {
                $data['department_id'] = $user->department_id;
            }
            $department = Department::with('faculty')->findOrFail($data['department_id']);
            if($user->isAdmin())abort_unless(app(\App\Services\AcademicOrganisationService::class)->canManageDepartment($user,$department),403);
            abort_unless($user->isSuperAdmin() || $department->faculty?->university_id === $user->university_id, 403);
            $data['university_id'] = $department->faculty?->university_id;
        }

        $supervisorInvitee = null;
        if ($supervisorInviteeId) {
            abort_unless($institutionExpansionEnabled && $workspaceMode === 'institutional', 422, 'Supervisor invitations require institutional research mode.');
            $supervisorInvitee = User::findOrFail($supervisorInviteeId);
            abort_unless(app(\App\Services\AcademicOrganisationService::class)->applyMembershipRole($supervisorInvitee,(int)$data['university_id'],null,'lecturer'),422,'Approve the lecturer membership before inviting supervision.');
            abort_unless(
                $supervisorInvitee->is_active && $supervisorInvitee->isLecturer() && $supervisorInvitee->university_id === $data['university_id'],
                422,
                'Choose an active lecturer from the same institution as this project.'
            );
        }

        $project = $projects->create($data, $user);
        $invitation = $supervisorInvitee
            ? $invitations->inviteSupervisor($project, $user, $supervisorInvitee, $supervisorMessage)
            : null;

        $payload = $project->fresh(['researchType', 'owner']);
        if ($invitation) {
            $payload->setRelation('invitations', collect([$invitation->load('invitee')]));
        }

        return response()->json($payload, 201);
    }

    public function show(Request $request, ResearchProject $research)
    {
        $this->authorize('view', $research);
        $user = $request->user();
        $enabled = fn (string $feature): bool => FeatureAccessService::effectiveStatus($feature, $user->university_id) === FeatureAccessService::STATUS_ENABLED;
        $institutional = $enabled('research_institutional_expansion');

        $relations = [
            'researchType', 'templateVersion', 'owner',
            'sections.document.versions.author', 'sections.document.comments.user',
            'workflowInstance.currentStage', 'workflowInstance.definition.stages',
            'workflowInstance.transitions.actor', 'latestValidationReport', 'corrections', 'milestones', 'tasks',
        ];
        if ($institutional) {
            array_push($relations, 'supervisor', 'coSupervisor', 'department');
        }
        if ($enabled('research_collaboration')) {
            $relations[] = 'memberRecords.user';
        }
        if ($enabled('research_meetings')) {
            $relations[] = 'meetings.attendees';
        }
        if ($enabled('research_archives')) {
            $relations[] = 'archives';
        }
        if ($enabled('research_datasets')) {
            $relations[] = 'datasets';
        }
        if ($enabled('research_to_knowledge_hub')) {
            $relations[] = 'publications';
        }

        $research->load($relations);
        if (! $institutional && ! $user->isAdmin()) {
            $research->makeHidden(['university_id', 'department_id', 'supervisor_id', 'co_supervisor_id']);
        }

        return response()->json($research);
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
        if ($request->filled('summary')) $request->merge(['change_summary' => $request->input('summary')]);
        app()->call([app(\App\Http\Controllers\ResearchSectionController::class), 'save'], [
            'request' => $request, 'research' => $research, 'section' => $section,
        ]);
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
