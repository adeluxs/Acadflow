@extends('layouts.auth')
@section('title', 'Verify email')
@section('content')
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <div class="grid h-14 w-14 place-items-center rounded-2xl bg-blue-100 text-2xl text-blue-700">✉</div>
    <h2 class="mt-5 text-3xl font-black text-slate-950">Verify your email address</h2>
    <p class="mt-3 leading-7 text-slate-600">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Verify it before entering your AcadFlow workspace.</p>
    @if(session('status') === 'verification-link-sent')
        <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">A new verification link has been sent.</div>
    @endif
    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
        <form method="POST" action="{{ route('verification.send') }}" class="flex-1" data-loading-form>@csrf<button class="w-full rounded-2xl bg-blue-700 px-5 py-3 font-bold text-white hover:bg-blue-800">Resend verification email</button></form>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-2xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">Sign out</button></form>
    </div>
</div>
@endsection
