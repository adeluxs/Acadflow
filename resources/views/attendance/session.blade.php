@extends('layouts.app')

@section('title', 'Attendance Session Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <!-- Session Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Attendance Session</h1>
                    <p class="text-gray-600 mt-1">{{ $session->course->code }} - {{ $session->course->name }}</p>
                    <p class="text-sm text-gray-500">Lecturer: {{ $session->lecturer->first_name }} {{ $session->lecturer->last_name }}</p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                        {{ $session->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ ucfirst($session->status) }}
                    </span>
                    @if($session->status === 'active')
                        <div class="mt-2">
                            <form method="POST" action="{{ route('attendance.close', $session) }}" class="inline">
                                @csrf
                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm">
                                    Close Session
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Started</p>
                    <p class="font-medium">{{ $session->started_at->format('M d, Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Ended</p>
                    <p class="font-medium">{{ $session->ended_at?->format('M d, Y H:i') ?? 'Ongoing' }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Total Students</p>
                    <p class="font-medium">{{ $session->records->count() }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Present/Late</p>
                    <p class="font-medium">{{ $session->records->whereIn('status', ['present', 'late'])->count() }}</p>
                </div>
            </div>

            @if($session->status === 'active')
                <div class="mt-4 p-4 bg-blue-50 rounded">
                    <h3 class="font-medium text-blue-900 mb-2">QR Code for Check-in</h3>
                    <div class="flex items-center gap-4">
                        <div id="qr-code" class="bg-white p-2 rounded"></div>
                        <div>
                            <p class="text-sm text-blue-700">Expires: <span id="expires-at">{{ $session->qr_expires_at->format('H:i:s') }}</span></p>
                            <button onclick="refreshQr()" class="mt-2 text-blue-600 hover:underline text-sm">
                                Refresh QR Code
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Attendance Records -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold">Attendance Records</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-in Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($session->records as $record)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $record->user->first_name }} {{ $record->user->user->last_name }}
                                    </div>
                                    <div class="text-sm text-gray-500">{{ $record->user->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        @if($record->status === 'present') bg-green-100 text-green-800
                                        @elseif($record->status === 'late') bg-yellow-100 text-yellow-800
                                        @elseif($record->status === 'absent') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $record->check_in_at?->format('M d, H:i') ?? 'Not checked in' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($record->latitude && $record->longitude)
                                        {{ number_format($record->latitude, 6) }}, {{ number_format($record->longitude, 6) }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $record->device_fingerprint ? 'Verified' : 'N/A' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <a href="{{ route('attendance.index') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                Back to Sessions
            </a>
            @if($session->status === 'closed')
                <a href="{{ route('admin.reports.export', 'attendance') }}?session_id={{ $session->id }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Export Report
                </a>
            @endif
        </div>
    </div>
</div>

@if($session->status === 'active')
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
let qrCodeInstance;
let refreshInterval;

function generateQrCode(data) {
    if (qrCodeInstance) {
        qrCodeInstance.clear();
    }
    QRCode.toCanvas(document.getElementById('qr-code'), data, { width: 150 }, function (error) {
        if (error) console.error(error);
    });
}

function refreshQr() {
    fetch('{{ route("attendance.refresh", $session) }}', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        generateQrCode(data.qr_code);
        document.getElementById('expires-at').textContent = new Date(data.qr_expires_at).toLocaleTimeString();
    });
}

// Initial QR code generation
generateQrCode('{{ $session->qr_code }}');

// Auto-refresh QR code every 50 seconds
refreshInterval = setInterval(refreshQr, 50000);

// Clear interval when page unloads
window.addEventListener('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>
@endif
@endsection
