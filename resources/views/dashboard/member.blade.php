@extends('layouts.app')
@section('title','Member Dashboard')
@section('page-title','Dashboard')
@section('page-subtitle','Welcome back, '.(auth()->user()->first_name ?? 'Member').' 👋')
@section('content')
@php
    $dashboardUser = auth()->user();
    $routeFeatureVisible = function (string $routeName) use ($dashboardUser): bool {
        $feature = \App\Services\FeatureAccessService::featureForRoute($routeName);
        return ! $feature || \App\Services\FeatureAccessService::shouldShowInNavigation($feature, $dashboardUser);
    };
    $featureVisible = fn (string $feature): bool => \App\Services\FeatureAccessService::shouldShowInNavigation($feature, $dashboardUser);
@endphp
<div class="space-y-5">
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Publications',$stats['publications'],'knowledge.manage','bg-indigo-50 text-indigo-600'],
            ['Research Projects',$stats['research_projects'],'research.index','bg-blue-50 text-blue-600'],
            ['Communities',$stats['communities'],'knowledge.communities.index','bg-emerald-50 text-emerald-600'],
            ['Groups',$stats['groups'],'groups.index','bg-orange-50 text-orange-600'],
        ] as $card)@if($routeFeatureVisible($card[2]))<a href="{{ route($card[2]) }}" class="acad-card acad-card-hover p-4"><div class="flex items-start justify-between"><div><p class="text-xs text-slate-500">{{ $card[0] }}</p><p class="mt-2 text-2xl font-black">{{ $card[1] }}</p><p class="mt-1 text-[10px] text-slate-400">Your workspace</p></div><span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $card[3] }}">↗</span></div></a>@endif @endforeach
    </section>
    <section class="grid gap-5 xl:grid-cols-[1.15fr_.85fr]">
        @if($featureVisible('knowledge_hub'))
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Recent Publications</h2><a class="text-xs font-semibold acad-link" href="{{ route('knowledge.manage') }}">Manage</a></div><div class="mt-4 space-y-2">@forelse($publications as $publication)<a href="{{ route('knowledge.show',$publication) }}" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">▤</span><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">{{ $publication->title }}</p><p class="text-[10px] text-slate-500">{{ str($publication->status)->headline() }}</p></div><span class="text-[10px] text-slate-400">{{ $publication->updated_at?->diffForHumans() }}</span></a>@empty<p class="text-xs text-slate-500">No publications yet.</p>@endforelse</div></article>
        @endif
        @if($featureVisible('ai_assistant'))
        <article class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-white to-indigo-50 p-5 shadow-sm"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-950 text-xs font-bold text-white">AI</div><div><h2 class="text-sm font-bold">AI Research Assistant</h2><p class="text-[10px] text-slate-500">Write, discover, and organize knowledge</p></div></div><div class="mt-4 space-y-2 text-xs"><a href="{{ route('ai.assistant') }}" class="block rounded-lg bg-indigo-600 p-3 font-semibold text-white shadow-sm">Ask AI Assistant</a><a href="{{ route('research.create') }}" class="block rounded-lg bg-white p-3 shadow-sm">Start a research project</a><a href="{{ route('knowledge.manage.create') }}" class="block rounded-lg bg-white p-3 shadow-sm">Draft a publication</a><a href="{{ route('knowledge.search') }}" class="block rounded-lg bg-white p-3 shadow-sm">Explore Knowledge Hub</a></div></article>
        @endif
    </section>
    <section class="grid gap-5 xl:grid-cols-2">
        @if($featureVisible('academic_events'))<article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Upcoming Events</h2><a href="{{ route('knowledge.events.index') }}" class="text-xs font-semibold acad-link">Discover</a></div><div class="mt-4 space-y-2">@forelse($eventRegistrations as $registration)@if($registration->event)<a href="{{ route('knowledge.events.show',$registration->event) }}" class="block rounded-xl border-b border-slate-100 py-3"><p class="text-xs font-semibold">{{ $registration->event->title }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $registration->event->starts_at?->format('M j, Y · g:i A') }} · {{ ucfirst($registration->status) }}</p></a>@endif @empty<p class="text-xs text-slate-500">No event registrations.</p>@endforelse</div></article>@endif
        @if($featureVisible('academic_challenges'))<article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Challenge Activity</h2><a href="{{ route('knowledge.challenges.index') }}" class="text-xs font-semibold acad-link">Participate</a></div><div class="mt-4 space-y-2">@forelse($challengeEntries as $entry)@if($entry->challenge)<a href="{{ route('knowledge.challenges.show',$entry->challenge) }}" class="block rounded-xl border-b border-slate-100 py-3"><p class="text-xs font-semibold">{{ $entry->challenge->title }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $entry->is_final ? 'Final submission' : 'Draft' }}{{ $entry->rank ? ' · Rank '.$entry->rank : '' }}</p></a>@endif @empty<p class="text-xs text-slate-500">No challenge activity.</p>@endforelse</div></article>@endif
    </section>
</div>
@endsection
