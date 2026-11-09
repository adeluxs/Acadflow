@php
    $challengePopup = app(\App\Services\Knowledge\ChallengePopupService::class)->forRequest(request());
@endphp
@if($challengePopup)
<div id="challenge-popup" data-delay="{{ $challengePopup['delay'] }}" data-seen-url="{{ route('knowledge.challenge-popup.seen') }}"
     class="fixed inset-0 z-[70] hidden items-end justify-center bg-slate-950/60 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="challenge-popup-title">
    <div class="relative w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-800 px-6 pb-6 pt-5 text-white">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-black uppercase tracking-[.2em] text-amber-300">AcadSyntra challenges</p>
                <button type="button" data-challenge-popup-close class="-mr-2 -mt-1 rounded-full p-2 text-indigo-100 hover:bg-white/10" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <h2 id="challenge-popup-title" class="mt-1 text-2xl font-black">Win cash for reading and writing 🏆</h2>
            <p class="mt-1 text-sm text-indigo-100">{{ ($challengePopup['writing']->count() + $challengePopup['reading']->count()) === 1 ? 'A challenge is' : 'Challenges are' }} live on the Knowledge Hub. Jump in before it closes.</p>
        </div>
        <div class="max-h-[60vh] space-y-3 overflow-y-auto p-5">
            @foreach($challengePopup['writing'] as $writing)
                @php($open = $writing->phase() === 'open')
                <a href="{{ route('knowledge.writing.show', $writing) }}" data-challenge-popup-go class="block rounded-2xl border border-violet-200 bg-violet-50 p-4 hover:border-violet-400">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-black uppercase tracking-wide text-violet-700">✍️ Writing Challenge · {{ $open ? 'Open now' : 'Opens '.$writing->starts_at->format('j M') }}</span>
                        <span class="text-lg font-black text-slate-950">₦{{ number_format(intdiv($writing->prizePoolMinor(), 100)) }}</span>
                    </div>
                    <p class="mt-1 font-black text-slate-950">{{ $writing->title }}</p>
                    @if($writing->theme)<p class="text-sm text-slate-600">Theme: {{ $writing->theme }}</p>@endif
                    <p class="mt-2 text-xs font-semibold text-slate-500">{{ $open ? 'Entries close '.$writing->submission_deadline->format('j M') : 'Start drafting now' }} · <span class="text-violet-700">{{ $open ? 'Enter now →' : 'See details →' }}</span></p>
                </a>
            @endforeach
            @foreach($challengePopup['reading'] as $reading)
                @php($running = $reading->isRunning())
                <a href="{{ route('knowledge.rewards.challenges.show', $reading) }}" data-challenge-popup-go class="block rounded-2xl border border-emerald-200 bg-emerald-50 p-4 hover:border-emerald-400">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-black uppercase tracking-wide text-emerald-700">📚 Reading Challenge · {{ $running ? 'Live now' : 'Starts '.$reading->starts_at->format('j M') }}</span>
                        <span class="text-lg font-black text-slate-950">₦{{ number_format(intdiv((int) $reading->prize_pool_minor, 100)) }}</span>
                    </div>
                    <p class="mt-1 font-black text-slate-950">{{ $reading->title }}</p>
                    <p class="mt-2 text-xs font-semibold text-slate-500">Ends {{ $reading->ends_at->format('j M') }}{{ $reading->min_passed_reads ? ' · pass '.$reading->min_passed_reads.' guides to qualify' : '' }} · <span class="text-emerald-700">{{ $running ? 'See the leaderboard →' : 'See details →' }}</span></p>
                </a>
            @endforeach
        </div>
        <div class="flex items-center justify-between gap-3 border-t px-5 py-3">
            <a href="{{ route('knowledge.index') }}" data-challenge-popup-go class="text-sm font-bold text-indigo-700">Browse the Knowledge Hub</a>
            <button type="button" data-challenge-popup-close class="rounded-xl px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">Maybe later</button>
        </div>
    </div>
</div>
<script>
    (() => {
        const popup = document.getElementById('challenge-popup');
        if (!popup) return;
        const delay = Math.max(0, Number(popup.dataset.delay || 0));
        // Browsers cap timers at ~24 days; anything that long just waits for the next page load.
        if (delay > 86400) return;
        let shown = false;
        const markSeen = () => fetch(popup.dataset.seenUrl, {
            method: 'POST', keepalive: true, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Accept': 'application/json' },
        }).catch(() => {});
        const open = () => {
            // Don't cover another open dialog (e.g. the subscribe prompt); try again shortly.
            if (document.querySelector('[role="dialog"][aria-modal="true"]:not(#challenge-popup):not(.hidden)')) { setTimeout(open, 60000); return; }
            if (shown) return;
            shown = true;
            popup.classList.remove('hidden');
            popup.classList.add('flex');
            markSeen();
            popup.querySelector('[data-challenge-popup-close]')?.focus();
        };
        const close = () => { popup.classList.add('hidden'); popup.classList.remove('flex'); };
        popup.querySelectorAll('[data-challenge-popup-close]').forEach((button) => button.addEventListener('click', close));
        popup.addEventListener('click', (event) => { if (event.target === popup) close(); });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && shown) close(); });
        setTimeout(open, delay === 0 ? 1500 : delay * 1000);
    })();
</script>
@endif
