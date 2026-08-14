@extends('layouts.app')

@section('title', 'My Attendance')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <h1 class="text-2xl font-bold">My Attendance</h1>
        <a href="{{ route('attendance.records') }}" class="text-blue-600 hover:underline">View detailed records</a>
    </div>

    @if($checkInSession)
        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5">
            <h2 class="text-lg font-semibold text-blue-950">Check in to {{ $checkInSession->course->code }}</h2>
            <p class="mt-1 text-sm text-blue-800">{{ $checkInSession->course->name }} · QR expires {{ $checkInSession->qr_expires_at->diffForHumans() }}</p>
            <form method="POST" action="{{ route('attendance.checkin') }}" class="mt-4" id="attendance-checkin-form">
                @csrf
                <input type="hidden" name="session_uuid" value="{{ $checkInSession->uuid }}">
                <input type="hidden" name="qr_code" value="{{ $checkInQrCode }}">
                <input type="hidden" name="latitude" id="checkin-latitude">
                <input type="hidden" name="longitude" id="checkin-longitude">
                <input type="hidden" name="device_fingerprint" id="device-fingerprint">
                <button type="submit" class="rounded bg-blue-700 px-5 py-2.5 font-medium text-white hover:bg-blue-800">
                    Confirm Check-in
                </button>
                <p id="location-status" class="mt-2 text-xs text-blue-700">Attempting to verify your current location…</p>
            </form>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
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
                            <td class="px-6 py-4">{{ ($record->check_in_at ?? $record->session?->started_at)?->format('Y-m-d') ?? 'N/A' }}</td>
                            <td class="px-6 py-4">{{ $record->session?->course?->code ?? 'N/A' }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    @if($record->status === 'present') bg-green-100 text-green-800
                                    @elseif($record->status === 'late') bg-yellow-100 text-yellow-800
                                    @elseif($record->status === 'pending') bg-gray-100 text-gray-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ ucfirst($record->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">{{ $record->check_in_at?->format('H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">No attendance records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
</div>

@if($checkInSession)
<script>
document.getElementById('device-fingerprint').value = navigator.userAgent.slice(0, 255);
const locationStatus = document.getElementById('location-status');

if ('geolocation' in navigator) {
    navigator.geolocation.getCurrentPosition(
        position => {
            document.getElementById('checkin-latitude').value = position.coords.latitude;
            document.getElementById('checkin-longitude').value = position.coords.longitude;
            locationStatus.textContent = 'Location captured. You may confirm your check-in.';
        },
        () => {
            locationStatus.textContent = 'Location was not available. Check-in can continue unless this session requires geofencing.';
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
    );
} else {
    locationStatus.textContent = 'This browser cannot provide location. Check-in can continue unless this session requires geofencing.';
}
</script>
@endif
@endsection
