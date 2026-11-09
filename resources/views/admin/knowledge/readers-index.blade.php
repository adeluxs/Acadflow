@extends('layouts.app')
@section('title', 'Knowledge Hub readers')
@section('page-title', 'Knowledge Hub readers')
@section('page-subtitle', 'See who read each guide and how they did on its quiz')
@section('content')
<div class="space-y-5">
    <a href="{{ route('admin.knowledge.index') }}" class="text-sm font-bold text-indigo-700">← Knowledge Admin</a>
    <form method="GET" class="flex gap-2"><label class="sr-only" for="reader-search">Search guides</label><input id="reader-search" name="q" value="{{ $search }}" placeholder="Search guide titles" class="w-full max-w-md rounded-xl border-slate-300"><button class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-bold text-white">Search</button></form>
    <div class="overflow-x-auto rounded-3xl border bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Guide</th><th class="px-4 py-3">Creator</th><th class="px-4 py-3">Access</th><th class="px-4 py-3 text-right">Reads</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y">
                @forelse($publications as $publication)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $publication->title }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $publication->creator?->full_name ?? '–' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ ucfirst($publication->access_type) }}</td>
                        <td class="px-4 py-3 text-right font-bold">{{ number_format($publication->view_count) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.knowledge.readers', $publication) }}" class="font-bold text-indigo-700">Readers →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No published guides found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $publications->links() }}
</div>
@endsection
