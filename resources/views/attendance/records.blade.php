@extends('layouts.app')

@section('title', 'My Attendance Records')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">My Attendance Records</h1>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-in Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($records as $record)
                    <tr>
                        <td class="px-6 py-4">{{ $record->check_in_at?->format('Y-m-d') }}</td>
                        <td class="px-6 py-4">{{ $record->session->course->code ?? 'N/A' }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                {{ $record->status === 'present' ? 'bg-green-100 text-green-800' : 
                                   ($record->status === 'late' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ ucfirst($record->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">{{ $record->check_in_at?->format('H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No attendance records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $records->links() }}
    </div>
</div>
@endsection
