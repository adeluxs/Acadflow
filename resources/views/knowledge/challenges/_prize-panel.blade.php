@if($prizeFund)
@php
    $isOrganizer = auth()->id() === $challenge->organizer_id;
    $places = ['🥇', '🥈', '🥉'];
    $typeLabel = $prizeFund->prize_type === 'cash' ? 'Cash' : 'Study credit';
@endphp
<section class="overflow-hidden rounded-3xl border {{ $prizeFund->status === 'unfunded' ? 'border-slate-200' : 'border-emerald-300' }} bg-white shadow-sm">
    <div class="{{ $prizeFund->status === 'unfunded' ? 'bg-slate-900' : 'bg-gradient-to-br from-emerald-700 via-emerald-800 to-slate-900' }} p-5 text-white">
        <p class="text-xs font-black uppercase tracking-[.2em] {{ $prizeFund->status === 'unfunded' ? 'text-slate-300' : 'text-emerald-200' }}">
            {{ match ($prizeFund->status) { 'funded' => '🔒 Prize guaranteed · held by AcadSyntra', 'settled' => '✅ Prizes paid out', default => '⏳ Prize not funded yet' } }}
        </p>
        <p class="mt-2 text-3xl font-black">₦{{ number_format(intdiv($prizeFund->total_minor, 100)) }}</p>
        <p class="text-sm text-emerald-100">{{ $typeLabel }} prize pool</p>
    </div>
    <ol class="divide-y px-5">
        @foreach($prizeFund->prizes as $i => $amount)
            @php($award = $prizeFund->awards->firstWhere('rank', $i + 1))
            <li class="flex items-center justify-between gap-3 py-3 text-sm">
                <span class="font-semibold text-slate-700">{{ $places[$i] ?? '🏅' }} {{ $i + 1 }}{{ ['st','nd','rd'][$i] ?? 'th' }} place{{ $award ? ' · '.$award->user?->full_name : '' }}</span>
                <strong class="{{ $award ? 'text-emerald-700' : 'text-slate-900' }}">₦{{ number_format(intdiv((int) $amount, 100)) }}{{ $award ? ' ✓' : '' }}</strong>
            </li>
        @endforeach
    </ol>
    @if($prizeFund->status === 'settled' && $prizeFund->refunded_minor > 0)
        <p class="px-5 pb-4 text-xs text-slate-500">₦{{ number_format(intdiv($prizeFund->refunded_minor, 100)) }} for unclaimed places went back to the creator.</p>
    @endif

    @if($isOrganizer && $prizeFund->status === 'unfunded' && $prizePlan)
        <div class="border-t bg-amber-50 p-5 text-sm">
            <p class="font-black text-slate-950">Fund your prizes to go live</p>
            <dl class="mt-3 space-y-1 text-slate-700">
                <div class="flex justify-between"><dt>Prizes</dt><dd>₦{{ number_format($prizeFund->total_minor / 100) }}</dd></div>
                <div class="flex justify-between"><dt>Platform fee ({{ $prizeFund->fee_percent }}%)</dt><dd>₦{{ number_format($prizeFund->fee_minor / 100) }}</dd></div>
                <div class="flex justify-between border-t pt-1 font-black"><dt>Total</dt><dd>₦{{ number_format($prizePlan['charge'] / 100) }}</dd></div>
                @if($prizePlan['from_earnings'] > 0)<div class="flex justify-between text-xs"><dt>From withdrawable earnings</dt><dd>₦{{ number_format($prizePlan['from_earnings'] / 100) }}</dd></div>@endif
                @if($prizePlan['from_spending'] > 0)<div class="flex justify-between text-xs"><dt>From wallet balance</dt><dd>₦{{ number_format($prizePlan['from_spending'] / 100) }}</dd></div>@endif
            </dl>
            @error('fund')<p class="mt-3 rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-800">{{ $message }}</p>@enderror
            @if($prizePlan['shortfall'] > 0)
                <p class="mt-3 rounded-xl bg-white p-3 text-xs text-slate-700">You need ₦{{ number_format($prizePlan['shortfall'] / 100) }} more{{ $prizeFund->prize_type === 'cash' ? ' (cash prizes can\'t be paid with study credit)' : '' }}.</p>
                <a href="{{ route('commerce.wallet') }}" class="mt-3 block rounded-xl bg-slate-950 px-4 py-2 text-center font-bold text-white">Top up wallet</a>
            @else
                <form method="POST" action="{{ route('knowledge.challenges.prizes.fund', $challenge) }}" class="mt-3">@csrf
                    <input type="hidden" name="publish" value="{{ in_array($challenge->status, ['draft', 'pending_review'], true) ? 1 : 0 }}">
                    <button class="w-full rounded-xl bg-emerald-600 px-4 py-2 font-black text-white">🔒 Fund ₦{{ number_format($prizePlan['charge'] / 100) }}{{ in_array($challenge->status, ['draft', 'pending_review'], true) ? ' and publish' : '' }}</button>
                </form>
            @endif
        </div>
    @elseif($isOrganizer && $prizeFund->status === 'funded')
        <div class="border-t bg-emerald-50 p-5 text-sm">
            @if($challenge->status === 'completed' && $challenge->results_published_at)
                <p class="font-black text-emerald-900">Results are out. Time to reward your winners! 🎉</p>
                @error('pay')<p class="mt-2 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                <form method="POST" action="{{ route('knowledge.challenges.prizes.pay', $challenge) }}" class="mt-3">@csrf
                    <button class="w-full rounded-xl bg-amber-400 px-4 py-2 font-black text-slate-950">💸 Pay winners now</button>
                </form>
                @php($autoDays = app(\App\Services\Knowledge\ReadEarnSettings::class)->int('knowledge_challenge_prize_auto_days'))
                @if($autoDays > 0)<p class="mt-2 text-xs text-emerald-800">If you don't, AcadSyntra pays them automatically {{ $autoDays }} days after results.</p>@endif
            @else
                <p class="text-emerald-900">Your prize is safe. When you publish ranked results, a "Pay winners" button appears here.</p>
            @endif
        </div>
    @elseif($prizeFund->status === 'funded')
        <p class="border-t bg-emerald-50 px-5 py-3 text-xs font-semibold text-emerald-900">The money is already with AcadSyntra, so winners are guaranteed to be paid.</p>
    @endif
</section>
@endif
