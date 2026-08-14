@extends('layouts.app')
@section('title', 'Manage Knowledge Hub')
@section('page-title', 'Knowledge Hub publishing')
@section('page-subtitle', 'Drafts, moderation and published resources')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET"><select name="status" class="rounded-xl border-slate-300 text-sm" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(['draft','pending_review','changes_requested','published','rejected','unpublished'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></form>
        <div class="flex gap-3"><a href="{{ route('knowledge.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Browse Hub</a><a href="{{ route('knowledge.manage.create') }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">New publication</a></div>
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left">Publication</th><th class="px-5 py-3 text-left">Status</th><th class="px-5 py-3 text-left">Access</th><th class="px-5 py-3 text-left">Metrics</th><th class="px-5 py-3 text-right">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($publications as $publication)<tr><td class="px-5 py-4"><p class="font-semibold">{{ $publication->title }}</p><p class="mt-1 text-xs text-slate-500">{{ ucwords(str_replace('_',' ',$publication->content_type)) }} @if($publication->sourceResearchProject) · Linked research @endif</p></td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium">{{ ucwords(str_replace('_',' ',$publication->status)) }}</span></td><td class="px-5 py-4">{{ ucfirst($publication->access_type) }} · {{ ucfirst($publication->visibility) }}</td><td class="px-5 py-4 text-slate-500">{{ number_format($publication->view_count) }} reads / {{ number_format($publication->bookmark_count) }} saves</td><td class="px-5 py-4 text-right"><a href="{{ route('knowledge.manage.edit',$publication) }}" class="font-semibold text-blue-700">Open</a></td></tr>
            @empty<tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No publications found.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    {{ $publications->links() }}
</div>
@endsection
