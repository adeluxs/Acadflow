@extends('layouts.app')

@section('title', 'Attendance Sessions')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:justify-between lg:items-start mb-6">
        <div>
            <h1 class="text-2xl font-bold">Attendance Sessions</h1>
            <p class="text-sm text-gray-600 mt-1">Start and manage attendance for courses assigned to you.</p>
        </div>
        <form method="POST" action="{{ route('attendance.start') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4" id="start-attendance-form">
            @csrf
            <select name="course_id" required class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 sm:col-span-2">
                <option value="">Select Course</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->code }} - {{ $course->name }}</option>
                @endforeach
            </select>
            <input type="number" name="check_in_window" min="1" max="180" value="{{ old('check_in_window', 30) }}" class="px-3 py-2 border border-gray-300 rounded-md" aria-label="Check-in window in minutes" title="Check-in window in minutes">
            <input type="number" name="late_threshold" min="0" max="180" value="{{ old('late_threshold', 15) }}" class="px-3 py-2 border border-gray-300 rounded-md" aria-label="Late threshold in minutes" title="Late threshold in minutes">
            <input type="hidden" name="latitude" id="session-latitude">
            <input type="hidden" name="longitude" id="session-longitude">
            <input type="hidden" name="geofence_radius" value="100">
            <button type="button" id="capture-location" class="border border-blue-600 text-blue-700 px-4 py-2 rounded hover:bg-blue-50 sm:col-span-2">
                Use Current Location (Optional)
            </button>
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 sm:col-span-2">
                Start Session
            </button>
            <p id="location-message" class="hidden text-xs text-gray-600 sm:col-span-4"></p>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Started</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($sessions as $session)
                        <tr>
                            <td class="px-6 py-4">{{ $session->course->code }} - {{ $session->course->name }}</td>
                            <td class="px-6 py-4">{{ $session->started_at->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-4">{{ $session->records->count() }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $session->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($session->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <a href="{{ route('attendance.session', $session) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">No sessions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
</div>

<script>
const locationButton = document.getElementById('capture-location');
const locationMessage = document.getElementById('location-message');

locationButton.addEventListener('click', () => {
    locationMessage.classList.remove('hidden');
    locationMessage.textContent = 'Capturing location…';

    if (!('geolocation' in navigator)) {
        locationMessage.textContent = 'Location is not supported by this browser. The session will start without geofencing.';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        position => {
            document.getElementById('session-latitude').value = position.coords.latitude;
            document.getElementById('session-longitude').value = position.coords.longitude;
            locationMessage.textContent = 'Location captured. A 100-metre geofence will be applied.';
        },
        () => {
            locationMessage.textContent = 'Location could not be captured. The session can still start without geofencing.';
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
    );
});
</script>
@endsection
