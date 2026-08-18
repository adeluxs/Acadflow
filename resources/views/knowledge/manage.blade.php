@extends('layouts.app')
@section('title', 'Manage Knowledge Hub')
@section('page-title', 'Knowledge Hub publishing')
@section('page-subtitle', 'Create, review, submit and track every publication through moderation')
@section('content')
@php
    $statusStyles = [
        'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
        'pending_review' => 'bg-amber-50 text-amber-700 border-amber-200',
        'changes_requested' => 'bg-orange-50 text-orange-700 border-orange-200',
        'published' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
        'unpublished' => 'bg-violet-50 text-violet-700 border-violet-200',
        'scheduled' => 'bg-blue-50 text-blue-700 border-blue-200',
    ];
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-700 px-6 py-7 text-white md:px-8">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div><p class="text-xs font-black uppercase tracking-[.22em] text-indigo-200">Creator Workspace</p><h1 class="mt-2 text-3xl font-black">Publication studio</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">Open every publication regardless of workflow state, edit when permitted, and follow moderation without losing access to your own work.</p></div>
                <div class="flex flex-wrap gap-2"><a href="{{ route('knowledge.index') }}" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-bold hover:bg-white/20">Browse Hub</a><a href="{{ route('knowledge.manage.create') }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-800">New publication</a></div>
            </div>
        </div>
    </section>

    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">{{ session('success') }}</div>@endif
    @if(session('info'))<div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-900">{{ session('info') }}</div>@endif

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="font-black text-slate-900">Your publishing pipeline</h2><p class="mt-1 text-sm text-slate-500">Filter by workflow status without changing ownership or access.</p></div>
            <form method="GET"><select name="status" class="rounded-xl border-slate-300 text-sm" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(['draft','pending_review','changes_requested','scheduled','published','rejected','unpublished'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></form>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        @forelse($publications as $publication)
            <article class="group rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0"><p class="text-xs font-black uppercase tracking-wide text-indigo-600">{{ ucwords(str_replace('_',' ',$publication->content_type)) }}</p><h3 class="mt-1 line-clamp-2 text-lg font-black text-slate-900">{{ $publication->title }}</h3><p class="mt-2 text-xs text-slate-500">Updated {{ optional($publication->updated_at)->diffForHumans() }} @if($publication->sourceResearchProject) · Linked research @endif</p></div>
                    <span class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-black {{ $statusStyles[$publication->status] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ ucwords(str_replace('_',' ',$publication->status)) }}</span>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-slate-50 p-3"><p class="text-lg font-black text-slate-900">{{ number_format($publication->view_count) }}</p><p class="text-[11px] text-slate-500">Reads</p></div>
                    <div class="rounded-2xl bg-slate-50 p-3"><p class="text-lg font-black text-slate-900">{{ number_format($publication->bookmark_count) }}</p><p class="text-[11px] text-slate-500">Saves</p></div>
                    <div class="rounded-2xl bg-slate-50 p-3"><p class="text-sm font-black text-slate-900">{{ ucfirst($publication->access_type) }}</p><p class="text-[11px] text-slate-500">Access</p></div>
                </div>
                @if($publication->moderation_note)<div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"><span class="font-black">Moderation note:</span> {{ $publication->moderation_note }}</div>@endif
                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4">
                    <span class="text-xs text-slate-500">{{ ucfirst($publication->visibility) }} visibility</span>
                    <div class="flex gap-2">
                        @can('update',$publication)<a href="{{ route('knowledge.manage.edit',$publication) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">Edit</a>@endcan
                        <a href="{{ route('knowledge.manage.show',$publication) }}" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black text-white hover:bg-indigo-700">Open workspace</a>
                    </div>
                </div>
            </article>
        @empty
            <div class="lg:col-span-2 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-2xl">✦</div><h3 class="mt-4 font-black text-slate-900">No publications found</h3><p class="mt-1 text-sm text-slate-500">Create a draft or choose a different status filter.</p><a href="{{ route('knowledge.manage.create') }}" class="mt-5 inline-flex rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Create publication</a></div>
        @endforelse
    </div>
    {{ $publications->links() }}
</div>
@endsection
