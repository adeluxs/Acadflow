@extends('layouts.auth')
@section('title', 'Sign in')
@section('content')
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <div class="mb-8">
        <p class="text-sm font-semibold text-indigo-700">Welcome back</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Continue your academic work</h2>
        <p class="mt-2 text-slate-600">Access your research, publications, communities, groups, events and learning activity.</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="space-y-5" data-loading-form>
        @csrf
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror name="email" type="email" autocomplete="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500 @error('email') border-rose-400 @enderror">
            @error('email')<p id="email-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <label for="password" class="text-sm font-semibold text-slate-800">Password</label>
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">Forgot password?</a>
            </div>
            <div class="relative">
                <input id="password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror name="password" type="password" autocomplete="current-password" required
                       class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-indigo-500 focus:ring-indigo-500 @error('password') border-rose-400 @enderror">
                <button type="button" data-password-toggle="password" aria-pressed="false" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
            </div>
            @error('password')<p id="password-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-center gap-3 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="rounded border-slate-300 text-indigo-700 focus:ring-indigo-500">
            Keep me signed in on this device
        </label>

        <button type="submit" class="w-full rounded-2xl bg-indigo-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-70">Sign in to AcadSyntra</button>
    </form>

    <p class="mt-7 text-center text-sm text-slate-600">New to AcadSyntra? <a href="{{ route('register') }}" class="font-bold text-indigo-700 hover:text-indigo-900">Create your account</a></p>
</div>
@endsection
