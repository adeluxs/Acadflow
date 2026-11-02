@php
    $chartRows = collect($rows);
    $days = $chartRows->pluck('day')->unique()->sort()->values();
    $series = $chartRows->groupBy('series');
    $maximum = max(1, (float) $chartRows->max('value'));
    $colors = ['#4f46e5', '#059669', '#dc2626', '#d97706', '#0284c7'];
@endphp
<figure data-notification-chart class="min-w-0 rounded-2xl border border-slate-200 bg-white p-5">
    <figcaption class="font-bold">{{ $title }}</figcaption>
    @if($chartRows->isEmpty())<p class="mt-6 text-sm text-slate-500">No recorded events for this selection.</p>@else
    <div class="mt-3 flex flex-wrap gap-3">@foreach($series as $label => $values)<button type="button" data-chart-toggle="{{ $loop->index }}" aria-pressed="true" class="rounded-lg border px-2 py-1 text-xs" style="color:{{ $colors[$loop->index % count($colors)] }}">{{ $label }}</button>@endforeach</div>
    <svg viewBox="0 0 600 180" role="img" aria-label="{{ $title }}" class="mt-4 w-full"><path d="M28 10 V150 H585" fill="none" stroke="#cbd5e1"/>
        @foreach($series as $label => $values)
        @php
            $byDay = $values->keyBy('day');
            $points = $days->map(fn ($day, $i) => (28 + ($i * 552 / max(1, $days->count() - 1))).','. (150 - ((float) ($byDay[$day]['value'] ?? 0) / $maximum * 130)))->implode(' ');
        @endphp
        <g data-chart-line="{{ $loop->index }}"><polyline fill="none" stroke="{{ $colors[$loop->index % count($colors)] }}" stroke-width="2.5" points="{{ $points }}"/>
        @foreach($days as $day)<circle cx="{{ 28 + ($loop->index * 552 / max(1, $days->count() - 1)) }}" cy="{{ 150 - ((float) ($byDay[$day]['value'] ?? 0) / $maximum * 130) }}" r="3" fill="currentColor"><title>{{ $day }} · {{ $label }}: {{ $byDay[$day]['value'] ?? 0 }}</title></circle>@endforeach</g>
        @endforeach
        <text x="28" y="173" font-size="10" fill="#64748b">{{ $days->first() }}</text><text x="500" y="173" font-size="10" fill="#64748b">{{ $days->last() }}</text>
    </svg>
    <details class="mt-2 text-xs"><summary class="cursor-pointer font-semibold text-indigo-600">View recorded data</summary><div class="mt-3 max-h-56 overflow-auto"><table class="w-full text-left"><thead><tr><th>Date</th><th>Series</th><th>Count</th></tr></thead><tbody>@foreach($chartRows as $row)<tr><td class="py-1">{{ $row['day'] }}</td><td>{{ $row['series'] }}</td><td>{{ $row['value'] }}</td></tr>@endforeach</tbody></table></div></details>
    @endif
</figure>
