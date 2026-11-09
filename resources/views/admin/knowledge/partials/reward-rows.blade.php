@if($rows->isNotEmpty())
    <div class="mt-4 max-h-96 overflow-y-auto rounded-xl border">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Recipient</th>@if($pointsLabel)<th class="px-3 py-2 text-right">{{ ucfirst($pointsLabel) }}</th>@endif<th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2">Status</th><th class="px-3 py-2"></th></tr></thead>
            <tbody class="divide-y">
                @foreach($rows as $row)
                    <tr class="{{ $row->status === 'excluded' ? 'bg-rose-50/60 text-slate-400' : '' }}">
                        <td class="px-3 py-2">{{ $row->rank }}</td>
                        <td class="px-3 py-2"><p class="font-semibold text-slate-800">{{ $row->user?->full_name ?? 'User #'.$row->user_id }}</p><p class="text-xs text-slate-500">{{ $row->user?->email }}@if(! empty($row->metadata['reads'])) · {{ $row->metadata['reads'] }} reads @endif @if(! empty($row->metadata['average_score'])) · avg {{ $row->metadata['average_score'] }}% @endif @if(! empty($row->metadata['entry_title'])) · {{ $row->metadata['entry_title'] }} @endif</p>@if($row->note)<p class="text-xs text-rose-600">{{ $row->note }}</p>@endif</td>
                        @if($pointsLabel)<td class="px-3 py-2 text-right">{{ number_format($row->points) }}</td>@endif
                        <td class="px-3 py-2 text-right font-bold">₦{{ number_format($row->amount_minor / 100) }}</td>
                        <td class="px-3 py-2">{{ ucfirst($row->status) }}</td>
                        <td class="px-3 py-2 text-right">
                            @if($row->status !== 'paid')
                                <form method="POST" action="{{ route('admin.knowledge.read-earn.payouts.exclude', $row) }}" class="flex justify-end gap-1">@csrf @method('PATCH')
                                    <input type="hidden" name="excluded" value="{{ $row->status === 'excluded' ? 0 : 1 }}">
                                    @if($row->status !== 'excluded')<input name="note" maxlength="500" placeholder="Reason" class="w-28 rounded-lg border-slate-200 px-2 py-1 text-xs">@endif
                                    <button class="rounded-lg border px-2 py-1 text-xs font-bold {{ $row->status === 'excluded' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $row->status === 'excluded' ? 'Include' : 'Exclude' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
