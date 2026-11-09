{{-- Shown once per session after the reader has opened a few guides this month. --}}
<div id="reader-pass-prompt" role="dialog" aria-modal="true" aria-labelledby="reader-pass-prompt-title" class="fixed inset-0 z-[70] flex items-end justify-center bg-slate-950/50 p-4 sm:items-center">
    <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-600">Enjoying the Knowledge Hub?</p>
        <h2 id="reader-pass-prompt-title" class="mt-2 text-2xl font-black text-slate-950">Read more, and get rewarded for it</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">
            You've read {{ $readerGate['reads_used'] }} of your {{ $readerGate['free_reads'] }} free guides this month.
            With the Reader Pass you can keep reading everything, earn study credit when you pass a guide's quiz, and share the monthly cash pool for top learners.
        </p>
        <div class="mt-5">@include('knowledge.rewards._pass-buttons')</div>
        <button type="button" data-close-reader-prompt class="mt-3 w-full rounded-xl px-4 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-50">Maybe later, keep reading</button>
    </div>
</div>
<script>
    document.querySelectorAll('[data-close-reader-prompt]').forEach((button) => button.addEventListener('click', () => document.getElementById('reader-pass-prompt')?.remove()));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') document.getElementById('reader-pass-prompt')?.remove(); });
</script>
