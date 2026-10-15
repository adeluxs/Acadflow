@extends('layouts.app')

@section('title', $featureStatus === 'maintenance' ? 'Feature Maintenance' : 'Feature Unavailable')

@section('content')
<div class="mx-auto flex min-h-[64vh] max-w-4xl items-center justify-center px-4 py-10">
    <div class="w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
        <div class="relative overflow-hidden px-6 py-10 sm:px-10">
            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-indigo-100/70 blur-2xl"></div>
            <div class="absolute -bottom-20 -left-10 h-52 w-52 rounded-full bg-violet-100/70 blur-2xl"></div>
            <div class="relative">
                <div class="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100">
                    @if($featureStatus === 'maintenance')
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 4h2m-1-1v2m6.4 2.6-1.4 1.4M19 12h2M5 12H3m3.6-4.4L5.2 6.2M8 21h8m-7-4h6a3 3 0 003-3v-1a6 6 0 10-12 0v1a3 3 0 003 3z"/></svg>
                    @else
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>

                <div class="mb-3 inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                    {{ $featureStatus === 'maintenance' ? 'Temporarily under maintenance' : 'Currently unavailable' }}
                </div>
                <h1 class="max-w-2xl text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{{ $featureTitle }}</h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">{{ $featureMessage }}</p>

                <div class="mt-8 flex flex-wrap gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="acad-primary-button inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold">Return to dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="acad-primary-button inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold">Log in</a>
                    @endauth
                    <a href="{{ route('public.page', 'status') }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Platform status</a>
                </div>

                <p class="mt-8 text-xs text-slate-400">Feature reference: {{ $feature }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
