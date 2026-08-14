@extends('layouts.app')
@section('title', 'Research Studio')
@section('page-title', 'Research Studio')
@section('page-subtitle', 'Formal research, supervision, validation and approval')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex gap-2">
            <select name="status" class="rounded-xl border-slate-300 text-sm" onchange="this.form.submit()">
                <option value="">All stages</option>
                @foreach(['draft','topic_proposal','proposal_writing','main_writing','validation','supervisor_review','corrections','approved','archived'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>
                @endforeach
            </select>
        </form>
        <div class="flex flex-wrap gap-2">
        @if(auth()->user()->isAdmin())<a href="{{ route('research.configuration.index') }}" class="inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold">Configure types & workflows</a><a href="{{ route('research.templates.index') }}" class="inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold">Template versions</a>@endif
        @can('create', App\Models\ResearchProject::class)
            <a href="{{ route('research.create') }}" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">New research project</a>
        @endcan
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($projects as $project)
            <a href="{{ route('research.show', $project) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{{ $project->researchType?->name ?? 'Research' }}</span>
                    <span class="text-xs font-medium text-slate-500">{{ number_format((float)$project->progress) }}%</span>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $project->title }}</h3>
                <p class="mt-2 line-clamp-2 text-sm text-slate-500">{{ $project->abstract ?: 'No abstract added yet.' }}</p>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, (float)$project->progress) }}%"></div></div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                    <span>{{ ucwords(str_replace('_',' ', $project->status)) }}</span>
                    <span>{{ $project->supervisor?->full_name ?? 'Supervisor pending' }}</span>
                </div>
            </a>
        @empty
            <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <h3 class="font-semibold text-slate-900">No research projects yet</h3>
                <p class="mt-2 text-sm text-slate-500">Create a project to generate its sections, versioned workspace and configured workflow.</p>
            </div>
        @endforelse
    </div>
    {{ $projects->links() }}
</div>
@endsection
