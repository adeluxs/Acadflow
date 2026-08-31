@extends('layouts.auth')
@section('title', 'Create account')
@section('content')
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <div class="mb-7">
        <p class="text-sm font-semibold text-blue-700">Join the academic network</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Create your AcadFlow account</h2>
        <p class="mt-2 text-slate-600">Choose the path that best describes you. Institutional membership remains optional until you verify or join an institution.</p>
    </div>

    <form method="POST" action="{{ route('store-register') }}" class="space-y-5" data-loading-form>
        @csrf
        <div>
            <label for="account_type" class="mb-2 block text-sm font-semibold text-slate-800">I am joining as</label>
            <select id="account_type" name="account_type" required class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-blue-600 focus:ring-blue-600">
                <option value="">Select your path</option>
                @foreach([
                    'student' => 'Student',
                    'lecturer' => 'Lecturer or educator',
                    'researcher' => 'Researcher',
                    'university_representative' => 'University representative',
                    'department_representative' => 'Faculty or department representative',
                    'academic_staff' => 'Academic staff',
                    'non_academic_staff' => 'Non-academic staff',
                    'independent_professional' => 'Independent professional',
                    'author_publisher' => 'Author or publisher',
                    'research_discovery' => 'Here to discover research and publications',
                    'community_events' => 'Here for communities, events and challenges',
                    'alumni' => 'Alumni',
                    'organisation' => 'Organisation or research group',
                    'other' => 'Other',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('account_type')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="first_name" class="mb-2 block text-sm font-semibold text-slate-800">First name</label>
                <input id="first_name" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-blue-600 focus:ring-blue-600">
            </div>
            <div>
                <label for="last_name" class="mb-2 block text-sm font-semibold text-slate-800">Last name</label>
                <input id="last_name" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-blue-600 focus:ring-blue-600">
            </div>
        </div>

        <div>
            <label for="username" class="mb-2 block text-sm font-semibold text-slate-800">Username</label>
            <input id="username" name="username" value="{{ old('username') }}" required autocomplete="username" placeholder="letters, numbers, dashes or underscores" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-blue-600 focus:ring-blue-600">
            @error('username')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-blue-600 focus:ring-blue-600">
            @error('email')<p class="mt-2 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="mb-2 block text-sm font-semibold text-slate-800">Password</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required minlength="{{ $passwordPolicy['min_length'] }}" autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-blue-600 focus:ring-blue-600">
                    <button type="button" data-password-toggle="password" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
                </div>
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800">Confirm password</label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-blue-600 focus:ring-blue-600">
                    <button type="button" data-password-toggle="password_confirmation" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
                </div>
            </div>
        </div>
        @include('auth.partials.password-policy', [
            'passwordPolicy' => $passwordPolicy,
            'passwordInputId' => 'password',
            'confirmationInputId' => 'password_confirmation',
        ])
        @error('password')<p class="text-sm text-rose-700">{{ $message }}</p>@enderror

        <label class="flex items-start gap-3 text-sm text-slate-600">
            <input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
            <span>I agree to use AcadFlow responsibly, respect privacy, and follow academic-integrity and community rules.</span>
        </label>

        <button type="submit" class="w-full rounded-2xl bg-blue-700 px-5 py-3.5 font-bold text-white shadow-lg shadow-blue-700/20 hover:bg-blue-800 disabled:cursor-wait disabled:opacity-70">Create account and continue</button>
    </form>

    <p class="mt-7 text-center text-sm text-slate-600">Already registered? <a href="{{ route('login') }}" class="font-bold text-blue-700 hover:text-blue-900">Sign in</a></p>
</div>
@endsection
