@extends('layouts.app')

@section('title', 'Send Announcement')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Send System Announcement</h1>
        <p class="text-gray-600 mt-2">Broadcast a message to users across all channels</p>
    </div>

    <form action="{{ route('admin.notifications.send-announcement') }}" method="POST" class="bg-white rounded-lg shadow p-8">
        @csrf

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Title *</label>
            <input type="text" name="title" value="{{ old('title') }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                   required>
            @error('title')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Message *</label>
            <textarea name="message" rows="6"
                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                      required>{{ old('message') }}</textarea>
            @error('message')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Target Audience *</label>
            <select name="target" id="target" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500" required onchange="toggleDept()">
                <option value="">Select target</option>
                <option value="all">All Users</option>
                <option value="students">All Students</option>
                <option value="lecturers">All Lecturers</option>
                <option value="department">Specific Department</option>
            </select>
            @error('target')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="mb-6" id="department-select" style="display:none;">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Department</label>
            <select name="department_id" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">Select department</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </select>
            @error('department_id')<span class="text-red-600 text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="flex gap-3 pt-6 border-t">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                Send Announcement
            </button>
            <a href="{{ route('admin.notifications.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
function toggleDept() {
    const target = document.getElementById('target').value;
    const deptSelect = document.getElementById('department-select');
    deptSelect.style.display = target === 'department' ? 'block' : 'none';
}
</script>
@endsection
