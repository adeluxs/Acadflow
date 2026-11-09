<div class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-emerald-50 p-6 sm:p-8">
    <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-600">Reader Pass</p>
    @guest
        <h2 class="mt-2 text-2xl font-black text-slate-950">Sign in to keep reading</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">You've read {{ $readerGate['free_reads'] }} free guide{{ $readerGate['free_reads'] === 1 ? '' : 's' }}. Create a free account to get more free reads every month.</p>
        <div class="mt-5 flex flex-wrap gap-2"><a href="{{ route('register') }}" class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-black text-white">Create free account</a><a href="{{ route('login') }}" class="rounded-xl border border-indigo-200 bg-white px-5 py-3 text-sm font-black text-indigo-800">Sign in</a></div>
    @else
        <h2 class="mt-2 text-2xl font-black text-slate-950">You've used your {{ $readerGate['free_reads'] }} free reads this month</h2>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Get the Reader Pass to keep reading every guide on the Knowledge Hub, and earn while you learn.</p>
        <ul class="mt-4 grid gap-2 text-sm text-slate-700 sm:grid-cols-3">
            <li class="rounded-xl bg-white p-3 shadow-sm"><strong class="block text-slate-950">Unlimited reading</strong>Every free guide and story</li>
            <li class="rounded-xl bg-white p-3 shadow-sm"><strong class="block text-slate-950">Study credit</strong>Pass a guide's quiz to earn</li>
            <li class="rounded-xl bg-white p-3 shadow-sm"><strong class="block text-slate-950">Monthly cash pool</strong>Top learners share real cash</li>
        </ul>
        <div class="mt-5">@include('knowledge.rewards._pass-buttons')</div>
        <p class="mt-3 text-xs text-slate-500">Your free reads reset on the 1st. <a href="{{ route('knowledge.rewards.index') }}" class="font-bold text-indigo-700 underline">How Read &amp; Earn works</a></p>
    @endguest
</div>
