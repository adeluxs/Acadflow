@extends('layouts.app')
@section('title', $research->title)
@section('page-title', 'Writing workspace')
@section('page-subtitle', $research->title)

@section('content')
@php
    $currentStage = $research->workflowInstance?->currentStage;
    $nextStage = $research->workflowInstance?->definition?->stages?->first(fn($stage) => $currentStage && $stage->position > $currentStage->position);
    $activeKey = strtolower((string) ($activeSection?->key ?? ''));
    $activeTitle = strtolower((string) ($activeSection?->title ?? ''));
    $isLiteratureWritingSection = $activeSection && (in_array($activeKey, ['chapter_2', 'literature_review'], true) || str_contains($activeTitle, 'literature'));
    $isReferencesWritingSection = $activeSection && (str_contains($activeKey, 'reference') || str_contains($activeTitle, 'reference') || str_contains($activeTitle, 'bibliograph'));
    $isResultsWritingSection = $activeSection && ($activeKey === 'chapter_4' || str_contains($activeTitle, 'result') || str_contains($activeTitle, 'analysis'));
@endphp

<div class="studio-shell space-y-6">
    <nav class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <a href="{{ route('research.workspace', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Control center</a>
        <a href="{{ route('research.show', ['research' => $research, 'section' => $activeSection?->uuid]) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Writing workspace</a>
        @if($literatureEnabled)
            <a href="{{ route('research.literature.search', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Literature &amp; references</a>
        @endif
        <a href="{{ route('research.plan', $research) }}" class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">Plan & library</a>
        <a href="{{ route('research.export.docx', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">DOCX</a>
        <a href="{{ route('research.export.pdf', $research) }}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold">PDF</a>
        <a href="{{ route('research.export.html', $research) }}" class="ml-auto rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Preview / export</a>
    </nav>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 p-5 text-white sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-indigo-200">Professional writing workspace</p>
                    <h1 class="mt-2 text-2xl font-black">{{ $writingGuidance['title'] }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">{{ $writingGuidance['description'] }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('validate', $research)
                        <form method="POST" action="{{ route('research.validate', $research) }}">@csrf<button class="rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs font-bold text-white hover:bg-white/15">Run validation</button></form>
                    @endcan
                    @can('transition', $research)
                        @if($nextStage)
                            <form method="POST" action="{{ route('research.transition', $research) }}">@csrf<input type="hidden" name="target_stage" value="{{ $nextStage->key }}"><input type="hidden" name="action" value="advance"><button class="rounded-xl bg-white px-3 py-2 text-xs font-black text-slate-950">Move to {{ $nextStage->name }}</button></form>
                        @endif
                    @endcan
                </div>
            </div>
            <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-300">
                <span>Overall progress <strong class="text-white">{{ number_format((float) $research->progress) }}%</strong></span>
                <span>Current stage <strong class="text-white">{{ $currentStage?->name ?? ucwords(str_replace('_',' ', $research->status)) }}</strong></span>
                <span>{{ $sectionJourney->count() }} configured sections</span>
                @if($institutionExpansionEnabled && $research->workspace_mode === 'institutional')
                    <span class="rounded-full bg-indigo-400/15 px-2.5 py-1 font-bold text-indigo-100">Institutional workspace</span>
                @endif
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)_330px]">
        <aside class="space-y-4 xl:sticky xl:top-5 xl:self-start">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Research document</p><h2 class="mt-1 font-black text-slate-950">Chapter navigator</h2></div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">{{ $sectionJourney->count() }}</span>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach($sectionJourney as $step)
                        @php($sectionNav = $step['section'])
                        @php($isActive = $activeSection && $sectionNav->id === $activeSection->id)
                        <a href="{{ route('research.show', ['research' => $research, 'section' => $sectionNav->uuid]) }}" class="block rounded-xl border px-3 py-3 transition {{ $isActive ? 'border-indigo-300 bg-indigo-50 ring-1 ring-indigo-100' : 'border-slate-100 hover:border-slate-200 hover:bg-slate-50' }}">
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ $step['progress'] >= 100 ? 'bg-emerald-100 text-emerald-700' : ($isActive ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500') }} text-[11px] font-black">{{ $step['progress'] >= 100 ? '✓' : $loop->iteration }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold leading-5 text-slate-900">{{ $step['label'] }}</span>
                                    <span class="block truncate text-[11px] text-slate-500">{{ $sectionNav->title }}</span>
                                </span>
                            </div>
                            <div class="mt-2 h-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $step['progress'] >= 100 ? 'bg-emerald-500' : 'bg-indigo-500' }}" style="width:{{ $step['progress'] }}%"></div></div>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Project progress</p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600" style="width:{{ min(100, max(0, (float) $research->progress)) }}%"></div></div>
                <p class="mt-2 text-xs text-slate-500">{{ number_format((float) $research->progress) }}% complete</p>
            </section>
        </aside>

        <main class="min-w-0 space-y-5">
            @if($activeSection)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="border-b border-slate-100 bg-gradient-to-r from-white to-slate-50 p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">{{ $writingGuidance['eyebrow'] }}</p>
                                <h2 class="mt-1 text-xl font-black text-slate-950">{{ $activeSection->title }}</h2>
                                <p class="mt-1 text-xs text-slate-500">Version {{ $activeSection->document->version_number }} · {{ ucwords(str_replace('_',' ', $activeSection->status)) }}{{ $activeSection->is_required ? ' · Required' : '' }}</p>
                            </div>
                            @can('review', $research)
                                <form method="POST" action="{{ route('research.sections.approve', [$research, $activeSection]) }}">@csrf<input type="hidden" name="expected_version" value="{{ $activeSection->document->version_number }}"><button class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white">Approve section</button></form>
                            @endcan
                        </div>
                    </header>

                    <div class="p-5">
                        @if(auth()->user()->can('update', $research) && !$activeSection->locked_at)
                            <form method="POST" action="{{ route('research.sections.save', [$research, $activeSection]) }}" class="space-y-3" data-research-autosave>@csrf @method('PUT')<input type="hidden" name="expected_version" value="{{ $activeSection->document->version_number }}">
                                <textarea id="research-editor-{{ $activeSection->uuid }}" name="body" rows="18" data-rich-editor data-editor-height="560px" data-ai-url="{{ route('ai.writing') }}" data-ai-enabled="{{ \App\Services\SettingService::get('ai_editor_suggestions_enabled', true, auth()->user()?->university_id) ? '1' : '0' }}" data-ai-min-chars="{{ \App\Services\SettingService::get('ai_editor_suggestion_min_chars', 60, auth()->user()?->university_id) }}" data-ai-delay="{{ \App\Services\SettingService::get('ai_editor_suggestion_delay_ms', 1600, auth()->user()?->university_id) }}" class="w-full rounded-xl border-slate-300 font-serif leading-7" placeholder="Write {{ strtolower($activeSection->title) }} here...">{{ old('body', $activeSection->document->body) }}</textarea>
                                <div class="flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span data-autosave-status role="status" class="text-xs leading-5 text-slate-500">Changes save automatically as recoverable versions.</span>
                                    <button type="submit" class="studio-button">Save version</button>
                                </div>
                            </form>
                        @else
                            <article class="prose max-w-none">{!! app(\App\Services\RichTextSanitizer::class)->sanitize((string) $activeSection->document->body) !!}</article>
                        @endif
                    </div>
                </section>

                <details class="studio-panel"><summary class="cursor-pointer font-bold text-sm">Section version history</summary><div class="mt-4 space-y-3">@foreach($activeSection->document->versions->sortByDesc('version_number')->take(20) as $version)<details class="rounded-xl border border-slate-200 p-3"><summary class="cursor-pointer text-xs">Version {{ $version->version_number }} · {{ $version->created_at?->format('d M Y H:i') }}</summary><article class="prose mt-3 max-w-none text-sm">{!! app(\App\Services\RichTextSanitizer::class)->sanitize((string) $version->body) !!}</article>@if(auth()->user()->can('update', $research) && !$activeSection->locked_at)<form method="POST" action="{{ route('research.sections.versions.restore', [$research, $activeSection, $version]) }}" class="mt-3">@csrf<button class="studio-button-secondary">Restore as new version</button></form>@endif</details>@endforeach</div></details>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3"><div><h3 class="font-black text-slate-900">Comments and corrections</h3><p class="mt-1 text-xs text-slate-500">Keep feedback attached to the section it applies to.</p></div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">{{ $activeSection->document->comments->count() }}</span></div>
                    <div class="mt-4 space-y-3">
                        @forelse($activeSection->document->comments as $comment)
                            <div class="rounded-xl bg-slate-50 p-3"><div class="flex justify-between gap-3 text-xs text-slate-500"><span>{{ $comment->user?->full_name }} · {{ ucfirst($comment->type) }}</span><span>{{ $comment->created_at?->diffForHumans() }}</span></div><p class="mt-2 text-sm leading-6 text-slate-700">{{ $comment->body }}</p></div>
                        @empty
                            <p class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">No comments yet.</p>
                        @endforelse
                    </div>
                    <form method="POST" action="{{ route('research.sections.comment', [$research, $activeSection]) }}" class="mt-3 flex gap-2">@csrf<input name="body" required class="flex-1 rounded-xl border-slate-300 text-sm" placeholder="Add a review comment"><button class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-bold">Comment</button></form>
                    @can('review', $research)
                        <form method="POST" action="{{ route('research.sections.corrections', [$research, $activeSection]) }}" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf<select name="type" class="rounded-xl border-slate-300 text-sm"><option value="general">General correction</option><option value="rewrite">Rewrite</option><option value="methodology">Methodology</option><option value="references">References</option><option value="formatting">Formatting</option><option value="academic_language">Academic language</option></select><input name="description" required class="rounded-xl border-slate-300 text-sm" placeholder="Describe the required correction"><button class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-bold text-white">Request</button></form>
                    @endcan
                </section>
            @else
                <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm"><h2 class="text-lg font-black text-slate-900">No writing sections configured</h2><p class="mt-2 text-sm text-slate-500">This project template does not contain any sections yet.</p></section>
            @endif

            <div id="research-assistant">
                @include('ai._contextual-assistant', [
                    'assistantFeature' => 'research_assistant',
                    'assistantEndpoint' => route('ai.context.research', $research),
                    'assistantDescription' => $activeSection ? 'Use the authorized project context to review the current section without inventing research evidence.' : null,
                ])
            </div>
        </main>

        <aside class="space-y-5 xl:sticky xl:top-5 xl:self-start">
            @if($activeSection)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Recommended structure</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Use this as a professional checklist, not a forced university format.</p>
                    <div class="mt-4 space-y-2">
                        @foreach($writingGuidance['checklist'] as $item)
                            <div class="flex items-start gap-2 rounded-xl bg-slate-50 px-3 py-2.5"><span class="mt-0.5 h-4 w-4 shrink-0 rounded border border-slate-300 bg-white"></span><span class="text-xs font-semibold leading-5 text-slate-700">{{ $item }}</span></div>
                        @endforeach
                    </div>
                    @can('update', $research)
                        @if($writingGuidance['checklist'])
                            <div class="mt-4 border-t border-slate-100 pt-4"><p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">Insert heading</p><div class="flex flex-wrap gap-2">@foreach($writingGuidance['checklist'] as $item)<button type="button" data-editor-insert data-editor-target="research-editor-{{ $activeSection->uuid }}" data-editor-text="{{ $item }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold text-slate-600 hover:bg-slate-50">{{ $item }}</button>@endforeach</div></div>
                        @endif
                    @endcan
                </section>

                @if($literatureEnabled && $isLiteratureWritingSection)
                    <section class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Insert from your references</p><h3 class="mt-1 font-black text-indigo-950">Reviewed literature</h3></div><span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-indigo-700">{{ $chapterTwoLiterature->count() }}</span></div>
                        <p class="mt-2 text-xs leading-5 text-indigo-900/70">Only papers you marked Reviewed are shown. AcadSyntra inserts your own review notes with the project citation; verify and rewrite the wording before saving.</p>
                        <div class="mt-4 max-h-[480px] space-y-3 overflow-y-auto pr-1">
                            @forelse($chapterTwoLiterature as $literatureRow)
                                <article class="rounded-xl border border-indigo-100 bg-white p-3">
                                    <p class="text-sm font-bold leading-5 text-slate-900">{{ $literatureRow['reference']->title }}</p>
                                    <p class="mt-1 text-xs font-semibold text-indigo-600">{{ $literatureRow['citation'] }}</p>
                                    <div class="mt-3 grid grid-cols-2 gap-2">
                                        <button type="button" data-literature-insert data-editor-target="research-editor-{{ $activeSection->uuid }}" data-editor-text="{{ $literatureRow['citation'] }}" class="rounded-lg border border-indigo-200 px-2 py-2 text-xs font-bold text-indigo-700">Insert citation</button>
                                        @if(filled($literatureRow['note']->findings ?: $literatureRow['note']->summary))<button type="button" data-literature-insert data-editor-target="research-editor-{{ $activeSection->uuid }}" data-editor-text="{{ trim(($literatureRow['note']->findings ?: $literatureRow['note']->summary).' '.$literatureRow['citation']) }}" class="rounded-lg bg-indigo-600 px-2 py-2 text-xs font-bold text-white">Insert evidence</button>@endif
                                        @if(filled($literatureRow['note']->methodology))<button type="button" data-literature-insert data-editor-target="research-editor-{{ $activeSection->uuid }}" data-editor-text="{{ trim($literatureRow['note']->methodology.' '.$literatureRow['citation']) }}" class="rounded-lg border border-slate-200 px-2 py-2 text-xs font-bold text-slate-600">Methodology</button>@endif
                                        @if(filled($literatureRow['note']->research_gap))<button type="button" data-literature-insert data-editor-target="research-editor-{{ $activeSection->uuid }}" data-editor-text="{{ trim($literatureRow['note']->research_gap.' '.$literatureRow['citation']) }}" class="rounded-lg border border-slate-200 px-2 py-2 text-xs font-bold text-slate-600">Research gap</button>@endif
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-xl border border-dashed border-indigo-200 bg-white/70 p-4 text-center"><p class="text-sm font-bold text-slate-800">No reviewed references yet.</p><p class="mt-1 text-xs leading-5 text-slate-500">Review papers in the Literature Library and mark them Reviewed before inserting evidence here.</p></div>
                            @endforelse
                        </div>
                        <a href="{{ route('research.literature.search', ['research' => $research, 'view' => 'saved']) }}" class="mt-4 block rounded-xl border border-indigo-200 bg-white px-3 py-2.5 text-center text-xs font-bold text-indigo-700">Open Literature Library</a>
                    </section>
                @elseif($literatureEnabled && $isReferencesWritingSection)
                    <section class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Reference list</p><h3 class="mt-1 font-black text-emerald-950">Reviewed bibliography</h3></div><span class="rounded-full bg-white px-2.5 py-1 text-xs font-black text-emerald-700">{{ $chapterTwoLiterature->count() }}</span></div>
                        <p class="mt-2 text-xs leading-5 text-emerald-900/70">Insert formatted references for reviewed papers using {{ strtoupper($citationStyle) }} style.</p>
                        @if($chapterTwoLiterature->isNotEmpty())
                            <button type="button" data-literature-insert data-editor-target="research-editor-{{ $activeSection->uuid }}" data-editor-text="{{ $chapterTwoLiterature->pluck('bibliography')->implode("\n\n") }}" class="mt-4 w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white">Insert reviewed bibliography</button>
                            <div class="mt-4 max-h-[390px] space-y-2 overflow-y-auto pr-1">@foreach($chapterTwoLiterature as $literatureRow)<div class="rounded-xl border border-emerald-100 bg-white p-3 text-xs leading-5 text-slate-700">{{ $literatureRow['bibliography'] }}</div>@endforeach</div>
                        @else
                            <div class="mt-4 rounded-xl border border-dashed border-emerald-200 bg-white/70 p-4 text-center text-xs text-slate-600">No reviewed references are ready for the bibliography yet.</div>
                        @endif
                    </section>
                @endif

                @if($datasetsEnabled && $isResultsWritingSection)
                    <section class="rounded-2xl border border-cyan-200 bg-cyan-50/50 p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-[0.14em] text-cyan-700">Dataset support</p><h3 class="mt-1 font-black text-cyan-950">Research data</h3></div><span class="rounded-full bg-white px-2 py-1 text-xs font-black text-cyan-700">{{ $research->datasets->count() }}</span></div>
                        <p class="mt-2 text-xs leading-5 text-cyan-900/70">Dataset Management is enabled by the administrator. Upload supporting data only when it is appropriate for your research design.</p>
                        @can('update', $research)
                            <form method="POST" action="{{ route('research.datasets.store', $research) }}" enctype="multipart/form-data" class="mt-4 space-y-2">@csrf<input name="name" required class="w-full rounded-xl border-cyan-200 bg-white text-sm" placeholder="Dataset name"><input type="file" name="file" required class="w-full rounded-xl border border-cyan-200 bg-white p-2 text-xs"><select name="access_level" class="w-full rounded-xl border-cyan-200 bg-white text-sm"><option value="project">Project only</option><option value="supervisors">Supervisors</option>@if($institutionExpansionEnabled && $research->workspace_mode === 'institutional')<option value="institution">Institution</option>@endif<option value="public">Public</option></select><button class="w-full rounded-xl bg-cyan-700 px-3 py-2.5 text-xs font-bold text-white">Upload dataset</button></form>
                        @endcan
                    </section>
                @endif

                <section class="rounded-2xl border border-violet-200 bg-violet-50/50 p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-violet-600">Section-aware AI</p>
                    <h3 class="mt-1 font-black text-violet-950">Useful review prompts</h3>
                    <div class="mt-3 space-y-2">@foreach(($writingGuidance['ai_prompts'] ?? []) as $prompt)<button type="button" data-research-ai-prompt="{{ $prompt }}" class="block w-full rounded-xl border border-violet-100 bg-white px-3 py-2.5 text-left text-xs font-semibold leading-5 text-violet-900 hover:border-violet-200">{{ $prompt }}</button>@endforeach</div>
                    <a href="#research-assistant" class="mt-3 block rounded-xl bg-violet-600 px-3 py-2.5 text-center text-xs font-bold text-white">Ask Research Assistant</a>
                </section>
            @endif

            @if($institutionExpansionEnabled && $research->workspace_mode === 'institutional')
                <section class="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Institutional supervision</p>
                    @if($research->supervisor)
                        <h3 class="mt-2 font-black text-indigo-950">{{ $research->supervisor->full_name }}</h3><p class="mt-1 text-xs text-indigo-800">Accepted supervisor{{ $research->department?->name ? ' · '.$research->department->name : '' }}</p>
                    @elseif($pendingSupervisorInvitation)
                        <h3 class="mt-2 font-black text-indigo-950">Invitation pending</h3><p class="mt-1 text-xs leading-5 text-indigo-800">Waiting for {{ $pendingSupervisorInvitation->invitee?->full_name }} to accept.</p>
                    @else
                        <h3 class="mt-2 font-black text-indigo-950">Invite a supervisor</h3>
                        @can('update', $research)
                            @if($supervisorCandidates->isNotEmpty())
                                <form method="POST" action="{{ route('research.invitations.store', $research) }}" class="mt-3 space-y-2">@csrf<select name="supervisor_invitee_id" required class="w-full rounded-xl border-indigo-200 bg-white text-sm"><option value="">Choose lecturer</option>@foreach($supervisorCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->full_name }}{{ $candidate->department?->name ? ' · '.$candidate->department->name : '' }}</option>@endforeach</select><textarea name="supervisor_message" rows="2" maxlength="1000" class="w-full rounded-xl border-indigo-200 bg-white text-sm" placeholder="Optional invitation message"></textarea><button class="w-full rounded-xl bg-indigo-600 px-3 py-2.5 text-xs font-bold text-white">Send supervision invitation</button></form>
                            @else
                                <p class="mt-2 text-xs leading-5 text-indigo-800">No eligible lecturers are currently available.</p>
                            @endif
                        @endcan
                    @endif
                </section>
            @endif

            @if($research->latestValidationReport)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Latest validation</p><div class="mt-3 grid grid-cols-2 gap-2"><div class="rounded-xl bg-blue-50 p-3"><p class="text-[10px] font-bold uppercase text-blue-600">Readiness</p><p class="mt-1 text-lg font-black text-blue-950">{{ number_format((float)$research->latestValidationReport->readiness_score,1) }}</p></div><div class="rounded-xl bg-amber-50 p-3"><p class="text-[10px] font-bold uppercase text-amber-600">Similarity</p><p class="mt-1 text-lg font-black text-amber-950">{{ number_format((float)$research->latestValidationReport->similarity_score,1) }}%</p></div></div></section>
            @endif

            @if($publishingEnabled)
                @can('publish', $research)
                    <form method="POST" action="{{ route('research.publish', $research) }}" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">@csrf<p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Publication</p><h3 class="mt-1 font-black text-emerald-950">Publish approved research</h3><p class="mt-2 text-xs leading-5 text-emerald-800">Creates a moderated Knowledge Hub draft while preserving the source-project link.</p><select name="category_id" class="mt-3 w-full rounded-xl border-emerald-200 text-sm"><option value="">No category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><select name="visibility" class="mt-2 w-full rounded-xl border-emerald-200 text-sm">@if($institutionExpansionEnabled && $research->workspace_mode === 'institutional')<option value="institution">Institution only</option>@endif<option value="public">Public</option></select><button class="mt-3 w-full rounded-xl bg-emerald-600 px-3 py-2.5 text-xs font-bold text-white">Create linked publication</button></form>
                @endcan
            @endif
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-literature-insert], [data-editor-insert]');
    if (button) {
        const targetId = button.dataset.editorTarget || '';
        const target = document.getElementById(targetId);
        const rawText = String(button.dataset.editorText || '').trim();
        if (!target || !rawText) return;
        const text = button.hasAttribute('data-editor-insert') && !button.hasAttribute('data-literature-insert') ? `\n${rawText}\n` : rawText;
        let accepted = false;
        if (window.AcadFlowRichEditor?.insert) {
            accepted = window.AcadFlowRichEditor.insert(target, text) === true;
        } else {
            target._acadflowPendingInsertions = target._acadflowPendingInsertions || [];
            target._acadflowPendingInsertions.push(text);
            accepted = true;
        }
        if (!accepted) return;
        const originalLabel = button.dataset.originalLabel || button.textContent.trim();
        button.dataset.originalLabel = originalLabel;
        button.textContent = 'Inserted ✓';
        button.disabled = true;
        setTimeout(() => { button.textContent = originalLabel; button.disabled = false; }, 1200);
        return;
    }

    const promptButton = event.target.closest('[data-research-ai-prompt]');
    if (!promptButton) return;
    const question = document.querySelector('#research-assistant [data-ai-question]');
    if (!question) return;
    question.value = promptButton.dataset.researchAiPrompt || '';
    question.dispatchEvent(new Event('input', {bubbles:true}));
    document.getElementById('research-assistant')?.scrollIntoView({behavior:'smooth', block:'start'});
    setTimeout(() => question.focus(), 350);
});

document.querySelectorAll('[data-literature-insert], [data-editor-insert]').forEach((button) => {
    button.dataset.originalLabel = button.textContent.trim();
});
</script>
@endpush
