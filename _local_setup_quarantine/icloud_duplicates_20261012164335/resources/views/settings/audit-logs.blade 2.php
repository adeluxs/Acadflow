@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Audit Logs</h1>
        <a href="{{ route('admin.settings') }}" class="text-blue-600 hover:underline text-sm">Back to Settings</a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6 p-4">
        <form method="GET" action="{{ route('admin.settings.audit-logs') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
                <select name="action" class="w-full px-3 py-2 border rounded">
                    <option value="">All Actions</option>
                    @foreach($actions as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $action)) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                <select name="user_id" class="w-full px-3 py-2 border rounded">
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name ?? $user->email }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-3 py-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-3 py-2 border rounded">
            </div>
            <div class="md:col-span-4 flex gap-2">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                    Filter
                </button>
                <a href="{{ route('admin.settings.audit-logs') }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 text-sm">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                {{ $log->user ? ($log->user->name ?? $log->user->email) : 'System' }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    @switch($log->action)
                                        @case('setting_updated')
                                            bg-blue-100 text-blue-800
                                            @break
                                        @case('login')
                                            bg-green-100 text-green-800
                                            @break
                                        @case('delete')
                                            bg-red-100 text-red-800
                                            @break
                                        @default
                                            bg-gray-100 text-gray-800
                                    @endswitch
                                ">
                                    {{ ucfirst(str_replace('_', ' ', $log->action)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                @if($log->field_name)
                                    <strong>{{ $log->field_name }}</strong><br>
                                    @if($log->old_value)
                                        <span class="text-red-600">Old: {{ Str::limit($log->old_value, 50) }}</span><br>
                                    @endif
                                    @if($log->new_value)
                                        <span class="text-green-600">New: {{ Str::limit($log->new_value, 50) }}</span>
                                    @endif
                                @elseif($log->model_type)
                                    {{ class_basename($log->model_type) }} #{{ $log->model_id }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ $log->ip_address }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                No audit logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
