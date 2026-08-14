@extends('layouts.app')
@section('title', 'Manage Institutions')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[.18em] text-indigo-600">Academic directory</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950">Universities & Polytechnics</h1>
            <p class="mt-1 text-sm text-slate-500">AcadFlow keeps the existing institution model while supporting NUC and NBTE catalogues in one tenant-safe directory.</p>
        </div>
        @can('create', App\Models\University::class)
            <button type="button" onclick="document.getElementById('createModal').classList.remove('hidden')" class="acad-primary-button">Add institution</button>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="acad-card p-5"><p class="text-xs text-slate-500">Visible on this page</p><p class="mt-2 text-2xl font-bold">{{ $universities->count() }}</p></div>
        <div class="acad-card p-5"><p class="text-xs text-slate-500">Universities</p><p class="mt-2 text-2xl font-bold">{{ $universities->where('institution_type','university')->count() }}</p></div>
        <div class="acad-card p-5"><p class="text-xs text-slate-500">Polytechnics</p><p class="mt-2 text-2xl font-bold">{{ $universities->where('institution_type','polytechnic')->count() }}</p></div>
    </div>

    <div class="acad-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50/80 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-5 py-3">Institution</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Ownership</th><th class="px-5 py-3">Structure</th><th class="px-5 py-3">Registry</th><th class="px-5 py-3">Status</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($universities as $university)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4"><p class="font-semibold text-slate-900">{{ $university->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $university->code }}{{ $university->state ? ' · '.$university->state : '' }}</p></td>
                            <td class="px-5 py-4 capitalize">{{ $university->institution_type ?: 'university' }}</td>
                            <td class="px-5 py-4">{{ $university->ownership ?: '—' }}</td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ $university->faculties_count }} faculties/schools<br>{{ $university->users_count }} users</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium">{{ $university->regulator ?: 'Manual' }}</span></td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $university->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $university->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="px-5 py-4 text-right"><a href="{{ route('admin.universities.edit', $university) }}" class="acad-link text-sm font-semibold">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">No institutions have been added yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-4">{{ $universities->links() }}</div>
    </div>

    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5 text-sm text-indigo-950">
        <p class="font-semibold">Catalogue sync</p>
        <p class="mt-1 text-indigo-800">Run <code class="rounded bg-white/80 px-1.5 py-0.5">php artisan acadflow:sync-nigeria-catalog</code> to refresh Nigerian institution registries and starter academic structures. Exact institution curriculum CSVs can be imported with <code class="rounded bg-white/80 px-1.5 py-0.5">--csv=/path/catalog.csv</code>.</p>
    </div>
</div>

<div id="createModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/45 p-4">
    <div class="mx-auto mt-10 max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
        <div class="flex items-center justify-between"><div><h2 class="text-xl font-bold">Add institution</h2><p class="text-sm text-slate-500">Use this for a manual institution not yet present in the regulator sync.</p></div><button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="rounded-xl border px-3 py-2">Close</button></div>
        <form method="POST" action="{{ route('admin.universities.create') }}" class="mt-6 grid gap-4 sm:grid-cols-2">@csrf
            <label class="sm:col-span-2 text-sm font-medium">Institution name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl"></label>
            <label class="text-sm font-medium">Short name<input name="short_name" value="{{ old('short_name') }}" required maxlength="50" class="mt-1 w-full rounded-xl"></label>
            <label class="text-sm font-medium">Code<input name="code" value="{{ old('code') }}" required maxlength="10" class="mt-1 w-full rounded-xl uppercase"></label>
            <label class="text-sm font-medium">Institution type<select name="institution_type" required class="mt-1 w-full rounded-xl"><option value="university">University</option><option value="polytechnic">Polytechnic</option></select></label>
            <label class="text-sm font-medium">Ownership<select name="ownership" class="mt-1 w-full rounded-xl"><option value="">Select</option><option>Federal</option><option>State</option><option>Private</option></select></label>
            <label class="text-sm font-medium">State<input name="state" value="{{ old('state') }}" class="mt-1 w-full rounded-xl"></label>
            <label class="text-sm font-medium">Regulator<input name="regulator" value="{{ old('regulator') }}" placeholder="NUC or NBTE" class="mt-1 w-full rounded-xl"></label>
            <label class="text-sm font-medium">Email<input name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-xl"></label>
            <label class="text-sm font-medium">Phone<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-xl"></label>
            <label class="sm:col-span-2 text-sm font-medium">Website<input name="website" type="url" value="{{ old('website') }}" class="mt-1 w-full rounded-xl"></label>
            <label class="sm:col-span-2 text-sm font-medium">Address<textarea name="address" rows="2" class="mt-1 w-full rounded-xl">{{ old('address') }}</textarea></label>
            <div class="sm:col-span-2 flex justify-end gap-3 pt-2"><button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="rounded-xl border px-5 py-2.5">Cancel</button><button class="acad-primary-button">Create institution</button></div>
        </form>
    </div>
</div>
@endsection
