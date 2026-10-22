@extends('layouts.app')

@section('title', 'Manage Users')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Users</h1>
        @can('create', App\Models\User::class)
        <button onclick="document.getElementById('inviteModal').classList.remove('hidden')" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
            Invite User
        </button>
        @endcan
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid items-end gap-4 md:grid-cols-4">
            <label class="text-sm">Name or email<input type="search" name="q" maxlength="100" value="{{ request('q') }}" class="mt-1 w-full border rounded px-3 py-2" placeholder="Search users"></label>
            <label class="text-sm">Role<select name="role" class="mt-1 w-full border rounded px-3 py-2">
                <option value="">All Roles</option>
                @foreach(['member'=>'Member','student'=>'Student','lecturer'=>'Lecturer','department_admin'=>'Department Admin','university_admin'=>'University Admin'] as $value=>$label)
                    <option value="{{ $value }}" @selected(request('role')===$value)>{{ $label }}</option>
                @endforeach
            </select></label>
            <x-admin-lookup name="department_id" kind="departments" label="Department" placeholder="All departments" :selected="$selectedDepartment" />
            <div class="space-y-2"><label class="block text-sm">Status<select name="status" class="mt-1 w-full border rounded px-3 py-2"><option value="">All statuses</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></label><button type="submit" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300">Filter</button> <a href="{{ route('admin.users') }}" class="text-sm text-indigo-600">Clear</a></div>
        </div>
    </form>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($users as $user)
                    <tr>
                        <td class="px-6 py-4">{{ $user->first_name }} {{ $user->last_name }}</td>
                        <td class="px-6 py-4">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">{{ $user->department?->name }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>

@can('create', App\Models\User::class)
<!-- Invite Modal -->
<div id="inviteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h2 class="text-xl font-bold mb-4">Invite User</h2>
        <form method="POST" action="{{ route('admin.users.invite') }}">
            @csrf
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input name="email" type="email" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Role</label>
                <select name="role" class="w-full px-3 py-2 border rounded" required>
                    <option value="student">Student</option>
                    <option value="lecturer">Lecturer</option>
                    <option value="department_admin">Department Admin</option>
                </select>
            </div>
            <x-admin-lookup class="mb-4" name="department_id" kind="departments" label="Department" placeholder="Select department" :selected="$inviteDepartment" required />
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('inviteModal').classList.add('hidden')" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">Send Invite</button>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection
