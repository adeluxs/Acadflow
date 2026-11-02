@extends('layouts.app')

@section('title', 'Manage Faculties')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Faculties</h1>
        @can('create', App\Models\Faculty::class)
        <button type="button" onclick="document.getElementById('facultyCreateModal').classList.remove('hidden')" class="rounded-lg bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">Add Faculty</button>
        @endcan
    </div>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3"><label class="text-sm">Faculty name or code<input type="search" name="q" maxlength="100" value="{{ request('q') }}" class="mt-1 block rounded-lg border-slate-300"></label><button class="rounded-lg bg-indigo-600 px-4 py-2 text-white">Search</button><a href="{{ route('admin.faculties') }}" class="px-2 py-2 text-sm text-indigo-600">Clear</a></form>
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departments</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dean</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($faculties as $faculty)
                    <tr>
                        <td class="px-6 py-4">{{ $faculty->name }}<span class="block text-xs text-slate-500">{{ $faculty->university?->name }}</span></td>
                        <td class="px-6 py-4">{{ $faculty->code }}</td>
                        <td class="px-6 py-4">{{ $faculty->departments_count }}</td>
                        <td class="px-6 py-4">{{ $faculty->dean?->full_name ?? 'Not assigned' }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $faculty->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $faculty->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.faculties.edit', $faculty) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No faculties found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $faculties->links() }}</div>
</div>

@can('create', App\Models\Faculty::class)
<div id="facultyCreateModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Add Faculty</h2><button type="button" onclick="document.getElementById('facultyCreateModal').classList.add('hidden')" aria-label="Close">×</button></div>
        <form method="POST" action="{{ route('admin.faculties.create') }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
            @if(auth()->user()->isSuperAdmin())<x-admin-lookup class="md:col-span-2" name="university_id" kind="universities" label="University" :selected="$selectedUniversity" required />@endif
            <label>Faculty name<input name="name" required class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label>Short name<input name="short_name" required maxlength="80" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label>Code<input name="code" required maxlength="30" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <x-admin-lookup name="dean_id" kind="deans" label="Dean" placeholder="Not assigned" :selected="$selectedDean" :depends="auth()->user()->isSuperAdmin() ? 'university_id' : null" />
            <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <div class="flex justify-end gap-3 md:col-span-2"><button type="button" onclick="document.getElementById('facultyCreateModal').classList.add('hidden')" class="rounded-xl border px-4 py-2">Cancel</button><button class="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">Create faculty</button></div>
        </form>
    </div>
</div>
@endcan
@endsection
