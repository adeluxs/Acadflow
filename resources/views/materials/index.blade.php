@extends('layouts.app')
@section('title', 'Course Materials - '.$course->name)
@section('content')
@php
    $materialCount = $grouped->flatten(1)->count();
    $isCourseStaff = auth()->user()->isLecturer() || auth()->user()->isAdmin();
@endphp
<div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 px-6 py-8 text-white shadow-xl sm:px-8 lg:px-10">
        <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-cyan-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <a href="{{ $isCourseStaff ? route('lecturer.courses') : route('courses.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-200 hover:text-white">← Back to courses</a>
                <p class="mt-5 text-xs font-black uppercase tracking-[.28em] text-indigo-300">{{ $course->code }} · Learning resources</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Course materials</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">{{ $course->name }}. Find lecture notes, slides, readings and other resources in one organized workspace.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:flex">
                <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4 backdrop-blur"><p class="text-xs text-slate-300">Resources</p><p class="mt-1 text-2xl font-black">{{ $materialCount }}</p></div>
                @if($isCourseStaff)
                    <a href="{{ route('lecturer.materials.create', $course) }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-5 py-4 text-sm font-black text-slate-950 shadow-lg transition hover:-translate-y-0.5">+ Upload material</a>
                @endif
            </div>
        </div>
    </section>

    @if($isCourseStaff)
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">Course staff tools</span>
            <a href="{{ route('lecturer.materials.create', $course) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Upload resource</a>
            @can('feature', 'document_generation')
                <a href="{{ route('materials.export-pdf', $course) }}" target="_blank" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Export materials PDF</a>
            @endcan
        </div>
    @endif

    @forelse($grouped as $groupKey => $groupItems)
        @php
            $heading = \Str::startsWith($groupKey, 'topic_') ? \Str::after($groupKey, 'topic_') : (\Str::startsWith($groupKey, 'week_') ? 'Week '.\Str::after($groupKey, 'week_') : 'Resources');
        @endphp
        <section class="space-y-4">
            <div class="flex items-end justify-between gap-4">
                <div><p class="text-xs font-black uppercase tracking-[.2em] text-indigo-600">Resource collection</p><h2 class="mt-1 text-xl font-black text-slate-900">{{ $heading }}</h2></div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $groupItems->count() }} item{{ $groupItems->count()===1?'':'s' }}</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($groupItems as $material)
                    @php
                        $icon = match ($material->type) { 'lecture_note'=>'📝','slides'=>'📊','reading'=>'📚','video'=>'🎬','assignment'=>'📋','exam'=>'✏️','reference'=>'📖',default=>'📁' };
                    @endphp
                    <article class="group flex min-h-56 flex-col rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-2xl">{{ $icon }}</div>
                            <div class="flex flex-wrap justify-end gap-1.5">
                                @if($material->week_number)<span class="rounded-full bg-violet-50 px-2.5 py-1 text-[11px] font-bold text-violet-700">Week {{ $material->week_number }}</span>@endif
                                @if(!$material->is_visible)<span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">Hidden</span>@endif
                            </div>
                        </div>
                        <a href="{{ route('materials.show', [$course, $material]) }}" class="mt-4 text-lg font-black leading-snug text-slate-900 transition group-hover:text-indigo-700">{{ $material->title }}</a>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">{{ str($material->type)->replace('_',' ')->headline() }}</p>
                        @if($material->description)<p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">{{ $material->description }}</p>@endif
                        <div class="mt-auto pt-5">
                            <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                                @if($material->topic)<span class="rounded-lg bg-cyan-50 px-2.5 py-1 font-semibold text-cyan-700">{{ $material->topic }}</span>@endif
                                <span class="rounded-lg bg-slate-50 px-2.5 py-1">{{ $material->file_size ? number_format($material->file_size/1024,1).' KB' : 'Resource' }}</span>
                            </div>
                            <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-4">
                                <a href="{{ route('materials.show', [$course, $material]) }}" class="flex-1 rounded-xl bg-indigo-600 px-3 py-2 text-center text-sm font-bold text-white hover:bg-indigo-700">Open</a>
                                <a href="{{ route('materials.download', [$course, $material]) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Download</a>
                                @can('update', $material)<a href="{{ route('lecturer.materials.edit', [$course, $material]) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">Edit</a>@endcan
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <section class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-3xl">📚</div>
            <h2 class="mt-5 text-xl font-black text-slate-900">No materials yet</h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Course resources will appear here as soon as the lecturer makes them available.</p>
            @if($isCourseStaff)<a href="{{ route('lecturer.materials.create', $course) }}" class="mt-5 inline-flex rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Upload the first material</a>@endif
        </section>
    @endforelse
</div>
@endsection
