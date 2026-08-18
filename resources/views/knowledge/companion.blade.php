@extends('layouts.app')
@section('title','Grounded AI Companion')
@section('page-title','Grounded AI Companion')
@section('page-subtitle','Publication-scoped answers with evidence validation')

@section('content')
@php
    $meta = is_array($session->metadata) ? $session->metadata : [];
    $intelligence = (array) data_get($meta, 'question_intelligence', []);
    $evidenceGate = (array) data_get($meta, 'evidence_gate', []);
    $answerValidation = (array) data_get($meta, 'answer_validation', []);
    $suggestions = (array) data_get($meta, 'suggestions', []);
    $feedback = (string) data_get($meta, 'feedback.rating', '');
    $guarded = in_array($session->provider, ['input_guard','scope_guard','retrieval_guard'], true);
    $publication = $session->subject instanceof \App\Models\KnowledgePublication ? $session->subject : null;
    $confidence = max(0, min(100, (float) $session->confidence));
@endphp

<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.14em] text-emerald-700">Publication only</span>
                <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-500">No open web</span>
                @if(data_get($meta, 'fallback_used'))
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">Validated fallback used</span>
                @endif
            </div>
            <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-950">Grounded answer</h1>
            <p class="mt-1 text-sm text-slate-500">AcadFlow evaluates the question, retrieves only this publication, and validates source support before showing an answer.</p>
        </div>
        @if($publication)
            <a href="{{ route('knowledge.show', $publication) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 shadow-sm hover:border-indigo-200 hover:text-indigo-700">← Back to publication</a>
        @endif
    </div>

    <section class="overflow-hidden rounded-[2rem] border {{ $guarded ? 'border-amber-200 bg-amber-50/40' : 'border-indigo-100 bg-white' }} shadow-sm">
        <div class="border-b {{ $guarded ? 'border-amber-100' : 'border-slate-100' }} p-6 sm:p-8">
            <p class="text-xs font-black uppercase tracking-[0.16em] {{ $guarded ? 'text-amber-700' : 'text-indigo-600' }}">Your question</p>
            <h2 class="mt-2 text-xl font-black leading-8 text-slate-950">{{ $session->question }}</h2>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                @if(!empty($intelligence['intent']))
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-600">Intent: {{ ucwords(str_replace('_',' ',(string)$intelligence['intent'])) }}</span>
                @endif
                @if(isset($evidenceGate['top_score']))
                    <span class="rounded-full bg-slate-100 px-3 py-1 font-bold text-slate-600">Evidence match: {{ number_format(((float)$evidenceGate['top_score']) * 100, 1) }}%</span>
                @endif
                @if(!empty($intelligence['learned_from_sessions']))
                    <span class="rounded-full bg-violet-50 px-3 py-1 font-bold text-violet-700">Pattern profile: {{ (int)$intelligence['learned_from_sessions'] }} successful signals</span>
                @endif
            </div>
        </div>

        <div class="p-6 sm:p-8">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl {{ $guarded ? 'bg-amber-500' : 'bg-indigo-600' }} font-black text-white">✦</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-black text-slate-950">AcadFlow Grounded AI</p>
                        <div class="flex items-center gap-2 text-xs text-slate-400">
                            <span>{{ str_replace('_',' ',ucwords((string)($session->provider ?: 'grounded'))) }}</span>
                            @if(!$guarded)<span>·</span><span>{{ number_format($confidence,1) }}% confidence</span>@endif
                        </div>
                    </div>
                    <div class="prose prose-slate mt-4 max-w-none whitespace-pre-line leading-7">{{ $session->answer }}</div>
                    @if($session->human_review_required)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">This answer has lower grounding confidence. Verify the cited source excerpts before relying on it.</div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if($guarded && $suggestions !== [])
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">↗</span><div><h2 class="font-black text-slate-950">Try a clearer publication question</h2><p class="text-xs text-slate-500">These prompts are generated from the publication context, not the open web.</p></div></div>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach($suggestions as $suggestion)
                    <form method="POST" action="{{ $publication ? route('knowledge.companion.ask', $publication) : '#' }}">
                        @csrf
                        <input type="hidden" name="question" value="{{ $suggestion }}">
                        <button @disabled(!$publication) class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-bold leading-6 text-slate-700 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-800">{{ $suggestion }}</button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif

    @if($session->sources->isNotEmpty())
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-xs font-black uppercase tracking-[0.16em] text-indigo-600">Evidence trail</p><h2 class="mt-1 text-xl font-black text-slate-950">Grounding sources</h2></div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">{{ $session->sources->count() }} source chunk{{ $session->sources->count() === 1 ? '' : 's' }}</span>
            </div>
            <div class="mt-5 grid gap-4">
                @foreach($session->sources as $source)
                    @php $label = data_get($source->metadata,'label','S'.$loop->iteration); @endphp
                    <article class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3"><span class="rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-black text-white">{{ $label }}</span><div class="min-w-0"><h3 class="truncate font-black text-slate-900">{{ $source->title ?: 'Publication source' }}</h3><p class="mt-1 text-xs text-slate-400">{{ $source->locator ?: 'Indexed section' }}</p></div></div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-500 shadow-sm">{{ number_format(((float)$source->relevance_score) * 100,1) }}% relevance</span>
                        </div>
                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $source->excerpt }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if(!$guarded)
        <section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="font-black text-slate-950">Was this grounded answer useful?</h2><p class="mt-1 text-sm text-slate-500">Feedback improves the publication's adaptive retrieval pattern. Negative feedback is excluded from successful learning patterns.</p></div>
                @if($feedback)
                    <span class="rounded-full {{ $feedback === 'helpful' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-3 py-1.5 text-xs font-black">Feedback: {{ str_replace('_',' ',ucfirst($feedback)) }}</span>
                @endif
            </div>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('knowledge.companion.feedback', $session) }}">@csrf<input type="hidden" name="rating" value="helpful"><button class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-700 hover:bg-emerald-100">✓ Helpful</button></form>
                <form method="POST" action="{{ route('knowledge.companion.feedback', $session) }}">@csrf<input type="hidden" name="rating" value="not_helpful"><button class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-black text-amber-700 hover:bg-amber-100">Not helpful</button></form>
            </div>
        </section>
    @endif

    @if(!$guarded && $answerValidation !== [])
        <details class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
            <summary class="cursor-pointer font-black text-slate-800">Grounding validation details</summary>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-400">Citation coverage</p><p class="mt-1 font-black text-slate-900">{{ number_format(((float)($answerValidation['citation_coverage'] ?? 0))*100,1) }}%</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-400">Support coverage</p><p class="mt-1 font-black text-slate-900">{{ number_format(((float)($answerValidation['support_coverage'] ?? 0))*100,1) }}%</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-400">Support score</p><p class="mt-1 font-black text-slate-900">{{ number_format(((float)($answerValidation['support_score'] ?? 0))*100,1) }}%</p></div>
            </div>
        </details>
    @endif
</div>
@endsection
