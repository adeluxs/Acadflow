<?php

namespace App\Http\Controllers;

use App\Models\AcademicReference;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\Media\MediaSecurityService;
use App\Services\Media\SafeFileDeliveryService;
use App\Services\ContentWorkspaceService;
use App\Services\AcademicReferenceService;
use App\Services\LiteratureSynthesisService;
use App\Services\FeatureAccessService;
use App\Models\Submission;
use App\Models\ResearchSection;
use App\Models\ResearchDataset;
use App\Models\ResearchCorrection;
use App\Models\ContentVersion;
use App\Models\ResearchActionItem;
use App\Models\ResearchAmendment;
use App\Models\ResearchArchive;
use App\Models\ResearchMeeting;
use App\Models\ResearchMilestone;
use App\Models\ResearchProject;
use App\Models\ResearchTask;
use App\Models\ResearchTemplateVersion;
use App\Models\ResearchType;
use App\Models\ScholarlyRecord;
use App\Models\User;
use App\Services\ResearchArchiveService;
use App\Services\ResearchCollaborationService;
use App\Services\ResearchMeetingService;
use App\Services\ResearchTemplateService;
use App\Services\ResearchWritingExperienceService;
use App\Services\Scholarly\ScholarlyDiscoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ResearchWorkspaceController extends Controller
{
    public function controlCenter(Request $request, ResearchProject $research, ResearchCollaborationService $collaboration, ResearchWritingExperienceService $writing): View
    {
        $this->authorize('view', $research);
        $user = $request->user();
        $isEnabled = fn (string $feature): bool => FeatureAccessService::effectiveStatus($feature, $user->university_id) === FeatureAccessService::STATUS_ENABLED;
        $modules = [
            'literature' => $isEnabled('research_literature'),
            'institutional' => $isEnabled('research_institutional_expansion'),
            'meetings' => $isEnabled('research_meetings'),
            'collaboration' => $isEnabled('research_collaboration'),
            'archives' => $isEnabled('research_archives'),
            'datasets' => $isEnabled('research_datasets'),
            'siwes' => $isEnabled('siwes_module'),
            'seminar' => $isEnabled('seminar_module'),
        ];

        $relations = ['owner', 'milestones', 'tasks', 'templateVersion', 'sections.document'];
        if ($modules['institutional']) {
            $relations[] = 'supervisor';
            $relations[] = 'coSupervisor';
        }
        if ($modules['literature']) {
            $relations[] = 'referenceLinks.reference';
        }
        if ($modules['meetings']) {
            $relations[] = 'meetings.attendees.user';
            $relations[] = 'meetings.actionItemRecords.assignee';
        }
        if ($modules['collaboration']) {
            $relations[] = 'memberRecords.user';
            $relations[] = 'sections.authorships';
        }
        if ($modules['archives']) {
            $relations[] = 'archives';
            $relations[] = 'amendments';
        }
        if ($modules['datasets']) {
            $relations[] = 'datasets';
        }
        if ($modules['siwes'] || $modules['seminar']) {
            $relations[] = 'specializedLinks';
        }

        $research->load($relations);

        $participants = collect([$research->owner])
            ->when($modules['institutional'], fn ($items) => $items->concat([$research->supervisor, $research->coSupervisor]))
            ->filter()
            ->when($modules['collaboration'], fn ($items) => $items->concat($research->memberRecords->pluck('user')))
            ->unique('id')
            ->values();

        $candidateMembers = collect();
        if ($modules['collaboration'] && $modules['institutional'] && $research->workspace_mode === 'institutional') {
            $candidateMembers = User::query()
                ->where('university_id', $research->university_id)
                ->where('is_active', true)
                ->where('id', '!=', $research->owner_id)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->limit(200)
                ->get();
        }

        $contributions = $modules['collaboration'] ? $collaboration->contributionReport($research) : [];
        $workspaceStats = [
            'open_tasks' => $research->tasks->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'references' => $modules['literature'] ? $research->referenceLinks->count() : null,
            'next_milestone' => $research->milestones->whereNotIn('status', ['completed'])->sortBy('due_at')->first(),
            'approved_sections' => $research->sections->where('status', 'approved')->count(),
            'total_sections' => $research->sections->count(),
        ];
        $sectionJourney = $writing->journey($research);
        $nextWritingAction = $writing->nextAction($research);
        $literatureStatus = ['saved' => 0, 'reading' => 0, 'reviewed' => 0];
        if ($modules['literature']) {
            $literatureStatus = $research->literatureNotes()
                ->where('created_by', $user->id)
                ->selectRaw("COALESCE(reading_status, 'saved') AS status_key, COUNT(*) AS aggregate")
                ->groupBy('status_key')
                ->pluck('aggregate', 'status_key')
                ->map(fn ($value) => (int) $value)
                ->union($literatureStatus)
                ->all();
        }

        return view('research.workspace', compact('research', 'participants', 'candidateMembers', 'contributions', 'modules', 'workspaceStats', 'sectionJourney', 'nextWritingAction', 'literatureStatus'));
    }

    public function templates(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);
        $types = ResearchType::query()->select(['id', 'uuid', 'name'])->with('templateVersions:id,uuid,research_type_id,version,name,citation_style,is_active')->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->where(fn ($s) => $s->whereNull('university_id')->orWhere('university_id', $request->user()->university_id)))->orderBy('name')->paginate(15)->withQueryString();
        return view('research.templates', compact('types'));
    }

    public function storeTemplate(Request $request, ResearchType $type, ResearchTemplateService $templates): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() && ($request->user()->isSuperAdmin() || $type->university_id === null || $type->university_id === $request->user()->university_id), 403);
        $data = $request->validate(['name' => ['required','string','max:255'], 'template_schema' => ['required'], 'validation_rules' => ['nullable'], 'citation_style' => ['required','in:apa,mla,chicago,harvard,ieee,vancouver'], 'is_active' => ['nullable','boolean'], 'effective_from' => ['nullable','date']]);
        foreach (['template_schema','validation_rules'] as $field) if (is_string($data[$field] ?? null) && str_starts_with(trim($data[$field]), '{')) $data[$field] = json_decode($data[$field], true, flags: JSON_THROW_ON_ERROR);
        $templates->createVersion($type, $data + ['is_active' => $request->boolean('is_active', true)], $request->user());
        return back()->with('success', 'A new immutable research template version was created.');
    }

    public function activateTemplate(Request $request, ResearchTemplateVersion $template, ResearchTemplateService $templates): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() && ($request->user()->isSuperAdmin() || $template->researchType?->university_id === null || $template->researchType?->university_id === $request->user()->university_id), 403);
        $templates->activate($template);
        return back()->with('success', 'Template version activated for future projects.');
    }

    public function scheduleMeeting(Request $request, ResearchProject $research, ResearchMeetingService $meetings): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_meetings', $request->user());
        $this->authorize('transition', $research);
        $data = $request->validate(['scheduled_at' => ['required','date','after:now'], 'duration_minutes' => ['required','integer','min:15','max:480'], 'location' => ['nullable','string','max:255'], 'online_url' => ['nullable','url','max:2000'], 'agenda' => ['nullable','string','max:10000'], 'notes' => ['nullable','string','max:10000'], 'attendee_ids' => ['nullable','array'], 'attendee_ids.*' => ['integer','exists:users,id']]);
        $meetings->schedule($research, $request->user(), $data);
        return back()->with('success', 'Research meeting scheduled with attendee reminders.');
    }

    public function completeMeeting(Request $request, ResearchProject $research, ResearchMeeting $meeting, ResearchMeetingService $meetings): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_meetings', $request->user());
        $this->authorize('transition', $research); abort_unless($meeting->research_project_id === $research->id, 404);
        $data = $request->validate(['notes' => ['nullable','string','max:20000'], 'attendance' => ['nullable','array'], 'action_items' => ['nullable','array'], 'action_items.*.title' => ['required_with:action_items','string','max:255'], 'action_items.*.assigned_to' => ['nullable','integer','exists:users,id'], 'action_items.*.description' => ['nullable','string'], 'action_items.*.due_at' => ['nullable','date']]);
        $meetings->complete($meeting, $data);
        return back()->with('success', 'Meeting notes, attendance, and action items saved.');
    }

    public function calendar(ResearchProject $research, ResearchMeeting $meeting, ResearchMeetingService $meetings): Response
    {
        $this->assertResearchFeatureEnabled('research_meetings', auth()->user());
        $this->authorize('view', $research); abort_unless($meeting->research_project_id === $research->id, 404);
        return response($meetings->ics($meeting), 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="research-meeting-'.$meeting->uuid.'.ics"']);
    }

    public function storeTask(Request $request, ResearchProject $research): RedirectResponse
    {
        $this->authorize('transition', $research);
        $data = $request->validate(['research_section_id' => ['nullable','integer','exists:research_sections,id'], 'assigned_to' => ['nullable','integer','exists:users,id'], 'title' => ['required','string','max:255'], 'description' => ['nullable','string'], 'priority' => ['required','in:low,normal,high,urgent'], 'due_at' => ['nullable','date']]);
        if ($data['research_section_id'] ?? null) abort_unless($research->sections()->whereKey($data['research_section_id'])->exists(), 422);
        if ($data['assigned_to'] ?? null) abort_unless($research->memberRecords()->where('user_id', $data['assigned_to'])->exists() || in_array($data['assigned_to'], [$research->owner_id,$research->supervisor_id,$research->co_supervisor_id]), 422);
        $research->tasks()->create($data + ['assigned_by' => $request->user()->id]);
        return back()->with('success', 'Research task assigned.');
    }

    public function updateTask(Request $request, ResearchProject $research, ResearchTask $task): RedirectResponse
    {
        $this->authorize('view', $research); abort_unless($task->research_project_id === $research->id, 404);
        abort_unless($request->user()->isAdmin() || $task->assigned_to === $request->user()->id || $task->assigned_by === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required','in:open,in_progress,blocked,completed,cancelled']]);
        $task->update($data + ['completed_at' => $data['status'] === 'completed' ? now() : null]);
        return back()->with('success', 'Task status updated.');
    }

    public function completeActionItem(Request $request, ResearchProject $research, ResearchActionItem $item): RedirectResponse
    {
        $this->authorize('view', $research); abort_unless($item->meeting?->research_project_id === $research->id, 404);
        abort_unless($request->user()->isAdmin() || $item->assigned_to === $request->user()->id || $research->supervisor_id === $request->user()->id, 403);
        $item->update(['status' => 'completed', 'completed_at' => now()]);
        return back()->with('success', 'Action item completed.');
    }

    public function updateMilestone(Request $request, ResearchProject $research, ResearchMilestone $milestone): RedirectResponse
    {
        $this->authorize('transition', $research); abort_unless($milestone->research_project_id === $research->id, 404);
        $data = $request->validate(['status' => ['required','in:pending,in_progress,completed,blocked'], 'due_at' => ['nullable','date']]);
        $milestone->update($data + ['completed_at' => $data['status'] === 'completed' ? now() : null, 'completed_by' => $data['status'] === 'completed' ? $request->user()->id : null]);
        app(\App\Services\ResearchProjectService::class)->recalculateProgress($research);
        return back()->with('success', 'Milestone updated.');
    }

    public function syncMember(Request $request, ResearchProject $research, ResearchCollaborationService $collaboration): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_collaboration', $request->user());
        $this->authorize('transition', $research);
        $data = $request->validate(['user_id' => ['required','integer','exists:users,id'], 'role' => ['required','in:lead_author,author,data_analyst,research_assistant,advisor'], 'permissions' => ['required','array','min:1'], 'permissions.*' => ['in:write,comment,manage_references,manage_datasets,view_private,assign_tasks'], 'contribution_percent' => ['nullable','numeric','min:0','max:100']]);
        $collaboration->syncMember($research, User::findOrFail($data['user_id']), $data['role'], $data['permissions'], $data['contribution_percent'] ?? null);
        return back()->with('success', 'Research collaborator and granular permissions updated.');
    }

    public function searchLiterature(
        Request $request,
        ResearchProject $research,
        ScholarlyDiscoveryService $scholarly,
        AcademicReferenceService $referenceService,
        LiteratureSynthesisService $synthesisService,
    ): View {
        $this->authorize('view', $research);
        $providerKeys = array_keys((array) config('scholarly.providers', []));
        $citationStyles = config('ai.citation_styles', ['apa', 'mla', 'chicago', 'harvard', 'ieee', 'vancouver']);
        $data = $request->validate([
            'view' => ['nullable', Rule::in(['discover', 'saved', 'reading', 'synthesis', 'compare'])],
            'q' => ['nullable', 'string', 'max:500'],
            'providers' => ['nullable', 'array'],
            'providers.*' => ['string', Rule::in($providerKeys)],
            'year_from' => ['nullable', 'integer', 'min:1800', 'max:'.(now()->year + 1)],
            'year_to' => ['nullable', 'integer', 'min:1800', 'max:'.(now()->year + 1), 'gte:year_from'],
            'limit' => ['nullable', 'integer', Rule::in([20, 30, 50])],
            'library_q' => ['nullable', 'string', 'max:255'],
            'reading_status' => ['nullable', Rule::in(['saved', 'reading', 'reviewed'])],
            'collection' => ['nullable', 'string', 'max:100'],
            'compare' => ['nullable', 'array', 'max:5'],
            'compare.*' => ['uuid'],
            'style' => ['nullable', Rule::in($citationStyles)],
        ]);

        $activeView = $data['view'] ?? 'discover';
        $filters = [
            'providers' => $data['providers'] ?? [],
            'year_from' => $data['year_from'] ?? null,
            'year_to' => $data['year_to'] ?? null,
        ];
        $limit = (int) ($data['limit'] ?? 30);
        $records = filled($data['q'] ?? null) ? $scholarly->search($data['q'], $request->user(), $filters, $limit) : [];
        $records = collect($records)->map(function (array $record): array {
            $normalizedDoi = strtolower(trim((string) ($record['doi'] ?? '')));
            $record['citation_key'] = $normalizedDoi !== ''
                ? 'doi:'.$normalizedDoi
                : 'scholarly:'.($record['provider'] ?? 'unknown').':'.sha1((string) ($record['external_identifier'] ?? $record['title'] ?? ''));
            $record['author_text'] = collect((array) ($record['authors'] ?? []))
                ->map(function ($author): ?string {
                    if (is_string($author)) {
                        return trim($author) ?: null;
                    }
                    if (! is_array($author)) {
                        return null;
                    }

                    $name = data_get($author, 'author.display_name')
                        ?? ($author['display_name'] ?? null)
                        ?? ($author['name'] ?? null)
                        ?? trim((string) (($author['given'] ?? '').' '.($author['family'] ?? '')));

                    return filled($name) ? trim((string) $name) : null;
                })
                ->filter()
                ->take(6)
                ->implode(', ');
            $candidateUrl = filled($record['url'] ?? null)
                ? (string) $record['url']
                : ($normalizedDoi !== '' ? 'https://doi.org/'.$normalizedDoi : null);
            $scheme = $candidateUrl ? strtolower((string) parse_url($candidateUrl, PHP_URL_SCHEME)) : '';
            $record['open_url'] = in_array($scheme, ['http', 'https'], true) ? $candidateUrl : null;

            return $record;
        })->values()->all();

        $research->loadMissing(['templateVersion', 'sections']);
        $notesQuery = $research->literatureNotes()->where('created_by', $request->user()->id)
            ->whereIn('academic_reference_id', $research->referenceLinks()->select('academic_reference_id'));
        $noteCounts = (clone $notesQuery)->selectRaw('reading_status, COUNT(*) AS aggregate')->groupBy('reading_status')->pluck('aggregate', 'reading_status');
        $totalReferences = $research->referenceLinks()->count();
        $libraryStats = [
            'total' => $totalReferences,
            'reading' => (int) ($noteCounts['reading'] ?? 0),
            'reviewed' => (int) ($noteCounts['reviewed'] ?? 0),
        ];
        $libraryStats['saved'] = $totalReferences - $libraryStats['reading'] - $libraryStats['reviewed'];
        $collections = $activeView === 'saved'
            ? (clone $notesQuery)->get(['collections'])->flatMap(fn ($note) => (array) $note->collections)->filter()->unique()->sort()->values()
            : collect();
        $query = $research->referenceLinks()->with('reference')->latest()->orderByDesc('id');
        $libraryPage = null;
        if ($activeView === 'discover') {
            // Mark only the visible search results as saved. Do not hydrate the
            // complete reference library merely to open the discovery page.
            $query->whereHas('reference', fn ($q) => $q->whereIn('citation_key', array_column($records, 'citation_key')));
        } elseif ($activeView === 'compare') {
            $query->whereHas('reference', fn ($q) => $q->whereIn('uuid', $data['compare'] ?? []));
        } elseif ($activeView === 'synthesis') {
            $query->whereIn('academic_reference_id', (clone $notesQuery)->where('reading_status', 'reviewed')->select('academic_reference_id'));
        } else {
            if ($activeView === 'reading') {
                $query->whereNotIn('academic_reference_id', (clone $notesQuery)->where('reading_status', 'reviewed')->select('academic_reference_id'));
            }
            if (filled($data['reading_status'] ?? null)) {
                $matchingNotes = (clone $notesQuery);
                if ($data['reading_status'] === 'saved') {
                    $query->whereNotIn('academic_reference_id', $matchingNotes->whereIn('reading_status', ['reading', 'reviewed'])->select('academic_reference_id'));
                } else {
                    $query->whereIn('academic_reference_id', $matchingNotes->where('reading_status', $data['reading_status'])->select('academic_reference_id'));
                }
            }
            if (filled($data['collection'] ?? null)) {
                $query->whereIn('academic_reference_id', (clone $notesQuery)->whereJsonContains('collections', $data['collection'])->select('academic_reference_id'));
            }
            if (filled($data['library_q'] ?? null)) {
                $needle = '%'.$data['library_q'].'%';
                $query->where(fn ($q) => $q
                    ->whereHas('reference', fn ($ref) => $ref->where('title', 'like', $needle)->orWhere('authors', 'like', $needle))
                    ->orWhereIn('academic_reference_id', (clone $notesQuery)->where('keywords', 'like', $needle)->select('academic_reference_id')));
            }
            $libraryPage = $query->paginate(25, ['*'], 'library_page')->withQueryString();
        }
        $referenceLinks = $libraryPage?->getCollection() ?? $query->get();
        $notesByReference = (clone $notesQuery)->whereIn('academic_reference_id', $referenceLinks->pluck('academic_reference_id'))->get()->keyBy('academic_reference_id');
        $savedCitationKeys = $referenceLinks->pluck('reference.citation_key')->filter()->flip();
        $providers = collect(config('scholarly.providers', []))->keys()->values();
        $citationStyle = strtolower((string) ($data['style'] ?? data_get($research->metadata, 'citation_style', $research->templateVersion?->citation_style ?? 'apa')));
        if (! in_array($citationStyle, $citationStyles, true)) {
            $citationStyle = 'apa';
        }

        $explicitCitationStyle = $request->filled('style') ? $citationStyle : null;
        $libraryRows = $referenceLinks
            ->filter(fn ($link): bool => (bool) $link->reference)
            ->values()
            ->map(function ($link, int $index) use ($notesByReference, $referenceService, $citationStyle, $research, $explicitCitationStyle, $libraryPage): array {
                $reference = $link->reference;
                $note = $notesByReference->get($reference->id);
                $readingStatus = $note?->reading_status ?: 'saved';
                $reviewFields = collect([$note?->summary, $note?->methodology, $note?->findings, $note?->limitations, $note?->contradictions, $note?->research_gap]);
                $completedFields = $reviewFields->filter(fn ($value) => filled($value))->count();

                return [
                    'link' => $link,
                    'reference' => $reference,
                    'reference_uuid' => $reference->uuid,
                    'title' => $reference->title,
                    'year' => $reference->publication_year,
                    'doi' => $reference->doi,
                    'url' => $reference->url ?: ($reference->doi ? 'https://doi.org/'.$reference->doi : null),
                    'authors' => $this->referenceAuthorText($reference->authors ?? []),
                    'note' => $note,
                    'reading_status' => $readingStatus,
                    'review_completion' => (int) round(($completedFields / 6) * 100),
                    'collections' => array_values(array_filter((array) ($note?->collections ?? []))),
                    'keywords' => array_values(array_filter((array) ($note?->keywords ?? []))),
                    'summary' => $note?->summary,
                    'methodology' => $note?->methodology,
                    'findings' => $note?->findings,
                    'limitations' => $note?->limitations,
                    'contradictions' => $note?->contradictions,
                    'research_gap' => $note?->research_gap,
                    'in_text_citation' => $referenceService->inText($reference, $citationStyle, ($libraryPage?->firstItem() ?? 1) + $index),
                    'bibliography' => $referenceService->formatForProject($reference, $research, ($libraryPage?->firstItem() ?? 1) + $index, $explicitCitationStyle),
                ];
            });

        $filteredLibraryRows = $libraryRows
            ->when(filled($data['library_q'] ?? null), function ($rows) use ($data) {
                $needle = str((string) $data['library_q'])->lower()->toString();
                return $rows->filter(fn (array $row): bool => str_contains(strtolower($row['title'].' '.$row['authors'].' '.implode(' ', $row['keywords'])), $needle));
            })
            ->when(filled($data['reading_status'] ?? null), fn ($rows) => $rows->where('reading_status', $data['reading_status']))
            ->when(filled($data['collection'] ?? null), fn ($rows) => $rows->filter(fn (array $row): bool => in_array($data['collection'], $row['collections'], true)))
            ->values();

        $readingQueue = $libraryRows->whereIn('reading_status', ['saved', 'reading'])->values();
        $synthesis = $activeView === 'synthesis' ? $synthesisService->build($libraryRows) : [];
        $compareUuids = array_values(array_unique((array) ($data['compare'] ?? [])));
        $compareRows = $libraryRows->filter(fn (array $row): bool => in_array($row['reference_uuid'], $compareUuids, true))->take(5)->values();
        $chapterTwoSection = $research->sections->first(fn ($section) => in_array($section->key, ['chapter_2', 'literature_review'], true));

        return view('research.literature', compact(
            'research', 'records', 'referenceLinks', 'notesByReference', 'savedCitationKeys', 'providers',
            'activeView', 'libraryRows', 'filteredLibraryRows', 'readingQueue', 'libraryStats', 'collections',
            'synthesis', 'compareRows', 'citationStyle', 'citationStyles', 'chapterTwoSection', 'libraryPage'
        ));
    }

    public function saveReference(Request $request, ResearchProject $research, ScholarlyRecord $record): RedirectResponse
    {
        $this->authorize('update', $research);
        $this->saveScholarlyReference($research, $request->user(), $record);

        return back()->with('success', 'Paper saved to the project reference library.');
    }

    public function saveReferences(Request $request, ResearchProject $research): RedirectResponse
    {
        $this->authorize('update', $research);
        $data = $request->validate([
            'record_ids' => ['required', 'array', 'min:1', 'max:50'],
            'record_ids.*' => ['required', 'uuid', 'exists:scholarly_records,uuid'],
        ]);

        $records = ScholarlyRecord::query()->whereIn('uuid', array_values(array_unique($data['record_ids'])))->get();
        foreach ($records as $record) {
            $this->saveScholarlyReference($research, $request->user(), $record);
        }

        return back()->with('success', $records->count().' paper'.($records->count() === 1 ? '' : 's').' saved to the project reference library.');
    }

    public function literatureNote(Request $request, ResearchProject $research, AcademicReference $reference): RedirectResponse
    {
        $this->authorize('update', $research);
        abort_unless($research->referenceLinks()->where('academic_reference_id', $reference->id)->exists(), 404);
        $data = $request->validate([
            'reading_status' => ['nullable', Rule::in(['saved', 'reading', 'reviewed'])],
            'summary' => ['nullable','string'],
            'methodology' => ['nullable','string'],
            'findings' => ['nullable','string'],
            'limitations' => ['nullable','string'],
            'contradictions' => ['nullable','string'],
            'research_gap' => ['nullable','string'],
            'keywords' => ['nullable','string'],
            'collections' => ['nullable','string'],
        ]);

        $status = $data['reading_status'] ?? null;
        $payload = collect($data)->except(['keywords', 'collections'])->all();
        if (array_key_exists('keywords', $data)) {
            $payload['keywords'] = $this->commaSeparatedValues($data['keywords'] ?? '');
        }
        if (array_key_exists('collections', $data)) {
            $payload['collections'] = $this->commaSeparatedValues($data['collections'] ?? '');
        }
        if ($status !== null) {
            $payload['reviewed_at'] = $status === 'reviewed' ? now() : null;
        }

        $research->literatureNotes()->updateOrCreate(
            ['academic_reference_id' => $reference->id, 'created_by' => $request->user()->id],
            $payload
        );

        return back()->with('success', $status === 'reviewed' ? 'Literature review saved and marked as reviewed.' : 'Structured literature review note saved.');
    }

    public function removeReference(Request $request, ResearchProject $research, AcademicReference $reference): RedirectResponse
    {
        $this->authorize('update', $research);
        $link = $research->referenceLinks()->where('academic_reference_id', $reference->id)->firstOrFail();
        $research->literatureNotes()->where('academic_reference_id', $reference->id)->delete();
        $link->delete();

        return back()->with('success', 'Reference removed from this project library.');
    }

    private function saveScholarlyReference(ResearchProject $research, User $user, ScholarlyRecord $record): AcademicReference
    {
        $normalizedDoi = strtolower(trim((string) $record->doi));
        $citationKey = $normalizedDoi !== ''
            ? 'doi:'.$normalizedDoi
            : 'scholarly:'.$record->provider.':'.sha1((string) $record->external_identifier);

        $reference = AcademicReference::query()->updateOrCreate(
            ['owner_id' => $user->id, 'citation_key' => $citationKey],
            [
                'university_id' => $research->workspace_mode === 'institutional' ? $research->university_id : null,
                'title' => $record->title,
                'authors' => $record->authors,
                'publication_year' => $record->publication_year,
                'source_type' => 'scholarly_work',
                'doi' => $record->doi,
                'url' => in_array(strtolower((string) parse_url((string) $record->url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $record->url : null,
                'abstract' => $record->abstract,
                'external_ids' => array_filter([
                    'provider' => $record->provider,
                    'external_identifier' => $record->external_identifier,
                    'doi' => $record->doi,
                ]),
                'metadata' => [
                    'scholarly_record_uuid' => $record->uuid,
                    'provider' => $record->provider,
                    'fetched_at' => $record->fetched_at?->toIso8601String(),
                ],
            ]
        );

        $research->referenceLinks()->firstOrCreate(
            ['academic_reference_id' => $reference->id],
            ['purpose' => 'research_library']
        );

        return $reference;
    }

    public function seal(Request $request, ResearchProject $research, ResearchArchiveService $archives): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_archives', $request->user());
        $this->authorize('publish', $research); $archive = $archives->seal($research, $request->user());
        return back()->with('success', 'Immutable archive v'.$archive->version.' sealed with checksum '.$archive->checksum.'.');
    }

    public function downloadArchive(ResearchProject $research, ResearchArchive $archive, ResearchArchiveService $archives, SafeFileDeliveryService $files)
    {
        $this->assertResearchFeatureEnabled('research_archives', auth()->user());
        $this->authorize('view', $research); abort_unless($archive->research_project_id === $research->id && $archives->verify($archive), 404, 'Archive package is unavailable or failed integrity verification.');
        return $files->stream($archive->disk, $archive->package_path, str($research->title)->slug().'-archive-v'.$archive->version.'.zip', 'application/zip', 'attachment');
    }

    public function requestAmendment(Request $request, ResearchProject $research): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_archives', $request->user());
        $this->authorize('view', $research); abort_unless($research->archives()->where('status','sealed')->exists(), 422);
        $data = $request->validate(['reason' => ['required','string','max:10000'], 'requested_changes' => ['nullable','array']]);
        $research->amendments()->create($data + ['research_archive_id' => $research->archives()->latest('version')->value('id'), 'requested_by' => $request->user()->id, 'status' => 'pending']);
        return back()->with('success', 'Controlled amendment request submitted for approval.');
    }

    public function reviewAmendment(Request $request, ResearchProject $research, ResearchAmendment $amendment): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_archives', $request->user());
        $this->authorize('review', $research); abort_unless($amendment->research_project_id === $research->id, 404);
        $data = $request->validate(['decision' => ['required','in:approve,reject'], 'review_note' => ['nullable','string','max:10000']]);
        $amendment->update(['status' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'reviewed_by' => $request->user()->id, 'review_note' => $data['review_note'] ?? null, 'reviewed_at' => now()]);
        return back()->with('success', 'Amendment review recorded.');
    }

    public function retireTemplate(Request $request, ResearchTemplateVersion $template, ResearchTemplateService $templates): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() && ($request->user()->isSuperAdmin() || $template->researchType?->university_id === null || $template->researchType?->university_id === $request->user()->university_id), 403);
        $templates->retire($template);
        return back()->with('success', 'Template version retired for future projects; existing projects remain pinned to it.');
    }

    public function storeSection(Request $request, ResearchProject $research, ContentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('update', $research);
        abort_if(in_array($research->status, ['approved','archived'], true), 423, 'Approved or archived research cannot add sections without an approved amendment.');
        $data = $request->validate(['title'=>['required','string','max:255'],'key'=>['nullable','alpha_dash','max:100'],'is_required'=>['nullable','boolean'],'initial_content'=>['nullable','string','max:100000']]);
        $key = $data['key'] ?? str($data['title'])->slug('_')->toString();
        abort_if($research->sections()->where('key',$key)->exists(), 422, 'A section with this key already exists.');
        DB::transaction(function () use ($research,$request,$workspace,$data,$key) {
            $document=$workspace->create(['document_type'=>'research_section','title'=>$data['title'],'body'=>$data['initial_content']??'','status'=>'draft','visibility'=>'private'],$request->user());
            $research->sections()->create(['content_document_id'=>$document->id,'created_by'=>$request->user()->id,'key'=>$key,'title'=>$data['title'],'position'=>((int)$research->sections()->max('position'))+1,'is_required'=>(bool)($data['is_required']??false),'status'=>'draft','completion_percent'=>0]);
        });
        return back()->with('success','Research section created with version history.');
    }

    public function reorderSections(Request $request, ResearchProject $research): RedirectResponse
    {
        $this->authorize('update',$research);
        $data=$request->validate(['section_ids'=>['required','array','min:1'],'section_ids.*'=>['required','string']]);
        $sections=$research->sections()->whereIn('uuid',$data['section_ids'])->get()->keyBy('uuid');
        abort_unless($sections->count()===count(array_unique($data['section_ids']))&&$sections->count()===$research->sections()->count(),422,'The reorder request must include every project section exactly once.');
        DB::transaction(function()use($data,$sections){foreach($data['section_ids'] as $i=>$uuid)$sections[$uuid]->update(['position'=>$i+1]);});
        return back()->with('success','Section order updated.');
    }

    public function destroySection(Request $request, ResearchProject $research, ResearchSection $section): RedirectResponse
    {
        $this->authorize('update',$research);abort_unless($section->research_project_id===$research->id,404);abort_if($section->is_required,422,'Required template sections cannot be removed.');abort_if($section->approved_at||$section->locked_at,423,'Approved or locked sections require an approved amendment.');
        DB::transaction(function()use($section){$section->tasks()->update(['research_section_id'=>null]);$section->document?->delete();$section->delete();});
        return back()->with('success','Optional section removed.');
    }

    public function restoreSectionVersion(Request $request, ResearchProject $research, ResearchSection $section, ContentVersion $version, ContentWorkspaceService $workspace): RedirectResponse
    {
        $this->authorize('update', $research);
        abort_unless($section->research_project_id === $research->id, 404);
        DB::transaction(function () use ($research, $section, $version, $workspace, $request) {
            $section = $research->sections()->lockForUpdate()->findOrFail($section->id);
            abort_if($section->locked_at, 423, 'This section is locked.');
            $before = (string) $section->document->body;
            $document = $workspace->restore($section->document, $version, $request->user());
            $section->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null,
                'completion_percent' => trim(strip_tags((string) $document->body)) === '' ? 0 : min(95, max(10, str_word_count(strip_tags($document->body)) / 10))]);
            app(ResearchCollaborationService::class)->recordEdit($section, $request->user(), $before, $document->body, $document->versions()->latest('version_number')->value('id'));
            app(\App\Services\ResearchProjectService::class)->recalculateProgress($research);
        });
        return back()->with('success','Section version restored as a new auditable version.');
    }

    public function resolveCorrection(Request $request, ResearchProject $research, ResearchCorrection $correction): RedirectResponse
    {
        $this->authorize('view',$research);abort_unless($correction->research_project_id===$research->id,404);abort_unless($request->user()->isAdmin()||$correction->assigned_to===$request->user()->id||$research->owner_id===$request->user()->id||$research->supervisor_id===$request->user()->id,403);
        $data=$request->validate(['resolution_note'=>['nullable','string','max:5000']]);$correction->update(['status'=>'resolved','resolved_at'=>now()]);
        if($correction->section&&!$correction->section->project->corrections()->where('research_section_id',$correction->research_section_id)->whereIn('status',['open','in_progress'])->exists())$correction->section->update(['status'=>'ready_for_review']);
        return back()->with('success','Correction marked resolved. '.($data['resolution_note']??''));
    }

    public function storeDataset(Request $request, ResearchProject $research, MediaSecurityService $media): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_datasets', $request->user());
        $this->authorize('update',$research);abort_unless($request->user()->isAdmin()||$research->owner_id===$request->user()->id||$research->memberRecords()->where('user_id',$request->user()->id)->whereJsonContains('permissions','manage_datasets')->exists(),403);
        $data=$request->validate(['name'=>['required','string','max:255'],'description'=>['nullable','string','max:5000'],'access_level'=>['required','in:project,supervisors,institution,public'],'file'=>['required','file'],'schema_metadata'=>['nullable','array'],'ethics_metadata'=>['nullable','array']]);
        $dataset=DB::transaction(function()use($research,$request,$data,$media){$dataset=$research->datasets()->create(['uploaded_by'=>$request->user()->id,'name'=>$data['name'],'description'=>$data['description']??null,'access_level'=>$data['access_level'],'schema_metadata'=>$data['schema_metadata']??[],'ethics_metadata'=>$data['ethics_metadata']??[]]);$visibility=match($data['access_level']){'public'=>'public','institution'=>'institution',default=>'private'};$asset=$media->store($data['file'],$request->user(),$dataset,$visibility,['research_project_uuid'=>$research->uuid,'dataset_uuid'=>$dataset->uuid,'ethics_controlled'=>true]);$dataset->update(['media_asset_id'=>$asset->id]);return $dataset;});
        return back()->with('success','Dataset '.$dataset->name.' uploaded with security scanning and project-level access controls.');
    }

    public function destroyDataset(Request $request, ResearchProject $research, ResearchDataset $dataset): RedirectResponse
    {
        $this->assertResearchFeatureEnabled('research_datasets', $request->user());
        $this->authorize('update',$research);abort_unless($dataset->research_project_id===$research->id,404);abort_unless($request->user()->isAdmin()||$dataset->uploaded_by===$request->user()->id||$research->supervisor_id===$request->user()->id,403);
        if($dataset->mediaAsset){Storage::disk($dataset->mediaAsset->disk)->delete($dataset->mediaAsset->path);$dataset->mediaAsset->delete();}$dataset->delete();return back()->with('success','Dataset removed with its stored asset.');
    }

    public function linkSpecializedWorkspace(Request $request, ResearchProject $research): RedirectResponse
    {
        $this->authorize('update',$research);$data=$request->validate(['workspace_type'=>['required','in:siwes,seminar'],'submission_id'=>['required','integer','exists:submissions,id'],'settings'=>['nullable','array']]);$submission=Submission::with(['user','course.department'])->findOrFail($data['submission_id']);abort_unless($submission->type===$data['workspace_type'],422,'Submission type does not match the requested specialized workspace.');abort_unless($request->user()->isAdmin()||$submission->user_id===$research->owner_id||$submission->user_id===$request->user()->id,403);abort_unless($request->user()->isSuperAdmin()||$submission->course?->department?->faculty?->university_id===$research->university_id,403);
        $research->specializedLinks()->updateOrCreate(['workspace_type'=>$data['workspace_type'],'source_type'=>$submission->getMorphClass(),'source_id'=>$submission->id],['settings'=>array_merge($data['settings']??[],['submission_uuid'=>$submission->uuid,'reuse_existing_submission_versions'=>true,'reuse_existing_grading_and_defense'=>true])]);$research->update(['specialization_type'=>$data['workspace_type']]);
        return back()->with('success',strtoupper($data['workspace_type']).' was linked to the existing submission, review, grading, and defense workflow instead of duplicated.');
    }

    public function exportPdf(ResearchProject $research)
    {
        $this->authorize('view', $research);
        $queue = app(\App\Services\ResearchExportQueue::class);
        if ($queue->required($research)) return redirect()->route('research.exports.show', $queue->start($research, auth()->user(), 'pdf'));
        $research->load(['sections.document','owner','supervisor','department','university','referenceLinks.reference']);$pdf=Pdf::loadView('research.export',compact('research'))->setPaper('a4','portrait');return $pdf->download(str($research->title)->slug().'.pdf');
    }

    public function exportDocx(ResearchProject $research, \App\Services\ResearchDocumentExportService $exporter)
    {
        $this->authorize('view', $research);
        $queue = app(\App\Services\ResearchExportQueue::class);
        if ($queue->required($research)) return redirect()->route('research.exports.show', $queue->start($research, auth()->user(), 'docx'));
        $path = $exporter->docx($research);
        return response()->download($path, (str($research->title)->slug()->limit(120, '')->toString() ?: 'research').'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function exportHtml(ResearchProject $research): Response
    {
        $this->authorize('view', $research); $research->load('sections.document');
        $body = view('research.export', compact('research'))->render();
        return response($body, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Content-Disposition' => 'attachment; filename="'.str($research->title)->slug().'.html"']);
    }

    /** @param array<int,mixed> $authors */
    private function referenceAuthorText(array $authors): string
    {
        return collect($authors)
            ->map(function ($author): ?string {
                if (is_string($author)) {
                    return trim($author) ?: null;
                }
                if (! is_array($author)) {
                    return null;
                }

                $name = data_get($author, 'author.display_name')
                    ?? ($author['display_name'] ?? null)
                    ?? ($author['name'] ?? null)
                    ?? trim((string) (($author['given'] ?? '').' '.($author['family'] ?? '')));

                return filled($name) ? trim((string) $name) : null;
            })
            ->filter()
            ->take(8)
            ->implode(', ');
    }

    /** @return array<int,string> */
    private function commaSeparatedValues(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->unique(fn (string $item): string => strtolower($item))
            ->values()
            ->all();
    }


    private function assertResearchFeatureEnabled(string $feature, ?User $user): void
    {
        abort_unless(
            FeatureAccessService::effectiveStatus($feature, $user?->university_id) === FeatureAccessService::STATUS_ENABLED,
            403,
            'This Research Studio module is currently disabled by the platform administrator.'
        );
    }

}
