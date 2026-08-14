@extends('layouts.app')
@section('title', $research->title)
@section('page-title', $research->title)
@section('page-subtitle', ($research->researchType?->name ?? 'Research').' · '.ucwords(str_replace('_',' ', $research->status)))
@section('content')
@php
    $currentStage = $research->workflowInstance?->currentStage;
    $nextStage = $research->workflowInstance?->definition?->stages?->first(fn($stage) => $currentStage && $stage->position > $currentStage->position);
@endphp
<div class="space-y-6">
    <div class="grid gap-5 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Progress</p><p class="mt-2 text-3xl font-semibold">{{ number_format((float)$research->progress) }}%</p><div class="mt-3 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-600" style="width:{{ min(100,(float)$research->progress) }}%"></div></div></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Current stage</p><p class="mt-2 font-semibold">{{ $currentStage?->name ?? ucwords(str_replace('_',' ', $research->status)) }}</p><p class="mt-1 text-sm text-slate-500">Workflow-controlled</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Supervisor</p><p class="mt-2 font-semibold">{{ $research->supervisor?->full_name ?? 'Not assigned' }}</p><p class="mt-1 text-sm text-slate-500">{{ $research->department?->name }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs uppercase tracking-wide text-slate-500">Latest validation</p><p class="mt-2 font-semibold">{{ $research->latestValidationReport?->readiness_score !== null ? number_format((float)$research->latestValidationReport->readiness_score, 1).' readiness' : 'Not run' }}</p><p class="mt-1 text-sm text-slate-500">Similarity: {{ $research->latestValidationReport?->similarity_score !== null ? number_format((float)$research->latestValidationReport->similarity_score,1).'%' : '—' }}</p></div>
    </div>

    <div class="flex flex-wrap gap-3"><a href="{{ route('research.workspace',$research) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Control center</a><a href="{{ route('research.literature.search',$research) }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Literature & references</a>
        @can('validate', $research)
            <form method="POST" action="{{ route('research.validate', $research) }}">@csrf<button class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Run validation & similarity</button></form>
        @endcan
        @can('transition', $research)
            @if($nextStage)
            <form method="POST" action="{{ route('research.transition', $research) }}" class="flex gap-2">@csrf<input type="hidden" name="target_stage" value="{{ $nextStage->key }}"><input type="hidden" name="action" value="advance"><button class="rounded-xl border border-blue-300 bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700">Move to {{ $nextStage->name }}</button></form>
            @endif
        @endcan
        @if($research->publications->isNotEmpty())
            <a href="{{ route('knowledge.manage.edit', $research->publications->first()) }}" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700">Open linked publication</a>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
            @foreach($research->sections as $section)
                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div><h2 class="font-semibold text-slate-900">{{ $section->title }}</h2><p class="mt-1 text-xs text-slate-500">Version {{ $section->document->version_number }} · {{ ucwords(str_replace('_',' ', $section->status)) }} @if($section->is_required) · Required @endif</p></div>
                        @can('review', $research)
                            <form method="POST" action="{{ route('research.sections.approve', [$research, $section]) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">Approve section</button></form>
                        @endcan
                    </div>
                    <div class="p-5">
                        @can('update', $research)
                        <form method="POST" action="{{ route('research.sections.save', [$research, $section]) }}" class="space-y-3">@csrf @method('PUT')
                            <textarea name="body" rows="14" data-rich-editor data-editor-height="340px" data-ai-url="{{ route('ai.writing') }}" data-ai-enabled="{{ \App\Services\SettingService::get('ai_editor_suggestions_enabled', true) ? '1' : '0' }}" data-ai-min-chars="{{ \App\Services\SettingService::get('ai_editor_suggestion_min_chars', 60) }}" data-ai-delay="{{ \App\Services\SettingService::get('ai_editor_suggestion_delay_ms', 1600) }}" class="w-full rounded-xl border-slate-300 font-serif leading-7" placeholder="Write {{ strtolower($section->title) }} here...">{{ old('body', $section->document->body) }}</textarea>
                            <div class="flex items-center justify-between"><span class="text-xs text-slate-500">Auto-save endpoint supports JSON clients; this form creates a recoverable version.</span><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Save version</button></div>
                        </form>
                        @else
                            <article class="prose max-w-none">{!! app(\App\Services\RichTextSanitizer::class)->sanitize((string) $section->document->body) !!}</article>
                        @endcan

                        <div class="mt-6 border-t border-slate-100 pt-5">
                            <h3 class="text-sm font-semibold">Comments and corrections</h3>
                            <div class="mt-3 space-y-3">
                                @forelse($section->document->comments as $comment)
                                    <div class="rounded-xl bg-slate-50 p-3"><div class="flex justify-between text-xs text-slate-500"><span>{{ $comment->user?->full_name }} · {{ ucfirst($comment->type) }}</span><span>{{ $comment->created_at?->diffForHumans() }}</span></div><p class="mt-2 text-sm">{{ $comment->body }}</p></div>
                                @empty <p class="text-sm text-slate-500">No comments yet.</p> @endforelse
                            </div>
                            <form method="POST" action="{{ route('research.sections.comment', [$research, $section]) }}" class="mt-3 flex gap-2">@csrf<input name="body" required class="flex-1 rounded-xl border-slate-300 text-sm" placeholder="Add a review comment"><button class="rounded-xl border border-slate-300 px-3 py-2 text-sm">Comment</button></form>
                            @can('review', $research)
                            <form method="POST" action="{{ route('research.sections.corrections', [$research, $section]) }}" class="mt-3 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf<select name="type" class="rounded-xl border-slate-300 text-sm"><option value="general">General correction</option><option value="rewrite">Rewrite</option><option value="methodology">Methodology</option><option value="references">References</option><option value="formatting">Formatting</option><option value="academic_language">Academic language</option></select><input name="description" required class="rounded-xl border-slate-300 text-sm" placeholder="Describe the required correction"><button class="rounded-xl bg-amber-500 px-3 py-2 text-sm font-semibold text-white">Request</button></form>
                            @endcan
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        <aside class="space-y-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Project details</h3><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">Owner</dt><dd class="font-medium">{{ $research->owner?->full_name }}</dd></div><div><dt class="text-slate-500">Research area</dt><dd>{{ $research->research_area ?: '—' }}</dd></div><div><dt class="text-slate-500">Keywords</dt><dd>{{ implode(', ', $research->keywords ?? []) ?: '—' }}</dd></div><div><dt class="text-slate-500">Expected completion</dt><dd>{{ $research->expected_completion_date?->format('M j, Y') ?? '—' }}</dd></div></dl></div>

            @if($research->latestValidationReport)
            <div class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Validation report</h3><p class="mt-3 text-sm text-slate-600">{{ $research->latestValidationReport->summary }}</p><div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-xl bg-blue-50 p-3"><p class="text-xs text-blue-700">Readiness</p><p class="text-xl font-semibold text-blue-900">{{ number_format((float)$research->latestValidationReport->readiness_score,1) }}</p></div><div class="rounded-xl bg-amber-50 p-3"><p class="text-xs text-amber-700">Similarity</p><p class="text-xl font-semibold text-amber-900">{{ number_format((float)$research->latestValidationReport->similarity_score,1) }}%</p></div></div></div>
            @endif

            @can('publish', $research)
            <form method="POST" action="{{ route('research.publish', $research) }}" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">@csrf<h3 class="font-semibold text-emerald-900">Publish approved research</h3><p class="mt-2 text-sm text-emerald-800">Creates a moderated Knowledge Hub draft and preserves the permanent source-project link.</p><select name="category_id" class="mt-4 w-full rounded-xl border-emerald-200 text-sm"><option value="">No category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><select name="visibility" class="mt-3 w-full rounded-xl border-emerald-200 text-sm"><option value="institution">Institution only</option><option value="public">Public</option></select><button class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">Create linked publication</button></form>
            @endcan
        </aside>
    </div>
</div>
@endsection
