@extends('layouts.app')

@section('title', 'Deadline plan')
@section('page-title', 'Deadline plan')
@section('page-subtitle', $plan->course?->code)

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @include('student._help', [
        'title' => 'Working the plan',
        'steps' => [
            'Start at Step 1 and work down the list; each step lists a task and suggested timing.',
            'Regenerate the plan from the deadline coach if anything changes.',
        ],
        'tip' => 'Do the earliest step first — big deadlines are much easier once they are split into small ones.',
    ])

    @php($items = is_array($plan->items) ? $plan->items : [])

    @if($plan->summary)
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-sm text-slate-700">{{ $plan->summary }}</div>
    @endif

    @if(empty($items))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            No action plan was generated ({{ $plan->error_code ?: 'no items returned' }}). Try again, or confirm a provider is configured in AI Settings.
        </div>
    @else
        <section class="space-y-3">
            @foreach($items as $index => $item)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Step {{ $index + 1 }} @if(!empty($item['due']))· due {{ $item['due'] }}@endif</p>
                    <h3 class="mt-1 font-black text-slate-900">{{ $item['title'] ?? 'Task' }}</h3>
                    @if(!empty($item['steps']) && is_array($item['steps']))
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">
                            @foreach($item['steps'] as $step)
                                <li>{{ is_array($step) ? json_encode($step) : $step }}</li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    <a href="{{ route('deadline-coach.index') }}" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All deadline plans</a>
</div>
@endsection
