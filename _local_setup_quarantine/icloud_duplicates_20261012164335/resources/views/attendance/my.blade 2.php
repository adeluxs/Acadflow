@extends('layouts.app')
@section('title','My Attendance')
@section('content')
@php
    $pageRecords = $records->getCollection();
    $presentCount = $pageRecords->where('status','present')->count();
    $lateCount = $pageRecords->where('status','late')->count();
@endphp
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <section class="rounded-[2rem] bg-gradient-to-br from-emerald-950 via-slate-950 to-slate-900 p-7 text-white shadow-xl sm:p-9">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-xs font-black uppercase tracking-[.24em] text-emerald-300">Academic presence</p><h1 class="mt-2 text-3xl font-black sm:text-4xl">My attendance</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Check in securely when a session is active and keep a clear record of your attendance history.</p></div><a href="{{ route('attendance.records') }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-black text-slate-950">Detailed records</a></div>
    </section>

    @if($checkInSession)
        <section class="relative overflow-hidden rounded-3xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm sm:p-7"><div class="absolute -right-10 -top-10 h-36 w-36 rounded-full bg-emerald-200/50 blur-3xl"></div><div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"><div><span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800">Live check-in available</span><h2 class="mt-3 text-2xl font-black text-slate-900">{{ $checkInSession->course->code }} · {{ $checkInSession->course->name }}</h2><p class="mt-2 text-sm text-slate-600">QR access expires {{ $checkInSession->qr_expires_at->diffForHumans() }}. Location is captured only when available and needed for session verification.</p></div><form method="POST" action="{{ route('attendance.checkin') }}" id="attendance-checkin-form" class="shrink-0">@csrf<input type="hidden" name="session_uuid" value="{{ $checkInSession->uuid }}"><input type="hidden" name="qr_code" value="{{ $checkInQrCode }}"><input type="hidden" name="latitude" id="checkin-latitude"><input type="hidden" name="longitude" id="checkin-longitude"><input type="hidden" name="device_fingerprint" id="device-fingerprint"><button class="w-full rounded-xl bg-emerald-700 px-6 py-3 text-sm font-black text-white hover:bg-emerald-800">Confirm check-in</button><p id="location-status" class="mt-2 max-w-xs text-xs text-emerald-700">Attempting to verify your current location…</p></form></div></section>
    @endif

    <div class="grid gap-4 sm:grid-cols-3"><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-black uppercase tracking-wide text-slate-400">Records on this page</p><p class="mt-2 text-3xl font-black text-slate-900">{{ $pageRecords->count() }}</p></div><div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5"><p class="text-xs font-black uppercase tracking-wide text-emerald-600">Present</p><p class="mt-2 text-3xl font-black text-emerald-800">{{ $presentCount }}</p></div><div class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><p class="text-xs font-black uppercase tracking-wide text-amber-600">Late</p><p class="mt-2 text-3xl font-black text-amber-800">{{ $lateCount }}</p></div></div>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 px-6 py-5"><h2 class="text-lg font-black text-slate-900">Recent attendance</h2></div><div class="overflow-x-auto"><table class="min-w-full"><thead class="bg-slate-50 text-left text-[11px] font-black uppercase tracking-wider text-slate-400"><tr><th class="px-6 py-3">Date</th><th class="px-6 py-3">Course</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Check-in</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($records as $record)<tr class="hover:bg-slate-50/70"><td class="px-6 py-4 text-sm font-semibold text-slate-700">{{ ($record->check_in_at ?? $record->session?->started_at)?->format('M j, Y') ?? 'N/A' }}</td><td class="px-6 py-4"><p class="text-sm font-black text-slate-900">{{ $record->session?->course?->code ?? 'N/A' }}</p><p class="text-xs text-slate-400">{{ $record->session?->course?->name ?? '' }}</p></td><td class="px-6 py-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $record->status==='present'?'bg-emerald-100 text-emerald-700':($record->status==='late'?'bg-amber-100 text-amber-700':($record->status==='pending'?'bg-slate-100 text-slate-600':'bg-rose-100 text-rose-700')) }}">{{ str($record->status)->headline() }}</span></td><td class="px-6 py-4 text-sm font-semibold text-slate-600">{{ $record->check_in_at?->format('H:i') ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500">No attendance records found.</td></tr>@endforelse</tbody></table></div></section>
    {{ $records->links() }}
</div>
@if($checkInSession)
<script>
document.getElementById('device-fingerprint').value = navigator.userAgent.slice(0,255);
const locationStatus=document.getElementById('location-status');
if('geolocation' in navigator){navigator.geolocation.getCurrentPosition(position=>{document.getElementById('checkin-latitude').value=position.coords.latitude;document.getElementById('checkin-longitude').value=position.coords.longitude;locationStatus.textContent='Location captured. You may confirm your check-in.';},()=>{locationStatus.textContent='Location was not available. Check-in can continue unless this session requires geofencing.';},{enableHighAccuracy:true,timeout:8000,maximumAge:30000});}else{locationStatus.textContent='This browser cannot provide location. Check-in can continue unless this session requires geofencing.';}
</script>
@endif
@endsection
