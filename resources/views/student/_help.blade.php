{{--
    Reusable student guidance card with a link back to the AI Assistant.
    Include with:
    @include('student._help', ['title' => '...', 'steps' => [...], 'tip' => '...'])
--}}
@php($steps = $steps ?? [])

<a href="{{ route('ai.assistant') }}" class="inline-flex w-fit items-center gap-1.5 text-xs font-bold text-indigo-700 hover:text-indigo-900">
    <span aria-hidden="true">&larr;</span> Back to AI Assistant
</a>

@if($steps !== [])
    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs leading-5 text-indigo-900">
        <p class="text-xs font-black uppercase tracking-wide text-indigo-700">{{ $title ?? 'How to use this tool' }}</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            @foreach($steps as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ol>
        @if(!empty($tip))
            <p class="mt-2 rounded-xl bg-white/70 p-2.5"><strong>Tip:</strong> {{ $tip }}</p>
        @endif
    </div>
@endif
