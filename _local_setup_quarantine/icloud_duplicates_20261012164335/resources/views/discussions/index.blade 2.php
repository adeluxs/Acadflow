@extends('layouts.app')
@section('title', 'Discussions - '.$course->name)
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <section class="rounded-[2rem] bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 p-7 text-white shadow-xl sm:p-9">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="text-xs font-black uppercase tracking-[.24em] text-cyan-300">{{ $course->code }} · Course community</p><h1 class="mt-2 text-3xl font-black sm:text-4xl">Discussions</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Ask questions, share ideas, connect conversations to materials and build a searchable course knowledge trail.</p></div>
            <div class="flex flex-wrap gap-2"><a href="{{ auth()->user()->isLecturer() ? route('lecturer.courses') : route('courses.index') }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold">Back to courses</a><a href="{{ route('discussions.create',$course) }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-black text-slate-950">+ New discussion</a></div>
        </div>
    </section>

    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[1fr_240px_auto]">
        <label><span class="sr-only">Search discussions</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search questions, topics or keywords…" class="w-full rounded-xl border-slate-300"></label>
        <select name="tag" class="rounded-xl border-slate-300"><option value="">All tags</option>@foreach($tags as $tag)<option value="{{ $tag->name }}" @selected(request('tag')==$tag->name)>{{ $tag->name }}</option>@endforeach</select>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-black text-white">Search</button>
    </form>

    <div class="space-y-4">
        @forelse($discussions as $discussion)
            <article class="group rounded-3xl border {{ $discussion->is_pinned ? 'border-amber-200 bg-amber-50/30' : 'border-slate-200 bg-white' }} p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg sm:p-6">
                <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap gap-2">@if($discussion->is_pinned)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800">Pinned</span>@endif @if($discussion->status==='resolved')<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Resolved</span>@endif @if($discussion->priority==='high')<span class="rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-bold text-rose-700">High priority</span>@endif @foreach($discussion->tags as $tag)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ $tag->name }}</span>@endforeach</div>
                        <a href="{{ route('discussions.show',[$course,$discussion]) }}" class="mt-3 block text-xl font-black text-slate-900 transition group-hover:text-indigo-700">{{ $discussion->title }}</a>
                        <p class="mt-2 text-sm text-slate-500">{{ $discussion->user->full_name }} · {{ $discussion->created_at->diffForHumans() }}</p>
                        @if($discussion->material)<a href="{{ route('materials.show',[$course,$discussion->material]) }}" class="mt-3 inline-flex rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-indigo-700">Related: {{ $discussion->material->title }}</a>@endif
                    </div>
                    <div class="flex shrink-0 items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3"><div class="text-center"><p class="text-xl font-black text-slate-900">{{ $discussion->engagementThread?->comments_count ?? 0 }}</p><p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Replies</p></div><a href="{{ route('discussions.show',[$course,$discussion]) }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Open</a></div>
                </div>
            </article>
        @empty
            <section class="rounded-[2rem] border border-dashed border-slate-300 bg-white p-14 text-center"><div class="text-4xl">💬</div><h2 class="mt-4 text-xl font-black text-slate-900">No discussions found</h2><p class="mt-2 text-sm text-slate-500">Start the first conversation or adjust your filters.</p><a href="{{ route('discussions.create',$course) }}" class="mt-5 inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Start discussion</a></section>
        @endforelse
    </div>
    {{ $discussions->links() }}
</div>
@endsection
