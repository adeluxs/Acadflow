<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Set up your workspace') · {{ $siteName }}</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('branding/acadsyntra-icon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/acadsyntra-icon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="onboarding-experience min-h-screen bg-slate-50 text-slate-900 antialiased">
@include('partials.global-loader')
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white p-1 shadow-sm ring-1 ring-slate-200"><img src="{{ asset('branding/acadsyntra-mark.svg') }}" alt="" class="h-full w-full object-contain"></span>
            <span><strong class="block text-lg">{{ $siteName }}</strong><span class="block text-xs text-slate-500">Personal workspace setup</span></span>
        </a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sign out</button></form>
    </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    @if(session('success'))<div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('info'))<div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('info') }}</div>@endif
    @if($errors->any())<div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
