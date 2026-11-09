@php
    $prizeSettings = app(\App\Services\Knowledge\ReadEarnSettings::class);
    $prizeFund = ($editing ?? false) ? $challenge->prizeFund : null;
    $prizeLocked = $prizeFund && $prizeFund->status !== 'unfunded';
    $prizeType = old('prize_type', $prizeFund->prize_type ?? 'none');
    $prizeText = old('prizes_text', $prizeFund ? collect($prizeFund->prizes)->map(fn ($m) => intdiv((int) $m, 100))->implode(', ') : '');
    $feePercent = $prizeSettings->int('knowledge_challenge_prize_fee_percent');
@endphp
@if($prizeSettings->bool('knowledge_challenge_prizes_enabled') || $prizeFund)
<section class="overflow-hidden rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-emerald-50">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-100 px-5 py-4">
        <div>
            <p class="text-xs font-black uppercase tracking-[.2em] text-amber-700">💰 Prize vault</p>
            <h2 class="text-lg font-black text-slate-950">Back your challenge with a guaranteed prize</h2>
        </div>
        <span class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-black text-white">🔒 Held by AcadSyntra until winners are paid</span>
    </div>
    @if($prizeLocked)
        <div class="p-5 text-sm text-slate-700">
            <p class="font-bold text-emerald-800">These prizes are funded and locked.</p>
            <p class="mt-1">{{ \App\Models\ChallengePrizeFund::TYPES[$prizeFund->prize_type] ?? $prizeFund->prize_type }}: {{ collect($prizeFund->prizes)->map(fn ($m) => '₦'.number_format(intdiv((int) $m, 100)))->implode(' · ') }}. Prizes can't change once money is held, so entrants can trust them.</p>
        </div>
    @else
        <div class="grid gap-4 p-5 md:grid-cols-[1fr_2fr]" id="prize-vault" data-fee="{{ $feePercent }}">
            <div>
                <label class="font-medium">Prize type</label>
                <select name="prize_type" class="mt-1 w-full rounded-xl border-slate-300" data-prize-type>
                    <option value="none" @selected($prizeType === 'none')>No funded prize</option>
                    <option value="cash" @selected($prizeType === 'cash')>💵 Cash (winners can withdraw)</option>
                    <option value="study_credit" @selected($prizeType === 'study_credit')>🎓 Study credit (spend on AcadSyntra)</option>
                </select>
            </div>
            <div>
                <label class="font-medium">Prizes in naira, 1st place first</label>
                <input name="prizes_text" value="{{ $prizeText }}" placeholder="e.g. 20000, 10000, 5000" class="mt-1 w-full rounded-xl border-slate-300" data-prizes>
                <p class="mt-1 text-xs text-slate-500">Up to 10 places, at least ₦{{ number_format((float) $prizeSettings->get('knowledge_challenge_prize_min')) }} each. You can also give non-money rewards in the Rewards box above.</p>
            </div>
            <div class="rounded-2xl bg-slate-950 p-4 text-white md:col-span-2" data-quote hidden>
                <div class="grid gap-3 text-center sm:grid-cols-3">
                    <div><p class="text-xs text-slate-400">Prizes</p><p class="text-xl font-black" data-q-total>₦0</p></div>
                    <div><p class="text-xs text-slate-400">Platform fee ({{ $feePercent }}%)</p><p class="text-xl font-black" data-q-fee>₦0</p></div>
                    <div><p class="text-xs text-amber-300">You pay once</p><p class="text-xl font-black text-amber-300" data-q-charge>₦0</p></div>
                </div>
                <p class="mt-3 text-xs text-slate-300">You fund it from your wallet when you publish. The challenge goes live only after the prize is funded. Places nobody wins are refunded to your wallet (the fee is not). Cash prizes must come from money you added or your withdrawable earnings, not study credit.</p>
            </div>
        </div>
        <script>
            (() => {
                const box = document.getElementById('prize-vault'); if (!box) return;
                const type = box.querySelector('[data-prize-type]'), input = box.querySelector('[data-prizes]'), quote = box.querySelector('[data-quote]');
                const pct = Number(box.dataset.fee || 0), fmt = n => '₦' + n.toLocaleString();
                const update = () => {
                    const amounts = (input.value || '').split(',').map(v => parseInt(v.trim(), 10)).filter(v => v > 0);
                    const total = amounts.reduce((a, b) => a + b, 0), fee = Math.ceil(total * pct / 100);
                    quote.hidden = type.value === 'none' || total === 0;
                    quote.querySelector('[data-q-total]').textContent = fmt(total);
                    quote.querySelector('[data-q-fee]').textContent = fmt(fee);
                    quote.querySelector('[data-q-charge]').textContent = fmt(total + fee);
                };
                type.addEventListener('change', update); input.addEventListener('input', update); update();
            })();
        </script>
    @endif
</section>
@endif
