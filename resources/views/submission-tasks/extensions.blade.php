@extends('layouts.app')

@section('title', 'Manage Extensions')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Manage Extensions</h1>
        <p class="text-gray-600 mt-2">{{ $task->title }}</p>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-green-700 font-semibold">✓ {{ session('success') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-3 gap-8">
        <!-- Left: Students List -->
        <div class="col-span-2">
            <div class="bg-white rounded-lg shadow">
                <div class="p-6 border-b">
                    <h2 class="text-xl font-bold text-gray-900">Students & Extensions</h2>
                </div>

                @if($enrollments && $enrollments->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Student</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Current Deadline</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Extension Granted</th>
                                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($enrollments as $enrollment)
                                @php
                                    $extension = $task->extensions()
                                        ->where('user_id', $enrollment->user_id)
                                        ->first();
                                @endphp
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-semibold text-gray-900">{{ $enrollment->user->name }}</p>
                                            <p class="text-sm text-gray-600">{{ $enrollment->user->email }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($extension)
                                            <div class="flex flex-col">
                                                <span class="line-through text-gray-500">{{ $task->late_deadline->format('M d, H:i') }}</span>
                                                <span class="font-semibold text-green-700">{{ $extension->extended_deadline->format('M d, H:i') }}</span>
                                            </div>
                                        @else
                                            {{ $task->late_deadline->format('M d, H:i') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        @if($extension)
                                            <span class="px-3 py-1 rounded-full bg-green-100 text-green-800 font-semibold text-xs">
                                                ✓ {{ $extension->created_at->format('M d') }}
                                            </span>
                                        @else
                                            <span class="text-gray-500">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <button onclick="openExtensionModal({{ $enrollment->user_id }}, '{{ $enrollment->user->name }}')"
                                                class="text-blue-600 hover:underline text-sm font-semibold">
                                            {{ $extension ? 'Edit' : 'Grant' }}
                                        </button>
                                        @if($extension)
                                            <form action="{{ route('submission-tasks.deleteExtension', [$course, $task, $extension]) }}" 
                                                  method="POST" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline text-sm ml-3"
                                                        onclick="return confirm('Revoke this extension?')">
                                                    Revoke
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center text-gray-500">
                        <p>No enrolled students yet</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Right: Info Sidebar -->
        <div>
            <!-- Assignment Info -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="font-bold text-gray-900 mb-4">📋 Assignment</h3>
                <ul class="space-y-3 text-sm">
                    <li>
                        <p class="text-gray-600">Type</p>
                        <p class="font-semibold text-gray-900">{{ ucfirst($task->type) }}</p>
                    </li>
                    <li>
                        <p class="text-gray-600">Current Deadline</p>
                        <p class="font-semibold text-gray-900">{{ $task->late_deadline->format('M d, Y H:i') }}</p>
                    </li>
                    <li>
                        <p class="text-gray-600">Students</p>
                        <p class="font-semibold text-gray-900">{{ $enrollments->count() ?? 0 }}</p>
                    </li>
                    <li>
                        <p class="text-gray-600">Extensions Granted</p>
                        <p class="font-semibold text-gray-900">{{ $task->extensions()->count() ?? 0 }}</p>
                    </li>
                </ul>
            </div>

            <!-- How It Works -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="font-bold text-gray-900 mb-3">💡 How Extensions Work</h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li>✓ Set a new deadline for the student</li>
                    <li>✓ Extensions appear in student view</li>
                    <li>✓ Late penalty still applies if extended deadline is missed</li>
                    <li>✓ You can edit or revoke at any time</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Extension Modal -->
<div id="extensionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4">
        <div class="px-6 py-4 border-b">
            <h2 class="text-xl font-bold text-gray-900" id="modalTitle">Grant Extension</h2>
        </div>

        <form id="extensionForm" method="POST">
            @csrf
            
            <div class="px-6 py-4 space-y-4">
                <input type="hidden" id="userId" name="user_id">

                <div>
                    <p class="text-sm text-gray-600 mb-2">Student</p>
                    <p id="studentName" class="font-semibold text-gray-900"></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">New Deadline *</label>
                    <input type="datetime-local" name="extended_deadline" 
                           value="{{ now()->addDays(3)->format('Y-m-d\TH:i') }}"
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           min="{{ now()->format('Y-m-d\TH:i') }}" required>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Reason</label>
                    <textarea name="reason" rows="3" 
                              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Reason for granting this extension..."></textarea>
                </div>
            </div>

            <div class="px-6 py-4 border-t flex gap-3">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    Grant Extension
                </button>
                <button type="button" onclick="closeExtensionModal()"
                        class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openExtensionModal(userId, studentName) {
    document.getElementById('userId').value = userId;
    document.getElementById('studentName').textContent = studentName;
    
    // Adjust form action based on whether this is an edit or create
    const extension = document.querySelector(`[data-extension-user-id="${userId}"]`);
    const form = document.getElementById('extensionForm');
    
    if (extension) {
        // Edit mode
        form.action = '{{ route("submission-tasks.updateExtension", [$course, $task, "__ID__"]) }}'.replace('__ID__', userId);
        form.method = 'POST';
        form._method = document.createElement('input');
        form._method.name = '_method';
        form._method.value = 'PUT';
        form._method.type = 'hidden';
        form.appendChild(form._method);
        document.getElementById('modalTitle').textContent = 'Edit Extension';
    } else {
        // Create mode
        form.action = '{{ route("submission-tasks.grantExtension", [$course, $task]) }}';
        form.method = 'POST';
        if (form._method) form._method.remove();
        document.getElementById('modalTitle').textContent = 'Grant Extension';
    }
    
    document.getElementById('extensionModal').classList.remove('hidden');
}

function closeExtensionModal() {
    document.getElementById('extensionModal').classList.add('hidden');
}

// Close modal on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeExtensionModal();
});
</script>
@endsection
