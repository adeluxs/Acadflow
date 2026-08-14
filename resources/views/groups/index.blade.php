@extends('layouts.app')
@section('title','Collaboration Groups')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-semibold text-blue-600">Focused collaboration</p><h1 class="text-3xl font-bold text-slate-900">Groups</h1><p class="mt-2 max-w-2xl text-slate-600">Create smaller study, research, project, departmental, SIWES, seminar, or professional teams. Communities remain broad public spaces; groups are working spaces with members, tasks, files, events, and discussions.</p></div>
        @can('create', App\Models\Group::class)<a href="{{ route('groups.create') }}" class="rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">Create group</a>@endcan
    </div>
    <form method="GET" class="grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-4">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search groups" class="rounded-xl border-slate-300 md:col-span-2">
        <select name="type" class="rounded-xl border-slate-300"><option value="">All types</option>@foreach(['study','research','project','departmental','professional','course','siwes','seminar'] as $type)<option value="{{ $type }}" @selected(($filters['type']??'')===$type)>{{ str($type)->headline() }}</option>@endforeach</select>
        <button class="rounded-xl border border-blue-600 px-4 py-2 font-semibold text-blue-700">Filter</button>
    </form>
    @if($groups->isEmpty())
        <div class="rounded-2xl border border-dashed bg-white p-12 text-center"><h2 class="text-xl font-semibold">No matching groups yet</h2><p class="mt-2 text-slate-600">Start a focused collaboration space or broaden your filters.</p></div>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($groups as $group)
                <article class="rounded-2xl border bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium">{{ str($group->group_type)->headline() }}</span><h2 class="mt-3 text-xl font-bold"><a href="{{ route('groups.show',$group) }}" class="hover:text-blue-700">{{ $group->name }}</a></h2></div><span class="text-xs text-slate-500">{{ ucfirst($group->visibility) }}</span></div>
                    <p class="mt-3 line-clamp-3 text-sm text-slate-600">{{ $group->description ?: 'A focused collaboration group.' }}</p>
                    <dl class="mt-5 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">Members</dt><dd class="font-semibold">{{ $group->active_members_count }}/{{ $group->max_members }}</dd></div><div><dt class="text-slate-500">Leader</dt><dd class="truncate font-semibold">{{ $group->leader->full_name }}</dd></div></dl>
                    @if($group->course)<p class="mt-3 text-xs text-slate-500">Course: {{ $group->course->code }} — {{ $group->course->name }}</p>@endif
                </article>
            @endforeach
        </div>
        <div>{{ $groups->links() }}</div>
    @endif
</div>
@endsection
