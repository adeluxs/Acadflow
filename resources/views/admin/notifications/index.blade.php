@extends('layouts.app')

@section('title', 'Notification Management')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Notification Management</h1>
            <p class="text-gray-600 mt-2">Control notification channels and monitor delivery</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.notifications.announce') }}" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                Send Announcement
            </a>
            <form method="POST" action="{{ route('admin.notifications.retry-failed') }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                    Retry Failed
                </button>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Total (30d)</p>
            <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Successful</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['success'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Failed</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['failed'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase">Success Rate</p>
            <p class="text-2xl font-bold text-gray-900">
                {{ $stats['total'] > 0 ? round(($stats['success'] / $stats['total']) * 100, 1) : 0 }}%
            </p>
        </div>
    </div>

    <!-- Channel Settings -->
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Global Channel Settings</h2>
        <p class="text-sm text-gray-600 mb-6">Enable or disable notification channels system-wide. Individual users can still adjust their own preferences.</p>

        <form action="{{ route('admin.notifications.update-channels') }}" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">In-App Notifications</span>
                        <p class="text-sm text-gray-600">Database/website notifications</p>
                    </div>
                    <input type="checkbox" name="notifications_in_app_enabled" value="1"
                           {{ $channels['notifications_in_app_enabled'] == '1' ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Email Notifications</span>
                        <p class="text-sm text-gray-600">Email delivery for alerts</p>
                    </div>
                    <input type="checkbox" name="notifications_email_enabled" value="1"
                           {{ $channels['notifications_email_enabled'] == '1' ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
                <label class="flex items-center justify-between">
                    <div>
                        <span class="font-medium text-gray-900">Push Notifications</span>
                        <p class="text-sm text-gray-600">Browser push notifications</p>
                    </div>
                    <input type="checkbox" name="notifications_push_enabled" value="1"
                           {{ $channels['notifications_push_enabled'] == '1' ? 'checked' : '' }}
                           class="h-5 w-5 text-blue-600 rounded">
                </label>
            </div>
            <div class="pt-6 border-t mt-6">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-semibold">
                    Save Channel Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Channel Breakdown -->
    @if(count($stats['by_channel']) > 0)
    <div class="bg-white rounded-lg shadow p-8 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Delivery by Channel (30d)</h2>
        <div class="grid grid-cols-3 gap-4">
            @foreach($stats['by_channel'] as $channel => $count)
                <div class="bg-gray-50 rounded p-4">
                    <p class="text-sm text-gray-600 uppercase">{{ ucfirst($channel) }}</p>
                    <p class="text-xl font-bold text-gray-900">{{ $count }}</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Recent Failures -->
    @if($recentFailures->count() > 0)
    <div class="bg-white rounded-lg shadow p-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Failures</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-2 text-left">User</th>
                        <th class="px-4 py-2 text-left">Channel</th>
                        <th class="px-4 py-2 text-left">Title</th>
                        <th class="px-4 py-2 text-left">Attempts</th>
                        <th class="px-4 py-2 text-left">Last Attempt</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentFailures as $log)
                        <tr class="border-b">
                            <td class="px-4 py-2">{{ $log->user?->email ?? 'Unknown' }}</td>
                            <td class="px-4 py-2">{{ ucfirst($log->channel) }}</td>
                            <td class="px-4 py-2">{{ $log->notification?->title ?? 'N/A' }}</td>
                            <td class="px-4 py-2">{{ $log->attempt_count }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $log->attempted_at?->diffForHumans() ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
