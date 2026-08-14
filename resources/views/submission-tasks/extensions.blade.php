@extends('layouts.app')

@section('title', 'Manage Extensions')

@section('content')
@php($baseDeadline = $task->late_deadline ?? $task->close_at ?? $task->due_date)
<div class="container mx-auto max-w-6xl px-4 py-8">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Manage Extensions</h1>
            <p class="mt-2 text-gray-600">{{ $task->title }}</p>
        </div>
        <a href="{{ route('submission-tasks.lecturer.show', [$course, $task]) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Back to assignment</a>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b p-6">
                    <h2 class="text-xl font-bold text-gray-900">Enrolled students</h2>
                    <p class="mt-1 text-sm text-gray-600">Submitting this form creates or updates the existing extension record; no duplicate record is created.</p>
                </div>

                @forelse($enrollments as $enrollment)
                    @php($extension = $extensions->get($enrollment->user_id))
                    <div class="border-b p-6 last:border-b-0">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $enrollment->user->name }}</p>
                                <p class="text-sm text-gray-600">{{ $enrollment->user->email }}</p>
                            </div>
                            @if($extension)
                                <form action="{{ route('submission-tasks.extension.revoke', [$course, $task, $extension]) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-800" onclick="return confirm('Revoke this extension?')">Revoke</button>
                                </form>
                            @endif
                        </div>

                        <form action="{{ route('submission-tasks.extension.grant', [$course, $task]) }}" method="POST" class="grid gap-4 md:grid-cols-3">
                            @csrf
                            <input type="hidden" name="student_id" value="{{ $enrollment->user_id }}">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Original deadline</label>
                                <div class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $baseDeadline?->format('M d, Y H:i') ?? 'Not set' }}</div>
                            </div>
                            <div>
                                <label for="extended_deadline_{{ $enrollment->user_id }}" class="block text-sm font-medium text-gray-700">Extended deadline</label>
                                <input id="extended_deadline_{{ $enrollment->user_id }}" type="datetime-local" name="extended_deadline"
                                       value="{{ old('student_id') == $enrollment->user_id ? old('extended_deadline') : $extension?->extended_deadline?->format('Y-m-d\TH:i') }}"
                                       min="{{ now()->format('Y-m-d\TH:i') }}" required
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="reason_{{ $enrollment->user_id }}" class="block text-sm font-medium text-gray-700">Reason</label>
                                <input id="reason_{{ $enrollment->user_id }}" name="reason" maxlength="1000"
                                       value="{{ old('student_id') == $enrollment->user_id ? old('reason') : $extension?->reason }}"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional reason">
                            </div>
                            <div class="md:col-span-3 flex items-center justify-between gap-4">
                                <p class="text-xs text-gray-500">
                                    @if($extension)
                                        Current extension: {{ $extension->extended_deadline?->format('M d, Y H:i') }}
                                    @else
                                        No extension granted.
                                    @endif
                                </p>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    {{ $extension ? 'Update extension' : 'Grant extension' }}
                                </button>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="p-10 text-center text-sm text-gray-500">No enrolled students were found for this course and semester.</div>
                @endforelse
            </div>
        </div>

        <aside class="space-y-6">
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="font-bold text-gray-900">Assignment</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-gray-500">Type</dt><dd class="font-semibold text-gray-900">{{ ucfirst($task->type) }}</dd></div>
                    <div><dt class="text-gray-500">Base deadline</dt><dd class="font-semibold text-gray-900">{{ $baseDeadline?->format('M d, Y H:i') ?? 'Not set' }}</dd></div>
                    <div><dt class="text-gray-500">Enrolled students</dt><dd class="font-semibold text-gray-900">{{ $enrollments->count() }}</dd></div>
                    <div><dt class="text-gray-500">Extensions granted</dt><dd class="font-semibold text-gray-900">{{ $extensions->count() }}</dd></div>
                </dl>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 text-sm text-blue-900">
                Extensions are scoped to this assignment and enrolled student. Updating an extension preserves the original deadline and replaces only the approved extended deadline and reason.
            </div>
        </aside>
    </div>
</div>
@endsection
