@extends('layouts.auth')
@section('title', 'Two-factor verification')
@section('content')
<div class="rounded-3xl border bg-white p-7 shadow-sm sm:p-9">
    <p class="text-sm font-semibold uppercase tracking-wider text-indigo-600">Secure sign-in</p>
    <h1 class="mt-2 text-3xl font-black text-slate-950">Confirm it’s you</h1>
    <p class="mt-3 leading-7 text-slate-600">Enter the six-digit code from your authenticator app. A single unused recovery code also works when your authenticator is unavailable.</p>

    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-7 space-y-5" data-loading-form>
        @csrf
        <label class="block" for="code"><span class="font-semibold text-slate-800">Authentication or recovery code</span><input id="code" name="code" type="text" inputmode="text" autocomplete="one-time-code" autofocus required maxlength="100" value="{{ old('code') }}" class="mt-2 w-full rounded-xl border-slate-300 text-lg tracking-widest focus:border-indigo-500 focus:ring-indigo-500" aria-describedby="code-help"></label>
        <p id="code-help" class="text-sm text-slate-500">Recovery codes are consumed after one successful use.</p>
        <button type="submit" class="w-full rounded-xl bg-indigo-600 px-5 py-3 font-bold text-white hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-70">Verify and continue</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">@csrf<button class="text-sm font-semibold text-slate-600 hover:text-slate-950">Sign out and use another account</button></form>
</div>
@endsection
