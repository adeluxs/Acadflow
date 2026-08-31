@extends('layouts.auth')
@section('title', 'Choose new password')
@section('content')
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <p class="text-sm font-semibold text-blue-700">Secure your account</p>
    <h2 class="mt-2 text-3xl font-black text-slate-950">Choose a new password</h2>
    <form method="POST" action="{{ route('password.store') }}" class="mt-7 space-y-5" data-loading-form>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autocomplete="email" class="w-full rounded-2xl border-slate-300 px-4 py-3">
        </div>
        <div>
            <label for="password" class="mb-2 block text-sm font-semibold text-slate-800">New password</label>
            <div class="relative"><input id="password" name="password" type="password" required minlength="{{ $passwordPolicy['min_length'] }}" autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16"><button type="button" data-password-toggle="password" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button></div>
        </div>
        <div>
            <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800">Confirm password</label>
            <div class="relative"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16"><button type="button" data-password-toggle="password_confirmation" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button></div>
        </div>
        @include('auth.partials.password-policy', [
            'passwordPolicy' => $passwordPolicy,
            'passwordInputId' => 'password',
            'confirmationInputId' => 'password_confirmation',
        ])
        @error('password')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror
        <button class="w-full rounded-2xl bg-blue-700 px-5 py-3.5 font-bold text-white hover:bg-blue-800">Update password</button>
    </form>
</div>
@endsection
