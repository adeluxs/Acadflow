@extends('layouts.auth')
@section('title', 'Create account')
@section('content')
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <div class="mb-7">
        <p class="text-sm font-semibold text-indigo-700">Join the academic network</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Create your AcadSyntra account</h2>
        <p class="mt-2 text-slate-600">Choose the path that fits your academic work. Start independently and connect an institution later if it becomes relevant.</p>
    </div>

    <form method="POST" action="{{ route('store-register') }}" class="space-y-5" data-loading-form>
        @csrf
        <div>
            <label for="account_type" class="mb-2 block text-sm font-semibold text-slate-800">I am joining as</label>
            <select id="account_type" aria-invalid="{{ $errors->has('account_type') ? 'true' : 'false' }}" @error('account_type') aria-describedby="account_type-error" @enderror name="account_type" required class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Select your path</option>
                @foreach(\App\Services\OnboardingService::PUBLIC_ACCOUNT_PATHS as $value => $label)
                    <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('account_type')<p id="account_type-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
            <p class="mt-2 text-xs leading-5 text-slate-500">Representing a university, faculty, department or organisation? Institutional onboarding is handled separately so individual users are never blocked from starting.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="first_name" class="mb-2 block text-sm font-semibold text-slate-800">First name</label>
                <input id="first_name" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" @error('first_name') aria-describedby="first_name-error" @enderror name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="last_name" class="mb-2 block text-sm font-semibold text-slate-800">Last name</label>
                <input id="last_name" aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" @error('last_name') aria-describedby="last_name-error" @enderror name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div>
            <label for="username" class="mb-2 block text-sm font-semibold text-slate-800">Username</label>
            <input id="username" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}" @error('username') aria-describedby="username-error" @enderror name="username" value="{{ old('username') }}" required autocomplete="username" placeholder="letters, numbers, dashes or underscores" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            @error('username')<p id="username-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            @error('email')<p id="email-error" class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="mb-2 block text-sm font-semibold text-slate-800">Password</label>
                <div class="relative">
                    <input id="password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror name="password" type="password" required minlength="{{ $passwordPolicy['min_length'] }}" autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="button" data-password-toggle="password" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
                </div>
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800">Confirm password</label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="button" data-password-toggle="password_confirmation" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
                </div>
            </div>
        </div>
        @include('auth.partials.password-policy', [
            'passwordPolicy' => $passwordPolicy,
            'passwordInputId' => 'password',
            'confirmationInputId' => 'password_confirmation',
        ])
        @error('password')<p id="password-error" class="text-sm text-rose-700">{{ $message }}</p>@enderror

        @error('first_name')<p id="first_name-error" class="text-sm text-rose-700">{{ $message }}</p>@enderror
        @error('last_name')<p id="last_name-error" class="text-sm text-rose-700">{{ $message }}</p>@enderror
        <label class="flex items-start gap-3 text-sm text-slate-600">
            <input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-slate-300 text-indigo-700 focus:ring-indigo-500">
            <span>I agree to use AcadSyntra responsibly, respect privacy, and follow academic-integrity and community rules.</span>
        </label>

        <button type="submit" class="w-full rounded-2xl bg-indigo-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-70">Create account and continue</button>
    </form>

    <p class="mt-7 text-center text-sm text-slate-600">Already registered? <a href="{{ route('login') }}" class="font-bold text-indigo-700 hover:text-indigo-900">Sign in</a></p>
</div>
@endsection
