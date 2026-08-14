@extends('layouts.app')

@section('title', 'Attendance Session Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex flex-col gap-4 md:flex-row md:justify-between md:items-start mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Attendance Session</h1>
                    <p class="text-gray-600 mt-1">{{ $session->course->code }} - {{ $session->course->name }}</p>
                    <p class="text-sm text-gray-500">Lecturer: {{ $session->lecturer->full_name }}</p>
                </div>
                <div class="md:text-right">
                    <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $session->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ ucfirst($session->status) }}
                    </span>
                    @can('stop', $session)
                        <div class="mt-3">
                            <form method="POST" action="{{ route('attendance.close', $session) }}" class="inline" onsubmit="return confirm('Close this attendance session? Pending students will be marked absent.')">
                                @csrf
                                <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 text-sm">
                                    Close Session
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Started</p>
                    <p class="font-medium">{{ $session->started_at->format('M d, Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Ended</p>
                    <p class="font-medium">{{ $session->ended_at?->format('M d, Y H:i') ?? 'Ongoing' }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Students</p>
                    <p class="font-medium">{{ $summary['total'] }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Present / Late</p>
                    <p class="font-medium">{{ $summary['present'] }} / {{ $summary['late'] }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Attendance rate</p>
                    <p class="font-medium">{{ number_format($summary['present_rate'], 1) }}%</p>
                </div>
            </div>

            @can('edit', $session)
                <div class="mt-4 p-4 bg-blue-50 rounded">
                    <h3 class="font-medium text-blue-900 mb-2">QR Code for Student Check-in</h3>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <canvas id="qr-code" class="bg-white p-2 rounded" aria-label="Attendance check-in QR code"></canvas>
                        <div>
                            <p class="text-sm text-blue-700">Expires: <span id="expires-at">{{ $session->qr_expires_at->format('H:i:s') }}</span></p>
                            <p class="text-xs text-blue-600 mt-1">Students must be signed in and enrolled in this course.</p>
                            <button type="button" onclick="refreshQr()" class="mt-2 text-blue-700 hover:underline text-sm">
                                Refresh QR Code
                            </button>
                            <p id="qr-error" class="hidden mt-2 text-sm text-red-700"></p>
                        </div>
                    </div>
                </div>
            @endcan
        </div>

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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Verification</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($session->records as $record)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $record->user->full_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $record->user->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        @if($record->status === 'present') bg-green-100 text-green-800
                                        @elseif($record->status === 'late') bg-yellow-100 text-yellow-800
                                        @elseif($record->status === 'absent' || $record->status === 'invalid') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $record->check_in_at?->format('M d, H:i') ?? 'Not checked in' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($record->latitude !== null && $record->longitude !== null)
                                        {{ number_format((float) $record->latitude, 6) }}, {{ number_format((float) $record->longitude, 6) }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $record->verification_notes ?: ($record->status === 'pending' ? 'Pending' : 'Not recorded') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">No enrolled students were found for this session.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ auth()->user()->isLecturer() ? route('attendance.lecturer') : route('admin.reports') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                Back
            </a>
            @can('export', $session)
                <a href="{{ route('admin.reports.export', ['type' => 'attendance', 'session_id' => $session->id]) }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Export Report
                </a>
            @endcan
        </div>
    </div>
</div>

@can('edit', $session)
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
const initialQrPayload = @json($qrPayload);
const refreshQrUrl = @json(route('attendance.qr.refresh', $session));
const csrfToken = @json(csrf_token());
let refreshInterval;

function generateQrCode(data) {
    const canvas = document.getElementById('qr-code');
    QRCode.toCanvas(canvas, data, { width: 180, margin: 1 }, function (error) {
        if (error) {
            document.getElementById('qr-error').textContent = 'Unable to render the QR code.';
            document.getElementById('qr-error').classList.remove('hidden');
        }
    });
}

async function refreshQr() {
    const errorElement = document.getElementById('qr-error');
    errorElement.classList.add('hidden');

    try {
        const response = await fetch(refreshQrUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Unable to refresh the QR code.');
        }

        generateQrCode(data.qr_payload);
        document.getElementById('expires-at').textContent = new Date(data.qr_expires_at).toLocaleTimeString();
    } catch (error) {
        errorElement.textContent = error.message;
        errorElement.classList.remove('hidden');
    }
}

generateQrCode(initialQrPayload);
refreshInterval = window.setInterval(refreshQr, 50000);
window.addEventListener('beforeunload', () => window.clearInterval(refreshInterval));
</script>
@endcan
@endsection
