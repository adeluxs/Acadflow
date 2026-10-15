@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <p class="text-sm font-semibold text-blue-700">Account recovery</p>
    <h2 class="mt-2 text-3xl font-black text-slate-950">Reset your password</h2>
    <p class="mt-2 text-slate-600">Enter the email linked to your account. We will send a time-limited reset link when the account exists.</p>
    <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5" data-loading-form>
        @csrf
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-blue-600 focus:ring-blue-600">
            @error('email')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>
        <button class="w-full rounded-2xl bg-blue-700 px-5 py-3.5 font-bold text-white hover:bg-blue-800">Send reset link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-bold text-blue-700">Back to sign in</a></p>
</div>
@endsection
