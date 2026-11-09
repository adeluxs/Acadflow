@extends('layouts.app')
@section('title','Academic Challenges')
@section('page-title','Academic Challenges')
@section('page-subtitle','Competitions, innovation challenges and evidence-based judging')
@section('content')
@include('knowledge._nav')
<div class="space-y-7">
    <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-amber-950 via-slate-950 to-orange-950 p-7 text-white shadow-xl sm:p-9">
        <div class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-amber-400/20 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[.24em] text-amber-300">Community challenges · made by students, lecturers &amp; clubs</p>
                <h1 class="mt-2 text-3xl font-black sm:text-4xl">Start a challenge. Back it with a real prize.</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">Anyone verified can host a challenge. Put up cash or study credit and AcadSyntra holds it in a locked prize vault until the winners are paid, so every 🔒 badge you see is a prize that is guaranteed.</p>
                <div class="mt-5 flex flex-wrap gap-3 text-xs font-bold">
                    <span class="rounded-full bg-emerald-400/90 px-3 py-1.5 text-emerald-950">🔒 ₦{{ number_format(intdiv($guaranteedPrizes, 100)) }} in guaranteed prizes right now</span>
                    <span class="rounded-full bg-white/10 px-3 py-1.5">1 · Create</span><span class="rounded-full bg-white/10 px-3 py-1.5">2 · Fund the vault</span><span class="rounded-full bg-white/10 px-3 py-1.5">3 · Judge</span><span class="rounded-full bg-white/10 px-3 py-1.5">4 · Pay winners in one tap</span>
                </div>
            </div>
            @auth @can('create',App\Models\AcademicChallenge::class)<a href="{{ route('knowledge.challenges.create') }}" class="shrink-0 rounded-xl bg-amber-400 px-5 py-3 text-sm font-black text-slate-950 shadow-lg hover:bg-amber-300">＋ Host a challenge</a>@endcan @endauth
        </div>
    </section>
    @if($official)
        <a href="{{ route('knowledge.writing.show', $official) }}" class="flex flex-col gap-3 rounded-3xl bg-gradient-to-r from-indigo-950 via-violet-900 to-fuchsia-800 p-5 text-white shadow-lg transition hover:-translate-y-0.5 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-xs font-black uppercase tracking-[.2em] text-amber-300">🏆 Official · run and paid by AcadSyntra</p><p class="mt-1 text-lg font-black">{{ $official->title }}</p><p class="text-sm text-indigo-100">{{ $official->phase() === 'open' ? 'Entries close '.$official->submission_deadline->format('j M') : 'Opens '.$official->starts_at->format('j M') }}</p></div>
            <span class="text-2xl font-black text-amber-300">₦{{ number_format(intdiv($official->prizePoolMinor(), 100)) }} →</span>
        </a>
    @endif
    <form method="GET" class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-4"><input name="q" value="{{ $filters['q']??'' }}" placeholder="Search challenges…" class="rounded-xl border-slate-300"><select name="type" class="rounded-xl border-slate-300"><option value="">All types</option>@foreach(['writing','research','tutorial','project_documentation','innovation','siwes_report'] as $type)<option value="{{ $type }}" @selected(($filters['type']??'')===$type)>{{ str($type)->headline() }}</option>@endforeach</select><select name="status" class="rounded-xl border-slate-300"><option value="">All stages</option>@foreach(['upcoming','active','judging','completed','mine'] as $status)<option value="{{ $status }}" @selected(($filters['status']??'')===$status)>{{ ucfirst($status) }}</option>@endforeach</select><button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white">Filter</button></form>
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">@forelse($challenges as $challenge)<a href="{{ route('knowledge.challenges.show',$challenge) }}" class="group flex min-h-72 flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-amber-200 hover:shadow-xl"><div class="flex items-center justify-between"><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ str($challenge->challenge_type)->headline() }}</span><span class="text-xs font-bold text-slate-400">{{ str($challenge->participation_mode)->headline() }}</span></div>@if(in_array($challenge->prizeFund?->status,['funded','settled'],true))<div class="mt-4 flex items-center justify-between rounded-2xl bg-gradient-to-r from-emerald-600 to-emerald-800 px-4 py-2.5 text-white"><span class="text-xs font-black uppercase tracking-wide">{{ $challenge->prizeFund->status==='settled'?'✅ Prizes paid':'🔒 Prize guaranteed' }}</span><span class="text-lg font-black">₦{{ number_format(intdiv($challenge->prizeFund->total_minor,100)) }}<span class="text-xs font-semibold text-emerald-100"> {{ $challenge->prizeFund->prize_type==='cash'?'cash':'credit' }}</span></span></div>@endif<h2 class="mt-5 text-xl font-black text-slate-900 group-hover:text-amber-700">{{ $challenge->title }}</h2><p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $challenge->description }}</p><div class="mt-auto grid grid-cols-2 gap-3 border-t border-slate-100 pt-5"><div><p class="text-[10px] font-black uppercase text-slate-400">Deadline</p><p class="mt-1 text-sm font-bold text-slate-700">{{ ($challenge->submission_deadline??$challenge->ends_at)->format('M j') }}</p></div><div class="text-right"><p class="text-[10px] font-black uppercase text-slate-400">Entries</p><p class="mt-1 text-sm font-bold text-slate-700">{{ $challenge->entries_count }} · {{ str($challenge->status)->headline() }}</p></div></div></a>@empty<div class="md:col-span-2 xl:col-span-3 rounded-[2rem] border border-dashed border-slate-300 bg-white p-14 text-center"><h2 class="text-xl font-black text-slate-900">No challenges match these filters</h2><p class="mt-2 text-sm text-slate-500">Explore another category or stage.</p></div>@endforelse</div>{{ $challenges->links() }}
</div>
@endsection
