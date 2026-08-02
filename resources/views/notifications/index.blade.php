@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Notifications</h1>
        <div class="flex gap-2">
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Mark All Read
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('notifications.clear') }}" onsubmit="return confirm('Clear all notifications?')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    Clear All
                </button>
            </form>
        </div>
    </div>

    <!-- Sidebar filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="md:col-span-1">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-bold mb-3">Filter By Type</h3>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('notifications.index') }}" 
                           class="flex justify-between {{ !request()->has('filter') && !request()->has('type') ? 'font-bold text-indigo-600' : 'text-gray-600' }}">
                            All
                             <span class="text-gray-400">{{ $typeCounts->count() ?? $notifications->total() }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('notifications.index', ['filter' => 'unread']) }}" 
                           class="flex justify-between {{ request('filter') === 'unread' ? 'font-bold text-indigo-600' : 'text-gray-600' }}">
                            Unread
                            <span class="text-gray-400">{{ $unreadCount }}</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('notifications.index', ['filter' => 'read']) }}" 
                           class="flex justify-between {{ request('filter') === 'read' ? 'font-bold text-indigo-600' : 'text-gray-600' }}">
                            Read
                            <span class="text-gray-400">{{ ($typeCounts->sum(fn($t) => $t->count) ?? $notifications->total()) - $unreadCount }}</span>
                        </a>
                    </li>
                </ul>

                @if($typeCounts->count() > 0)
                    <hr class="my-4">
                    <h3 class="font-bold mb-3">By Category</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach($typeCounts as $type => $count)
                            <li>
                                <a href="{{ route('notifications.index', ['type' => $type]) }}" 
                                   class="flex justify-between text-gray-600 hover:text-indigo-600">
                                    {{ ucwords(str_replace('_', ' ', $type)) }}
                                    <span>{{ $count->count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <!-- Main list -->
        <div class="md:col-span-3">
            @if($notifications->count() > 0)
                <div class="space-y-3">
                    @foreach($notifications as $notification)
                        <div class="bg-white rounded-lg shadow p-4 {{ is_null($notification->read_at) ? 'border-l-4 border-blue-500' : '' }}">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="font-semibold {{ is_null($notification->read_at) ? 'text-gray-900' : 'text-gray-700' }}">
                                        {{ $notification->title }}
                                    </h4>
                                    <p class="text-gray-600 text-sm mt-1">{{ $notification->message }}</p>
                                    <div class="flex items-center gap-2 mt-2 text-xs text-gray-500">
                                        <span>{{ $notification->created_at?->format('M d, Y H:i') ?? '-' }}</span>
                                        @if($notification->user)
                                            <span>• {{ $notification->user->full_name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    @if(is_null($notification->read_at))
                                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                            @csrf
                                            <button type="submit" class="text-xs text-blue-600 hover:underline">Mark Read</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('notifications.destroy', $notification) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline" 
                                                onclick="return confirm('Delete this notification?')">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6">
                    {{ $notifications->links() }}
                </div>
            @else
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <p class="text-gray-500">No notifications found.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
