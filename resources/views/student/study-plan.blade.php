@extends('layouts.app')

@section('title', $plan->title)
@section('page-title', $plan->title)
@section('page-subtitle', ($plan->course?->code ?? '').' · Study plan')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @include('student._help', [
        'title' => 'Using your study plan',
        'steps' => [
            'Work through the steps in order — each shows a topic, an objective, an activity and an estimated time.',
            'Tick things off as you finish and revisit the plan through the week.',
            'Regenerate the plan if your deadlines or course coverage change.',
        ],
        'tip' => 'Aim for consistent short sessions rather than one long cram — the plan is a guide, not a rule.',
    ])

    @php($items = is_array($plan->items) ? $plan->items : [])

    @if($plan->status !== 'ready' || empty($items))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            @if($plan->status === 'failed')
                This plan could not be generated ({{ $plan->error_code ?: 'no items returned' }}). Try again.
            @else
                Your plan is still being generated. Refresh in a moment.
            @endif
        </div>
    @else
        <section class="space-y-3">
            @foreach($items as $index => $item)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Step {{ $index + 1 }} · {{ $item['estimated_minutes'] ?? 30 }} min</p>
                    <h3 class="mt-1 font-black text-slate-900">{{ $item['topic'] ?? '' }}</h3>
                    @if(!empty($item['objective']))<p class="mt-1 text-sm text-slate-600"><strong>Objective:</strong> {{ $item['objective'] }}</p>@endif
                    @if(!empty($item['activity']))<p class="mt-1 text-sm text-slate-600"><strong>Activity:</strong> {{ $item['activity'] }}</p>@endif
                </article>
            @endforeach
        </section>
    @endif

    <a href="{{ route('study-plans.index') }}" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All study plans</a>
</div>
@endsection
