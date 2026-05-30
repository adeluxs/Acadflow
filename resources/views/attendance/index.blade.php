@extends('layouts.app')

@section('title', 'Attendance')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Attendance</h1>
        @role('lecturer')
            <a href="{{ route('attendance.start') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                Start Session
            </a>
        @endrole
    </div>

    <div class="grid gap-6 md:grid-cols-2 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">My Attendance</h2>
            <div class="text-4xl font-bold text-blue-600">{{ $attendanceRate ?? 0 }}%</div>
            <p class="text-gray-600 text-sm">Overall attendance rate</p>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Recordings</h2>
            <div class="space-y-2">
                <p>Present: <span class="font-semibold text-green-600">{{ $present ?? 0 }}</span></p>
                <p>Late: <span class="font-semibold text-yellow-600">{{ $late ?? 0 }}</span></p>
                <p>Absent: <span class="font-semibold text-red-600">{{ $absent ?? 0 }}</span></p>
            </div>
        </div>
    </div>

    <h2 class="text-xl font-semibold mb-4">Recent Sessions</h2>
    
    @if($sessions->isEmpty())
        <div class="bg-gray-100 rounded-lg p-8 text-center">
            <p class="text-gray-600">No attendance sessions yet.</p>
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($sessions as $session)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $session->course->code }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $session->created_at->format('M d, Y') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full 
                                    {{ $session->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $session->is_active ? 'Active' : 'Closed' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ $session->records->whereIn('status', ['present', 'late'])->count() }} / {{ $session->records->count() }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection