@extends('layouts.app')
@section('title', 'Knowledge Hub')
@section('page-title', 'Knowledge Hub')
@section('page-subtitle', 'Discover trusted academic work, communities and learning resources.')

@section('content')
@include('knowledge._nav')

<div class="space-y-8">
    <section class="relative overflow-hidden rounded-[2rem] border border-indigo-100 bg-gradient-to-br from-indigo-950 via-indigo-900 to-blue-800 px-6 py-8 text-white shadow-xl shadow-indigo-950/10 sm:px-9 lg:px-11">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-cyan-300/10 blur-3xl"></div>

        <div class="relative grid gap-8 xl:grid-cols-[1.25fr_.75fr] xl:items-end">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-indigo-100 backdrop-blur">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/15">✦</span>
                    Academic discovery, publishing and collaboration
                </div>
                <h1 class="mt-5 max-w-3xl text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">
                    Turn your institution's knowledge into something people can actually use.
                </h1>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-indigo-100/90 sm:text-base">
                    Explore research outputs, study guides, tutorials, learning paths and community knowledge from authorized academic sources.
                </p>

                <form method="GET" action="{{ route('knowledge.index') }}" class="mt-7 rounded-2xl border border-white/15 bg-white/10 p-3 shadow-lg backdrop-blur-sm">
                    <div class="grid gap-3 lg:grid-cols-[1fr_210px_190px_auto]">
                        <label class="relative block">
                            <span class="sr-only">Search Knowledge Hub</span>
                            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-indigo-200">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m21 21-4.35-4.35m1.1-5.4a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z"/></svg>
                            </span>
                            <input name="q" value="{{ request('q') }}" class="w-full rounded-xl border-white/20 bg-white/95 py-3 pl-11 pr-4 text-sm font-medium text-slate-900 placeholder:text-slate-400" placeholder="Search title, author, topic or content">
                        </label>
                        <select name="category" class="rounded-xl border-white/20 bg-white/95 px-3 py-3 text-sm font-medium text-slate-800">
                            <option value="">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->slug }}" @selected(request('category') === $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <select name="type" class="rounded-xl border-white/20 bg-white/95 px-3 py-3 text-sm font-medium text-slate-800">
                            <option value="">All resource types</option>
                            @foreach(['academic_article','research_output','study_guide','tutorial','case_study','career_guide','digital_resource'] as $type)
                                <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-xl bg-white px-5 py-3 text-sm font-black text-indigo-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                            Search
                        </button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="text-2xl font-black">{{ number_format($publications->total()) }}</p>
                    <p class="mt-1 text-xs font-medium text-indigo-100">Resources in this view</p>
                </div>
                <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="text-2xl font-black">{{ number_format($categories->count()) }}</p>
                    <p class="mt-1 text-xs font-medium text-indigo-100">Knowledge categories</p>
                </div>
                <div class="col-span-2 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                    <p class="text-sm font-bold">Built for focused academic work</p>
                    <p class="mt-1 text-xs leading-5 text-indigo-100">Save useful resources, follow creators, discuss evidence and ask the grounded AI companion when a publication is indexed.</p>
                </div>
            </div>
        </div>
    </section>

    @auth
        <section class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-black text-slate-900">Share what you know</p>
                <p class="mt-1 text-sm text-slate-500">Publish academic resources or manage drafts, moderation and analytics.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('knowledge.manage') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700">Manage publications</a>
                <a href="{{ route('knowledge.manage.create') }}" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700">Create publication</a>
            </div>
        </section>
    @endauth

    @if($recommended->isNotEmpty())
        <section>
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">For you</p>
                    <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Recommended next reads</h2>
                </div>
                <p class="text-sm text-slate-500">Based on academic relevance and authorized activity.</p>
            </div>
            <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                @foreach($recommended->take(4) as $item)
                    @php
                        $recommendedPublication = $item['publication'];
                        $recommendationReason = data_get($item, 'recommendation.reason', 'Recommended academic resource');
                    @endphp
                    <a href="{{ route('knowledge.show', $recommendedPublication) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg">
                        <div class="flex items-center justify-between gap-3">
                            <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-black uppercase tracking-wide text-indigo-700">{{ ucwords(str_replace('_', ' ', $recommendedPublication->content_type)) }}</span>
                            <span class="text-xs text-slate-400">{{ $recommendedPublication->reading_time_minutes ?: 5 }} min</span>
                        </div>
                        <h3 class="mt-4 line-clamp-2 text-base font-black leading-6 text-slate-900 transition group-hover:text-indigo-700">{{ $recommendedPublication->title }}</h3>
                        <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-500">{{ $recommendationReason }}</p>
                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-400">
                            <span>{{ $recommendedPublication->creator?->full_name }}</span>
                            <span>{{ number_format($recommendedPublication->view_count) }} reads</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Library</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950">Explore academic resources</h2>
            </div>
            @if(request()->hasAny(['q','category','type','access']))
                <a href="{{ route('knowledge.index') }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50">Clear filters</a>
            @endif
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse($publications as $publication)
                <article class="group flex min-h-[280px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl hover:shadow-slate-200/60">
                    <div class="h-1.5 bg-gradient-to-r from-indigo-500 via-blue-500 to-cyan-400 opacity-80"></div>
                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-black uppercase tracking-wide text-indigo-700">{{ ucwords(str_replace('_', ' ', $publication->content_type)) }}</span>
                                @if($publication->access_type === 'premium')
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">Premium</span>
                                @elseif($publication->access_type === 'institution')
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">Institution</span>
                                @endif
                            </div>
                            <span class="whitespace-nowrap text-xs text-slate-400">{{ $publication->published_at?->diffForHumans() }}</span>
                        </div>

                        <h3 class="mt-4 text-xl font-black leading-7 tracking-tight text-slate-950">
                            <a href="{{ route('knowledge.show', $publication) }}" class="transition group-hover:text-indigo-700">{{ $publication->title }}</a>
                        </h3>
                        <p class="mt-3 line-clamp-3 flex-1 text-sm leading-6 text-slate-600">{{ $publication->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $publication->document?->body), 180) }}</p>

                        <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $publication->creator?->full_name ?: 'Academic contributor' }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-400">{{ $publication->category?->name ?: 'Academic resource' }}</p>
                            </div>
                            <div class="shrink-0 text-right text-xs text-slate-400">
                                <p>{{ number_format($publication->view_count) }} reads</p>
                                <p class="mt-0.5">{{ number_format($publication->bookmark_count) }} saves</p>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center md:col-span-2 xl:col-span-3">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-2xl">⌕</div>
                    <h3 class="mt-4 text-lg font-black text-slate-900">No publications match this search</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Try a broader keyword, remove a filter, or publish the first resource for this topic.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-6">{{ $publications->links() }}</div>
    </section>
</div>
@endsection
