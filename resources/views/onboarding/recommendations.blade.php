@extends('layouts.onboarding')
@section('title', 'Your recommendations')
@section('content')
<div class="mx-auto max-w-5xl">
    <div class="rounded-3xl bg-gradient-to-br from-blue-700 to-indigo-800 p-8 text-white shadow-xl">
        <p class="text-sm font-semibold text-blue-100">Onboarding complete</p>
        <h1 class="mt-2 text-4xl font-black">Your AcadFlow workspace is ready.</h1>
        <p class="mt-3 max-w-2xl text-blue-100">Start with recommendations based on your interests. Nothing is joined or followed without your action.</p>
        <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-2xl bg-white px-5 py-3 font-bold text-blue-800">Open dashboard</a>
    </div>

    <section class="mt-8"><div class="flex items-end justify-between"><div><p class="text-sm font-semibold text-blue-700">Connect</p><h2 class="text-2xl font-black">Communities you may like</h2></div><a class="text-sm font-bold text-blue-700" href="{{ route('knowledge.communities.index') }}">View all</a></div><div class="mt-4 grid gap-4 md:grid-cols-3">@forelse($communities as $community)<a href="{{ route('knowledge.communities.show', $community) }}" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-blue-400"><h3 class="font-bold">{{ $community->name }}</h3><p class="mt-2 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($community->description, 110) }}</p><p class="mt-4 text-xs font-semibold text-slate-400">{{ $community->members_count }} members</p></a>@empty<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-slate-500 md:col-span-3">More community recommendations will appear as content is published.</div>@endforelse</div></section>

    <section class="mt-8"><div class="flex items-end justify-between"><div><p class="text-sm font-semibold text-blue-700">Participate</p><h2 class="text-2xl font-black">Upcoming events</h2></div><a class="text-sm font-bold text-blue-700" href="{{ route('knowledge.events.index') }}">View all</a></div><div class="mt-4 grid gap-4 md:grid-cols-3">@forelse($events as $event)<a href="{{ route('knowledge.events.show', $event) }}" class="rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs font-semibold uppercase text-blue-700">{{ str_replace('_', ' ', $event->event_type) }}</p><h3 class="mt-2 font-bold">{{ $event->title }}</h3><p class="mt-3 text-sm text-slate-500">{{ $event->starts_at->format('M j, Y · g:i A') }}</p></a>@empty<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-slate-500 md:col-span-3">No upcoming events are currently available.</div>@endforelse</div></section>

    <section class="mt-8"><div class="flex items-end justify-between"><div><p class="text-sm font-semibold text-blue-700">Discover</p><h2 class="text-2xl font-black">Recent publications</h2></div><a class="text-sm font-bold text-blue-700" href="{{ route('knowledge.index') }}">Open Knowledge Hub</a></div><div class="mt-4 grid gap-4 md:grid-cols-3">@forelse($publications as $publication)<a href="{{ route('knowledge.show', $publication) }}" class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-bold">{{ $publication->title }}</h3><p class="mt-2 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($publication->summary ?? $publication->abstract ?? '', 120) }}</p></a>@empty<div class="rounded-2xl border border-dashed border-slate-300 p-6 text-slate-500 md:col-span-3">Published knowledge will appear here.</div>@endforelse</div></section>
</div>
@endsection
