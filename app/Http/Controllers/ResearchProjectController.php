<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\KnowledgeCategory;
use App\Models\ResearchProject;
use App\Models\ResearchProjectInvitation;
use App\Models\ResearchType;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\AcademicReferenceService;
use App\Services\IndependentAcademicContextService;
use App\Services\ResearchInvitationService;
use App\Services\ResearchProjectService;
use App\Services\ResearchWritingExperienceService;
use App\Services\ResearchPublicationService;
use App\Services\ResearchValidationService;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ResearchProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ResearchProject::class);
        $user = $request->user();
        $institutionExpansionEnabled = FeatureAccessService::effectiveStatus('research_institutional_expansion', $user->university_id) === FeatureAccessService::STATUS_ENABLED;
        $institutionPreview = $institutionExpansionEnabled || $user->isAdmin();

        $projectRelations = ['researchType', 'owner', 'workflowInstance.currentStage'];
        if ($institutionPreview) {
            $projectRelations[] = 'supervisor';
        }

        $projects = ResearchProject::query()
            ->with($projectRelations)->withCount('referenceLinks')
            ->when(! $user->isSuperAdmin(), function ($query) use ($user, $institutionPreview): void {
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
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $pendingInvitations = $institutionExpansionEnabled
            ? ResearchProjectInvitation::query()
                ->with(['project.owner', 'inviter'])
                ->where('invitee_id', $user->id)
                ->where('status', 'pending')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest()
                ->get()
            : collect();

        return view('research.index', compact('projects', 'pendingInvitations', 'institutionExpansionEnabled'));
    }

    public function create(Request $request, IndependentAcademicContextService $independent): View
    {
        $this->authorize('create', ResearchProject::class);
        $user = $request->user();

        if (! ResearchType::query()->where('is_active', true)->whereNull('university_id')->exists()) {
            $independent->ensureResearchType();
        }

        $types = ResearchType::query()
            ->select(['id', 'name'])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('university_id')->orWhere('university_id', $user->university_id))
            ->orderBy('name')
            ->get();

        $institutionExpansionEnabled = FeatureAccessService::effectiveStatus('research_institutional_expansion', $user->university_id) === FeatureAccessService::STATUS_ENABLED;
        $departments = collect();
        $supervisors = collect();

        // Institutional options are optional. Keep the initial independent form
        // small; fetch these only when institutional mode is selected. Retain
        // server rendering after validation failures and for the no-JS fallback.
        if ($institutionExpansionEnabled && $user->university_id && ($request->boolean('institutional') || $request->session()->getOldInput('institutional_project') === '1' || $request->session()->getOldInput('workspace_mode') === 'institutional')) {
            $departments = Department::query()
                ->when(! $user->isSuperAdmin(), fn ($query) => $query->whereHas('faculty', fn ($faculty) => $faculty->where('university_id', $user->university_id)))
                ->when($user->department_id && ! $user->isAdmin(), fn ($query) => $query->whereKey($user->department_id))
                ->orderBy('name')
                ->get(['id', 'name']);

            $supervisors = User::query()
                ->with('department:id,name')
                ->where('role', 'lecturer')
                ->where('is_active', true)
                ->where('university_id', $user->university_id)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->limit(150)
                ->get(['id', 'first_name', 'last_name', 'department_id']);
        }

        return view('research.create', compact('types', 'departments', 'supervisors', 'institutionExpansionEnabled'));
    }

    public function creationOptions(Request $request): JsonResponse
    {
        $this->authorize('create', ResearchProject::class);
        $user = $request->user();
        abort_unless($user->university_id && FeatureAccessService::effectiveStatus('research_institutional_expansion', $user->university_id) === FeatureAccessService::STATUS_ENABLED, 403);
        $data = $request->validate([
            'kind' => ['required', 'in:departments,supervisors'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $term = trim($data['q'] ?? '');
        if ($data['kind'] === 'departments') {
            $query = Department::query()
                ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereHas('faculty', fn ($faculty) => $faculty->where('university_id', $user->university_id)))
                ->when($user->department_id && ! $user->isAdmin(), fn ($q) => $q->whereKey($user->department_id))
                ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.$term.'%'))
                ->orderBy('name')->orderBy('id');
            $page = $query->simplePaginate(25, ['id', 'name']);
            $options = $page->getCollection()->map(fn ($department) => ['id' => $department->id, 'label' => $department->name]);
        } else {
            $query = User::query()->with('department:id,name')
                ->where('is_active',true)->whereHas('institutionAffiliations',fn($q)=>$q->approved()->where('affiliation_type','lecturer')->where('university_id',$user->university_id))
                ->when($term !== '', fn ($q) => $q->where(fn ($names) => $names->where('first_name', 'like', '%'.$term.'%')->orWhere('last_name', 'like', '%'.$term.'%')))
                ->orderBy('first_name')->orderBy('last_name')->orderBy('id');
            $page = $query->simplePaginate(25, ['id', 'first_name', 'last_name', 'department_id']);
            $options = $page->getCollection()->map(fn ($supervisor) => ['id' => $supervisor->id, 'label' => $supervisor->full_name.($supervisor->department?->name ? ' · '.$supervisor->department->name : '')]);
        }

        return response()->json(['options' => $options, 'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, ResearchProjectService $projects, IndependentAcademicContextService $independent, ResearchInvitationService $invitations): RedirectResponse
    {
        $this->authorize('create', ResearchProject::class);
        $user = $request->user();
        $data = $request->validate([
            'research_type_id' => ['required', 'integer', 'exists:research_types,id'],
            'workspace_mode' => ['nullable', 'in:independent,institutional'],
            'institutional_project' => ['nullable', 'boolean'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'research_area' => ['nullable', 'string', 'max:255'],
            'keywords' => ['nullable', 'string', 'max:1000'],
            'abstract' => ['nullable', 'string'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_invitee_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_message' => ['nullable', 'string', 'max:1000'],
            'co_supervisor_id' => ['nullable', 'integer', 'different:supervisor_id', 'exists:users,id'],
            'expected_completion_date' => ['nullable', 'date', 'after_or_equal:today'],
        ] + app(\App\Services\ResearchMethodologyService::class)->rules());

        $type = ResearchType::findOrFail($data['research_type_id']);
        abort_unless($type->university_id === null || $type->university_id === $user->university_id || $user->isSuperAdmin(), 403);

        $institutionExpansionEnabled = FeatureAccessService::effectiveStatus('research_institutional_expansion', $user->university_id) === FeatureAccessService::STATUS_ENABLED;
        $requestedInstitutional = $request->boolean('institutional_project') || (($data['workspace_mode'] ?? null) === 'institutional');

        if (! $institutionExpansionEnabled) {
            abort_if(
                $requestedInstitutional || ! empty($data['department_id']) || ! empty($data['supervisor_invitee_id']) || ! empty($data['supervisor_id']),
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

        if ($workspaceMode === 'independent') {
            $context = $independent->context();
            $data['university_id'] = $context['university']->id;
            $data['department_id'] = $context['department']->id;
            $data['academic_session_id'] = $context['session']->id;
            $data['semester_id'] = $context['semester']->id;
        } else {
            abort_unless($user->university_id, 422, 'Connect an institution before creating an institutional research workspace.');
            abort_unless(! empty($data['department_id']), 422, 'Select a department for an institutional research workspace.');
            $department = Department::with('faculty')->findOrFail($data['department_id']);
            if($user->isAdmin())abort_unless(app(\App\Services\AcademicOrganisationService::class)->canManageDepartment($user,$department),403);
            abort_unless($user->isSuperAdmin() || $department->faculty?->university_id === $user->university_id, 403);
            $data['university_id'] = $department->faculty?->university_id;
            if (! $user->isAdmin() && $user->department_id) {
                $data['department_id'] = $user->department_id;
            }
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

        if ($supervisorInvitee) {
            $invitations->inviteSupervisor($project, $user, $supervisorInvitee, $supervisorMessage);
        }

        $message = $supervisorInviteeId
            ? 'Research workspace created and the supervisor invitation was sent for acceptance.'
            : 'Research workspace created successfully.';

        return redirect()->route('research.show', $project)->with('success', $message);
    }

    public function show(Request $request, ResearchProject $research, AcademicReferenceService $referenceService, ResearchWritingExperienceService $writing): View
    {
        $this->authorize('view', $research);
        $institutionExpansionEnabled = FeatureAccessService::effectiveStatus('research_institutional_expansion', auth()->user()?->university_id) === FeatureAccessService::STATUS_ENABLED;
        $publishingEnabled = FeatureAccessService::effectiveStatus('research_to_knowledge_hub', auth()->user()?->university_id) === FeatureAccessService::STATUS_ENABLED;
        $literatureEnabled = FeatureAccessService::effectiveStatus('research_literature', auth()->user()?->university_id) === FeatureAccessService::STATUS_ENABLED;
        $datasetsEnabled = FeatureAccessService::effectiveStatus('research_datasets', auth()->user()?->university_id) === FeatureAccessService::STATUS_ENABLED;

        $relations = [
            'researchType', 'owner',
            'sections.document',
            'workflowInstance.currentStage', 'workflowInstance.definition.stages',
            'workflowInstance.transitions.actor', 'latestValidationReport', 'corrections',
        ];
        if ($institutionExpansionEnabled || auth()->user()?->isAdmin()) {
            array_push($relations, 'supervisor', 'coSupervisor', 'department', 'invitations.invitee');
        }
        if ($publishingEnabled) {
            $relations[] = 'publications';
        }
        if ($literatureEnabled) {
            $relations[] = 'templateVersion';
        }
        $research->load($relations);

        $pendingSupervisorInvitation = null;
        $supervisorCandidates = collect();
        if ($institutionExpansionEnabled && $research->workspace_mode === 'institutional') {
            $pendingSupervisorInvitation = $research->invitations
                ->where('role', 'supervisor')
                ->where('status', 'pending')
                ->filter(fn ($invitation) => ! $invitation->expires_at || $invitation->expires_at->isFuture())
                ->sortByDesc('created_at')
                ->first();

            if (! $research->supervisor_id && ! $pendingSupervisorInvitation && auth()->user()?->can('update', $research)) {
                $supervisorCandidates = User::query()
                    ->with('department')
                    ->where('role', 'lecturer')
                    ->where('is_active', true)
                    ->where('university_id', $research->university_id)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->limit(150)
                    ->get();
            }
        }

        $categories = $publishingEnabled
            ? KnowledgeCategory::query()
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('university_id')->orWhere('university_id', $research->university_id))
                ->orderBy('name')
                ->get()
            : collect();

        $sectionJourney = $writing->journey($research);
        $activeSection = $writing->activeSection($research, $request->query('section'));
        $sectionLabel = strtolower(($activeSection?->key ?? '').' '.($activeSection?->title ?? ''));
        $needsLiterature = str_contains($sectionLabel, 'chapter_2') || str_contains($sectionLabel, 'literature') || str_contains($sectionLabel, 'reference') || str_contains($sectionLabel, 'bibliograph');
        if ($literatureEnabled && $needsLiterature) {
            $research->load([
                'referenceLinks.reference',
                'literatureNotes' => fn ($query) => $query->where('created_by', $request->user()->id)->where('reading_status', 'reviewed')->with('reference'),
            ]);
        }
        if ($datasetsEnabled && (str_contains($sectionLabel, 'chapter_4') || str_contains($sectionLabel, 'result') || str_contains($sectionLabel, 'analysis'))) {
            $research->load('datasets');
        }
        $sectionVersions = null;
        $sectionComments = null;
        if ($activeSection) {
            $activeSection->setRelation('project', $research);
            if ($activeSection->document) {
                // Version bodies grow on every autosave. Never hydrate histories
                // for chapters that are not currently being edited.
                $sectionVersions = $activeSection->document->versions()->paginate(5, ['*'], 'versions_page')->withQueryString();
                $sectionComments = $activeSection->document->comments()->with('user')->paginate(25, ['*'], 'comments_page')->withQueryString();
            }
        }
        $writingGuidance = $writing->guidance($activeSection);
        $nextWritingAction = $writing->nextAction($research);

        $citationStyle = strtolower((string) (data_get($research->metadata, 'citation_style', $research->templateVersion?->citation_style ?? 'apa')));
        $chapterTwoLiterature = collect();
        if ($literatureEnabled && $needsLiterature) {
            $linkedReferenceIds = $research->referenceLinks->pluck('academic_reference_id')->flip();
            $chapterTwoLiterature = $research->literatureNotes
                ->where('created_by', auth()->id())
                ->filter(fn ($note) => ($note->reading_status ?? 'saved') === 'reviewed' && $note->reference && $linkedReferenceIds->has($note->academic_reference_id))
                ->values()
                ->map(function ($note, int $index) use ($referenceService, $citationStyle, $research): array {
                    $reference = $note->reference;
                    return [
                        'reference' => $reference,
                        'note' => $note,
                        'citation' => $referenceService->inText($reference, $citationStyle, $index + 1),
                        'bibliography' => $referenceService->formatForProject($reference, $research, $index + 1),
                    ];
                });
        }

        return view('research.show', compact(
            'research', 'categories', 'institutionExpansionEnabled', 'publishingEnabled', 'literatureEnabled', 'datasetsEnabled',
            'pendingSupervisorInvitation', 'supervisorCandidates', 'chapterTwoLiterature', 'citationStyle',
            'sectionJourney', 'activeSection', 'writingGuidance', 'nextWritingAction', 'sectionVersions', 'sectionComments'
        ));
    }

    public function inviteSupervisor(Request $request, ResearchProject $research, ResearchInvitationService $invitations): RedirectResponse
    {
        $this->authorize('update', $research);
        abort_unless(
            FeatureAccessService::effectiveStatus('research_institutional_expansion', $request->user()?->university_id) === FeatureAccessService::STATUS_ENABLED,
            403,
            'Institution-linked research is currently disabled by the platform administrator.'
        );
        abort_unless($research->workspace_mode === 'institutional', 422, 'Supervisor invitations require an institutional research workspace.');
        abort_if($research->supervisor_id, 422, 'This project already has an accepted supervisor.');
        abort_if(
            $research->invitations()->where('role', 'supervisor')->where('status', 'pending')->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists(),
            422,
            'A supervisor invitation is already pending for this project.'
        );

        $data = $request->validate([
            'supervisor_invitee_id' => ['required', 'integer', 'exists:users,id'],
            'supervisor_message' => ['nullable', 'string', 'max:1000'],
        ]);

        $invitations->inviteSupervisor(
            $research,
            $request->user(),
            User::findOrFail($data['supervisor_invitee_id']),
            $data['supervisor_message'] ?? null
        );

        return back()->with('success', 'Supervisor invitation sent. Supervision becomes active only after the lecturer accepts.');
    }

    public function respondInvitation(Request $request, ResearchProjectInvitation $invitation, ResearchInvitationService $invitations): RedirectResponse
    {
        abort_unless(FeatureAccessService::effectiveStatus('research_institutional_expansion', $request->user()?->university_id) === FeatureAccessService::STATUS_ENABLED, 403);
        $data = $request->validate(['decision' => ['required', 'in:accept,decline']]);
        $result = $invitations->respond($invitation, $request->user(), $data['decision'] === 'accept');

        return redirect()->route('research.index')->with(
            'success',
            $result->status === 'accepted' ? 'Research supervision invitation accepted.' : 'Research supervision invitation declined.'
        );
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
