@extends('layouts.app')

@section('title', $course->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">{{ $course->code }} - {{ $course->name }}</h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <div class="text-gray-500 text-sm">Credits</div>
                <div>{{ $course->credits }}</div>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Department</div>
                <div>{{ $course->department->name }}</div>
            </div>
        </div>
    </div>

    <h2 class="text-xl font-bold mb-4">Enrolled Students</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($enrollments as $enrollment)
                    <tr>
                        <td class="px-6 py-4">{{ $enrollment->user->email }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                {{ $enrollment->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($enrollment->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-4 text-center text-gray-500">No enrollments.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection