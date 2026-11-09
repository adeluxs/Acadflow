@php
    $phase = $challenge->phase();
    $steps = [
        ['key' => 'upcoming', 'label' => 'Opens', 'date' => $challenge->starts_at],
        ['key' => 'open', 'label' => 'Entries close', 'date' => $challenge->submission_deadline],
        ['key' => 'judging', 'label' => 'Judging', 'date' => $challenge->results_at],
        ['key' => 'results', 'label' => 'Winners', 'date' => $challenge->results_published_at ?? $challenge->results_at],
    ];
    $order = ['upcoming' => 0, 'open' => 1, 'judging' => 2, 'results' => 3];
    $current = $order[$phase] ?? 0;
@endphp
<ol class="grid grid-cols-4 gap-2" aria-label="Challenge timeline">
    @foreach($steps as $i => $step)
        <li class="rounded-2xl px-3 py-3 text-center {{ $i < $current ? 'bg-white/10 text-white/70' : ($i === $current ? 'bg-amber-300 text-slate-950 shadow-lg' : 'bg-white/5 text-white/50') }}" @if($i === $current) aria-current="step" @endif>
            <span class="block text-[11px] font-black uppercase tracking-wider">{{ $i < $current ? '✓ ' : '' }}{{ $step['label'] }}</span>
            <span class="mt-0.5 block text-xs font-semibold">{{ $step['date']->format('j M') }}</span>
        </li>
    @endforeach
</ol>
