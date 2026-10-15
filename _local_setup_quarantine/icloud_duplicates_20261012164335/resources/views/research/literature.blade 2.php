@extends('layouts.app')
@section('title', 'Literature & References')
@section('page-title', 'Literature & reference library')
@section('page-subtitle', $research->title)

@section('content')
@php
    $tabBase = fn (string $view) => route('research.literature.search', ['research' => $research, 'view' => $view, 'style' => $citationStyle]);
    $statusClasses = [
        'saved' => 'bg-slate-100 text-slate-700',
        'reading' => 'bg-amber-100 text-amber-800',
        'reviewed' => 'bg-emerald-100 text-emerald-800',
    ];
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50 via-white to-white px-5 py-6 sm:px-7">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Research evidence workspace</p>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Literature &amp; References</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Discover scholarly work, manage your reading queue, review papers systematically, compare evidence and move verified literature into your writing workspace.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('research.workspace', $research) }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Control Center</a>
                    <a href="{{ route('research.show', $research) }}{{ $chapterTwoSection ? '#section-'.$chapterTwoSection->key : '' }}" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800">Writing Workspace</a>
                </div>
            </div>
        </div>

        <nav class="flex gap-1 overflow-x-auto px-3 py-2 sm:px-5" aria-label="Literature workspace">
            @foreach([
                'discover' => ['Discover', $records ? count($records) : null],
                'saved' => ['Saved References', $libraryStats['total']],
                'reading' => ['Reading Queue', $libraryStats['saved'] + $libraryStats['reading']],
                'synthesis' => ['Synthesis', $libraryStats['reviewed']],
                'compare' => ['Compare', null],
            ] as $view => [$label, $count])
                <a href="{{ $tabBase($view) }}" class="whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-bold transition {{ $activeView === $view ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $label }}
                    @if($count !== null)
                        <span class="ml-1.5 rounded-full px-2 py-0.5 text-[11px] {{ $activeView === $view ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $count }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </section>

    @if($activeView === 'discover')
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <form method="GET" action="{{ route('research.literature.search', $research) }}" class="space-y-4">
                <input type="hidden" name="view" value="discover">
                <input type="hidden" name="style" value="{{ $citationStyle }}">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_130px_130px_120px_auto]">
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Research topic, question or keywords</span>
                        <input name="q" value="{{ request('q') }}" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="e.g. machine learning for predicting student academic performance">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">From year</span>
                        <input type="number" min="1800" max="{{ now()->year + 1 }}" name="year_from" value="{{ request('year_from') }}" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="2020">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">To year</span>
                        <input type="number" min="1800" max="{{ now()->year + 1 }}" name="year_to" value="{{ request('year_to') }}" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="{{ now()->year }}">
                    </label>
                    <label class="block">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Results</span>
                        <select name="limit" class="mt-1.5 w-full rounded-xl border-slate-300">
                            @foreach([20, 30, 50] as $option)
                                <option value="{{ $option }}" @selected((int) request('limit', 30) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="self-end rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Search papers</button>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Sources</span>
                        @foreach($providers as $provider)
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="providers[]" value="{{ $provider }}" @checked(!request()->has('providers') || in_array($provider, (array) request('providers'), true)) class="rounded border-slate-300 text-indigo-600">
                                {{ str($provider)->replace('_', ' ')->headline() }}
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs leading-5 text-slate-500">AcadFlow searches scholarly metadata/index services. Opening a paper follows the provider or DOI destination and does not bypass publisher or access restrictions.</p>
                </div>
            </form>
        </section>

        @if(filled(request('q')))
            <section class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Discover</p>
                        <h2 class="text-xl font-black text-slate-950">Search results <span class="text-base font-semibold text-slate-400">({{ count($records) }})</span></h2>
                        <p class="mt-1 text-sm text-slate-500">Select several relevant papers and save them into this project in one action.</p>
                    </div>
                    @if(count($records) > 0)
                        <form id="bulk-save-form" method="POST" action="{{ route('research.literature.bulk-save', $research) }}">
                            @csrf
                            <button class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white">Save selected papers</button>
                        </form>
                    @endif
                </div>

                <div class="grid gap-4">
                    @forelse($records as $record)
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-200">
                            <div class="flex gap-4">
                                <div class="pt-1">
                                    @if(!$savedCitationKeys->has($record['citation_key']))
                                        <input form="bulk-save-form" type="checkbox" name="record_ids[]" value="{{ $record['uuid'] }}" class="rounded border-slate-300 text-indigo-600" aria-label="Select {{ $record['title'] }}">
                                    @else
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-xs font-black text-emerald-700">✓</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 font-bold text-indigo-700">{{ str($record['provider'])->replace('_', ' ')->headline() }}</span>
                                                <span class="text-slate-500">{{ $record['publication_year'] ?? 'Year unavailable' }}</span>
                                                @if(filled($record['doi'] ?? null))
                                                    <span class="rounded-md bg-blue-50 px-2 py-1 font-mono text-[11px] text-blue-700">DOI {{ $record['doi'] }}</span>
                                                @endif
                                                @if($savedCitationKeys->has($record['citation_key']))
                                                    <span class="rounded-full bg-emerald-50 px-2 py-1 font-bold text-emerald-700">Saved</span>
                                                @endif
                                            </div>
                                            <h3 class="mt-2 text-lg font-bold leading-7 text-slate-950">{{ $record['title'] }}</h3>
                                            <p class="mt-1 text-sm text-slate-500">{{ $record['author_text'] ?: 'Authors unavailable' }}</p>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap gap-2">
                                            @if(filled($record['open_url']))
                                                <a href="{{ $record['open_url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">Open paper ↗</a>
                                            @endif
                                            @if(!$savedCitationKeys->has($record['citation_key']))
                                                <form method="POST" action="{{ route('research.literature.save', [$research, $record['uuid']]) }}">
                                                    @csrf
                                                    <button class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-bold text-indigo-700">Save reference</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    @if(filled($record['abstract'] ?? null))
                                        <div class="mt-4 rounded-xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Abstract preview</p>
                                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ str(strip_tags($record['abstract']))->limit(850) }}</p>
                                        </div>
                                    @else
                                        <p class="mt-4 text-sm italic text-slate-400">No abstract was returned by this source.</p>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                            <p class="font-bold text-slate-800">No matching scholarly records were found.</p>
                            <p class="mt-1 text-sm text-slate-500">Try broader keywords, a wider year range, or additional scholarly sources.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        @else
            <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-xl">⌕</div>
                <h2 class="mt-4 text-lg font-black text-slate-900">Search across scholarly sources</h2>
                <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Use a research question or a few focused keywords. Save relevant papers, then move them through Reading and Reviewed before using them in Chapter Two.</p>
            </section>
        @endif
    @endif

    @if($activeView === 'saved')
        <section class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['Total references', $libraryStats['total'], 'text-indigo-700 bg-indigo-50'],
                    ['Saved', $libraryStats['saved'], 'text-slate-700 bg-slate-100'],
                    ['Reading', $libraryStats['reading'], 'text-amber-800 bg-amber-50'],
                    ['Reviewed', $libraryStats['reviewed'], 'text-emerald-800 bg-emerald-50'],
                ] as [$label, $value, $classes])
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                        <p class="mt-2 text-3xl font-black {{ explode(' ', $classes)[0] }}">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5 sm:p-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-600">Project Library</p>
                            <h2 class="mt-1 text-xl font-black text-slate-950">Manage your references</h2>
                            <p class="mt-1 text-sm text-slate-500">Track reading progress, organise collections and complete structured review notes.</p>
                        </div>
                        <form method="GET" action="{{ route('research.literature.search', $research) }}" class="grid gap-2 sm:grid-cols-3">
                            <input type="hidden" name="view" value="saved">
                            <input type="hidden" name="style" value="{{ $citationStyle }}">
                            <input name="library_q" value="{{ request('library_q') }}" class="rounded-xl border-slate-300 text-sm" placeholder="Search library">
                            <select name="reading_status" class="rounded-xl border-slate-300 text-sm" onchange="this.form.submit()">
                                <option value="">All statuses</option>
                                @foreach(['saved' => 'Saved', 'reading' => 'Reading', 'reviewed' => 'Reviewed'] as $value => $label)
                                    <option value="{{ $value }}" @selected(request('reading_status') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="collection" class="rounded-xl border-slate-300 text-sm" onchange="this.form.submit()">
                                <option value="">All collections</option>
                                @foreach($collections as $collection)
                                    <option value="{{ $collection }}" @selected(request('collection') === $collection)>{{ $collection }}</option>
                                @endforeach
                            </select>
                            <button class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700">Filter</button>
                        </form>
                    </div>
                </div>

                <form method="GET" action="{{ route('research.literature.search', $research) }}">
                    <input type="hidden" name="view" value="compare">
                    <input type="hidden" name="style" value="{{ $citationStyle }}">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-5 py-3"><span class="sr-only">Compare</span></th>
                                    <th class="px-5 py-3">Reference</th>
                                    <th class="px-5 py-3">Year</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3">Collections</th>
                                    <th class="px-5 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse($filteredLibraryRows as $row)
                                    <tr class="align-top">
                                        <td class="px-5 py-4"><input type="checkbox" name="compare[]" value="{{ $row['reference_uuid'] }}" class="rounded border-slate-300 text-indigo-600" aria-label="Compare {{ $row['title'] }}"></td>
                                        <td class="max-w-xl px-5 py-4">
                                            <p class="font-bold text-slate-900">{{ $row['title'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $row['authors'] ?: 'Authors unavailable' }}</p>
                                            <p class="mt-2 text-xs font-medium text-indigo-700">{{ $row['in_text_citation'] }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">{{ $row['year'] ?? 'n.d.' }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses[$row['reading_status']] ?? $statusClasses['saved'] }}">{{ ucfirst($row['reading_status']) }}</span>
                                            <div class="mt-2 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-500" style="width:{{ $row['review_completion'] }}%"></div></div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex max-w-xs flex-wrap gap-1">
                                                @forelse($row['collections'] as $collection)
                                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600">{{ $collection }}</span>
                                                @empty
                                                    <span class="text-xs text-slate-400">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-right">
                                            <a href="{{ route('research.literature.search', ['research' => $research, 'view' => 'saved', 'style' => $citationStyle, 'review' => $row['reference_uuid']]) }}#review-{{ $row['reference_uuid'] }}" class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-bold text-indigo-700">Review</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">No saved references match these filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($filteredLibraryRows->count() > 1)
                        <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4">
                            <p class="text-xs text-slate-500">Select 2–5 references to compare their structured review notes.</p>
                            <button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Compare selected</button>
                        </div>
                    @endif
                </form>
            </section>

            <div class="space-y-4">
                @foreach($filteredLibraryRows as $row)
                    <details id="review-{{ $row['reference_uuid'] }}" class="group rounded-3xl border border-slate-200 bg-white shadow-sm" @if(request('review') === $row['reference_uuid']) open @endif>
                        <summary class="flex cursor-pointer list-none flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses[$row['reading_status']] ?? $statusClasses['saved'] }}">{{ ucfirst($row['reading_status']) }}</span>
                                    <span class="text-xs text-slate-500">{{ $row['review_completion'] }}% review complete</span>
                                </div>
                                <h3 class="mt-2 truncate text-base font-black text-slate-950">{{ $row['title'] }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $row['authors'] ?: 'Authors unavailable' }} · {{ $row['year'] ?? 'n.d.' }}</p>
                            </div>
                            <span class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 group-open:bg-slate-50">Open review</span>
                        </summary>
                        <div class="border-t border-slate-100 p-5 sm:p-6">
                            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_280px]">
                                <form method="POST" action="{{ route('research.literature.note', [$research, $row['reference']]) }}" class="grid gap-4">
                                    @csrf
                                    @method('PUT')
                                    <label>
                                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">My Summary</span>
                                        <textarea name="summary" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="What is this paper mainly arguing or demonstrating?">{{ $row['summary'] }}</textarea>
                                    </label>
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <label><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Methodology</span><textarea name="methodology" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="Design, sample, data and analysis method">{{ $row['methodology'] }}</textarea></label>
                                        <label><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Key Findings</span><textarea name="findings" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="Main findings relevant to your project">{{ $row['findings'] }}</textarea></label>
                                        <label><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Limitations</span><textarea name="limitations" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="Weaknesses or limits of the study">{{ $row['limitations'] }}</textarea></label>
                                        <label><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Contradictions / Comparison</span><textarea name="contradictions" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="Where does this agree or disagree with other evidence?">{{ $row['contradictions'] }}</textarea></label>
                                    </div>
                                    <label><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Research Gap / Relevance</span><textarea name="research_gap" rows="4" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="What remains unresolved and how does it connect to your research?">{{ $row['research_gap'] }}</textarea></label>
                                    <div class="grid gap-4 lg:grid-cols-2">
                                        <label><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Review Keywords</span><input name="keywords" value="{{ implode(', ', $row['keywords']) }}" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="machine learning, student performance"></label>
                                        <label><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Collections</span><input name="collections" value="{{ implode(', ', $row['collections']) }}" class="mt-1.5 w-full rounded-xl border-slate-300" placeholder="Chapter Two, Nigerian Studies"></label>
                                    </div>
                                    <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                        <select name="reading_status" class="rounded-xl border-slate-300 text-sm font-semibold">
                                            <option value="saved" @selected($row['reading_status'] === 'saved')>Saved</option>
                                            <option value="reading" @selected($row['reading_status'] === 'reading')>Reading</option>
                                            <option value="reviewed" @selected($row['reading_status'] === 'reviewed')>Reviewed</option>
                                        </select>
                                        <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Save Review</button>
                                    </div>
                                </form>

                                <aside class="space-y-4">
                                    <div class="rounded-2xl bg-slate-50 p-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Citation · {{ strtoupper($citationStyle) }}</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-800">{{ $row['in_text_citation'] }}</p>
                                        <p class="mt-3 text-xs leading-5 text-slate-600">{{ $row['bibliography'] }}</p>
                                    </div>
                                    @if($row['url'])
                                        <a href="{{ $row['url'] }}" target="_blank" rel="noopener noreferrer" class="block rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-bold text-slate-700">Open Paper ↗</a>
                                    @endif
                                    @if($row['reference']->abstract)
                                        <details class="rounded-2xl border border-slate-200 p-4"><summary class="cursor-pointer text-sm font-bold text-slate-700">Stored abstract</summary><p class="mt-3 text-xs leading-5 text-slate-600">{{ $row['reference']->abstract }}</p></details>
                                    @endif
                                    <form method="POST" action="{{ route('research.references.destroy', [$research, $row['reference']]) }}" onsubmit="return confirm('Remove this reference from the project library?')">@csrf @method('DELETE')<button class="w-full rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-bold text-rose-700">Remove from project</button></form>
                                </aside>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    @if($activeView === 'reading')
        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-6">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-600">Reading Queue</p>
                <h2 class="mt-1 text-xl font-black text-slate-950">Move papers from saved to reviewed</h2>
                <p class="mt-1 text-sm text-slate-500">A paper only becomes available for Chapter Two insertion after you mark its structured review as Reviewed.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($readingQueue as $row)
                    <div class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_180px] sm:items-center sm:p-6">
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $statusClasses[$row['reading_status']] }}">{{ ucfirst($row['reading_status']) }}</span><span class="text-xs text-slate-400">{{ $row['review_completion'] }}% reviewed</span></div>
                            <h3 class="mt-2 font-black text-slate-900">{{ $row['title'] }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $row['authors'] ?: 'Authors unavailable' }} · {{ $row['year'] ?? 'n.d.' }}</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            @if($row['reading_status'] === 'saved')
                                <form method="POST" action="{{ route('research.literature.note', [$research, $row['reference']]) }}">@csrf @method('PUT')<input type="hidden" name="reading_status" value="reading"><button class="w-full rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white">Start reading</button></form>
                            @endif
                            <a href="{{ $tabBase('saved') }}&review={{ $row['reference_uuid'] }}#review-{{ $row['reference_uuid'] }}" class="rounded-xl border border-indigo-200 px-4 py-2.5 text-center text-sm font-bold text-indigo-700">Open review form</a>
                        </div>
                    </div>
                @empty
                    <div class="p-10 text-center"><p class="font-bold text-slate-800">Your reading queue is clear.</p><p class="mt-1 text-sm text-slate-500">Discover more papers or review existing saved references.</p></div>
                @endforelse
            </div>
        </section>
    @endif

    @if($activeView === 'synthesis')
        <section class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Reviewed Papers</p><p class="mt-2 text-3xl font-black text-indigo-700">{{ $synthesis['reviewed_count'] }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Research Themes</p><p class="mt-2 text-3xl font-black text-blue-700">{{ $synthesis['themes']->count() }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Method Patterns</p><p class="mt-2 text-3xl font-black text-violet-700">{{ $synthesis['methodologies']->count() }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Recorded Gaps</p><p class="mt-2 text-3xl font-black text-rose-700">{{ $synthesis['gaps']->count() }}</p></div>
            </div>

            @if($synthesis['reviewed_count'] === 0)
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center"><h2 class="text-lg font-black text-slate-900">Synthesis begins after paper review</h2><p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">Mark references as Reviewed after completing their structured notes. AcadFlow will aggregate only what you recorded—it will not invent findings for unread papers.</p></div>
            @else
                <div class="grid gap-5 xl:grid-cols-2">
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black text-slate-900">Key Research Themes</h2>
                        <p class="mt-1 text-sm text-slate-500">Built from your review keywords.</p>
                        <div class="mt-5 space-y-3">
                            @forelse($synthesis['themes'] as $theme)
                                @php $themeMax = max(1, (int) ($synthesis['themes']->max('count') ?: 1)); @endphp
                                <div><div class="flex justify-between text-sm"><span class="font-semibold text-slate-700">{{ $theme['label'] }}</span><span class="text-slate-400">{{ $theme['count'] }}</span></div><div class="mt-1.5 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width:{{ max(10, round(($theme['count'] / $themeMax) * 100)) }}%"></div></div></div>
                            @empty
                                <p class="text-sm text-slate-500">Add review keywords to see theme patterns.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-black text-slate-900">Common Methodologies</h2>
                        <p class="mt-1 text-sm text-slate-500">Detected from methodology notes you entered.</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            @forelse($synthesis['methodologies'] as $method)
                                <div class="rounded-2xl bg-slate-50 p-4"><p class="text-sm font-bold text-slate-800">{{ $method['label'] }}</p><p class="mt-1 text-2xl font-black text-indigo-700">{{ $method['count'] }}</p></div>
                            @empty
                                <p class="text-sm text-slate-500">No recognizable methodology patterns yet.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <div class="grid gap-5 xl:grid-cols-3">
                    @foreach([
                        ['Reviewed Findings', $synthesis['findings'], 'text-emerald-700'],
                        ['Recorded Limitations', $synthesis['limitations'], 'text-amber-700'],
                        ['Identified Research Gaps', $synthesis['gaps'], 'text-rose-700'],
                    ] as [$label, $items, $accent])
                        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 class="font-black {{ $accent }}">{{ $label }}</h3>
                            <div class="mt-4 space-y-3">
                                @forelse($items->take(5) as $item)
                                    <div class="rounded-xl bg-slate-50 p-3"><p class="text-sm leading-6 text-slate-700">{{ $item['text'] }}</p><p class="mt-2 text-[11px] font-semibold text-slate-400">{{ $item['citation'] }} · {{ str($item['title'])->limit(48) }}</p></div>
                                @empty
                                    <p class="text-sm text-slate-500">No reviewed notes in this category yet.</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>

                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-black text-slate-900">Conflicting / contrasting evidence</h3><p class="mt-1 text-sm text-slate-500">Taken directly from your Contradictions / Comparison notes.</p></div><a href="{{ $tabBase('compare') }}" class="rounded-xl border border-indigo-200 px-4 py-2 text-sm font-bold text-indigo-700">Compare papers</a></div>
                    <div class="mt-4 grid gap-3 lg:grid-cols-2">@forelse($synthesis['contradictions']->take(6) as $item)<div class="rounded-xl bg-violet-50/60 p-4"><p class="text-sm leading-6 text-slate-700">{{ $item['text'] }}</p><p class="mt-2 text-xs font-semibold text-violet-600">{{ $item['citation'] }}</p></div>@empty<p class="text-sm text-slate-500">No contrasting evidence has been recorded yet.</p>@endforelse</div>
                </section>
            @endif
        </section>
    @endif

    @if($activeView === 'compare')
        <section class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-xs font-black uppercase tracking-[0.16em] text-violet-600">Compare Papers</p><h2 class="mt-1 text-xl font-black text-slate-950">Compare reviewed evidence side by side</h2><p class="mt-1 text-sm text-slate-500">Choose up to five saved references. Empty cells mean you have not recorded that review field yet.</p></div>
                    <a href="{{ $tabBase('saved') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Choose references</a>
                </div>
            </section>

            @if($compareRows->count() >= 2)
                <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-[980px] w-full border-collapse text-sm">
                            <thead><tr class="bg-slate-50"><th class="w-48 border-b border-r border-slate-200 p-4 text-left text-xs font-black uppercase tracking-wide text-slate-500">Aspect</th>@foreach($compareRows as $row)<th class="min-w-56 border-b border-r border-slate-200 p-4 text-left align-top"><p class="font-black text-slate-900">{{ $row['title'] }}</p><p class="mt-1 text-xs font-medium text-indigo-600">{{ $row['in_text_citation'] }}</p></th>@endforeach</tr></thead>
                            <tbody>
                                @foreach([
                                    'Methodology' => 'methodology',
                                    'Key Findings' => 'findings',
                                    'Limitations' => 'limitations',
                                    'Research Gap' => 'research_gap',
                                    'Contradictions / Comparison' => 'contradictions',
                                ] as $label => $field)
                                    <tr><th class="border-b border-r border-slate-200 bg-slate-50/60 p-4 text-left align-top font-bold text-slate-700">{{ $label }}</th>@foreach($compareRows as $row)<td class="border-b border-r border-slate-200 p-4 align-top leading-6 text-slate-700">{{ filled($row[$field]) ? $row[$field] : 'Not recorded yet.' }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @else
                <section class="rounded-3xl border border-dashed border-slate-300 bg-white p-8">
                    <h3 class="font-black text-slate-900">Select at least two references</h3>
                    <form method="GET" action="{{ route('research.literature.search', $research) }}" class="mt-5 space-y-3">
                        <input type="hidden" name="view" value="compare"><input type="hidden" name="style" value="{{ $citationStyle }}">
                        @foreach($libraryRows->take(30) as $row)
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4 hover:border-indigo-200"><input type="checkbox" name="compare[]" value="{{ $row['reference_uuid'] }}" class="mt-1 rounded border-slate-300 text-indigo-600"><span><span class="block font-bold text-slate-900">{{ $row['title'] }}</span><span class="mt-1 block text-xs text-slate-500">{{ ucfirst($row['reading_status']) }} · {{ $row['in_text_citation'] }}</span></span></label>
                        @endforeach
                        @if($libraryRows->count() >= 2)<button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Compare selected papers</button>@endif
                    </form>
                </section>
            @endif
        </section>
    @endif

    <section class="rounded-3xl border border-indigo-200 bg-indigo-50/50 p-5 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.16em] text-indigo-700">Writing integration</p>
                <h2 class="mt-1 text-lg font-black text-indigo-950">Reviewed literature feeds your Literature Review section</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-indigo-900/75">Only references marked Reviewed appear in the Chapter Two insertion panel. You choose whether to insert the in-text citation, your reviewed finding, methodology, limitation or research-gap note into the editor.</p>
            </div>
            <a href="{{ route('research.show', $research) }}{{ $chapterTwoSection ? '#section-'.$chapterTwoSection->key : '' }}" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-center text-sm font-bold text-white">Open {{ $chapterTwoSection?->title ?? 'Writing Workspace' }}</a>
        </div>
    </section>
</div>
@endsection
