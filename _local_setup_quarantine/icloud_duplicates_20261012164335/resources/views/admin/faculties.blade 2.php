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

    <div class="bg-white rounded-lg shadow overflow-hidden">
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
                        <td class="px-6 py-4">{{ $faculty->name }}</td>
                        <td class="px-6 py-4">{{ $faculty->code }}</td>
                        <td class="px-6 py-4">{{ $faculty->departments->count() }}</td>
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
</div>

@can('create', App\Models\Faculty::class)
<div id="facultyCreateModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
    <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
        <div class="flex items-center justify-between"><h2 class="text-xl font-bold">Add Faculty</h2><button type="button" onclick="document.getElementById('facultyCreateModal').classList.add('hidden')" aria-label="Close">×</button></div>
        <form method="POST" action="{{ route('admin.faculties.create') }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
            @if(auth()->user()->isSuperAdmin())<label class="md:col-span-2">University<select name="university_id" required class="mt-1 w-full rounded-xl border-slate-300"><option value="">Select university</option>@foreach($universities as $university)<option value="{{ $university->id }}">{{ $university->name }}</option>@endforeach</select></label>@endif
            <label>Faculty name<input name="name" required class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label>Short name<input name="short_name" required maxlength="80" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label>Code<input name="code" required maxlength="30" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label>Dean<select name="dean_id" class="mt-1 w-full rounded-xl border-slate-300"><option value="">Not assigned</option>@foreach($deans as $dean)<option value="{{ $dean->id }}">{{ $dean->full_name }}</option>@endforeach</select></label>
            <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <div class="flex justify-end gap-3 md:col-span-2"><button type="button" onclick="document.getElementById('facultyCreateModal').classList.add('hidden')" class="rounded-xl border px-4 py-2">Cancel</button><button class="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">Create faculty</button></div>
        </form>
    </div>
</div>
@endcan
@endsection