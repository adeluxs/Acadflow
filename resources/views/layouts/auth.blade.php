<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') · {{ $siteName }}</title>
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('branding/acadsyntra-icon-192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/acadsyntra-icon-180.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-experience min-h-screen bg-slate-950 text-slate-900 antialiased">
@include('partials.global-loader')
<a href="#auth-main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4">Skip to account form</a>
@php($isRegistration = request()->routeIs('register'))
<div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_.95fr]">
    <section class="relative hidden overflow-hidden lg:flex lg:min-h-screen lg:flex-col {{ $isRegistration ? 'lg:justify-start lg:gap-10' : 'lg:justify-between' }} bg-gradient-to-br from-indigo-950 via-indigo-900 to-slate-950 px-12 py-10 text-white">
        <div class="absolute inset-0 opacity-20" aria-hidden="true">
            <div class="absolute -left-24 top-20 h-80 w-80 rounded-full bg-cyan-300 blur-3xl"></div>
            <div class="absolute bottom-10 right-0 h-96 w-96 rounded-full bg-violet-400 blur-3xl"></div>
        </div>
        <div class="relative">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white p-1 shadow-lg ring-1 ring-white/20"><img src="{{ asset('branding/acadsyntra-mark.svg') }}" alt="" class="h-full w-full object-contain"></span>
                <span>
                    <strong class="block text-2xl">{{ $siteName }}</strong>
                    <span class="text-sm text-blue-100">A connected space for academic work.</span>
                </span>
            </a>
        </div>

        <div class="relative max-w-xl">
            <p class="mb-4 text-sm font-semibold uppercase tracking-[.24em] text-cyan-200">Make room for your next idea</p>
            <h1 class="text-5xl font-black leading-tight">Your questions deserve a place to grow.</h1>
            <p class="mt-6 text-lg leading-8 text-blue-100">Bring your learning, research and academic community together. Start with an idea, find the evidence and build something worth sharing.</p>

            <div class="mt-10 grid grid-cols-2 gap-4 text-sm">
                @foreach([
                    ['Research Studio', 'Supervision, writing, validation and approvals'],
                    ['Knowledge Hub', 'Publish and discover academic work'],
                    ['Communities & groups', 'Build public networks and focused teams'],
                    ['Events & challenges', 'Learn, participate, judge and earn recognition'],
                ] as [$heading, $copy])
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                        <strong class="block">{{ $heading }}</strong>
                        <span class="mt-1 block text-blue-100">{{ $copy }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="relative text-sm text-blue-200 {{ $isRegistration ? 'lg:mt-auto' : '' }}">For students, lecturers, researchers, institutions, publishers and independent professionals.</p>
    </section>

    <main id="auth-main" class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 sm:px-8">
        <div class="w-full max-w-xl">
            <a href="{{ route('home') }}" class="{{ $isRegistration ? 'mb-5' : 'mb-8' }} inline-flex items-center gap-3 lg:hidden">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white p-1 shadow-sm ring-1 ring-slate-200"><img src="{{ asset('branding/acadsyntra-mark.svg') }}" alt="" class="h-full w-full object-contain"></span>
                <span class="text-xl font-bold text-slate-950">{{ $siteName }}</span>
            </a>

            @include('partials.feedback')

            @yield('content')

            <p class="mt-8 text-center text-xs text-slate-500"><a href="{{ route('public.page', 'terms') }}" class="underline underline-offset-4">Terms</a> <span aria-hidden="true">·</span> <a href="{{ route('public.page', 'privacy') }}" class="underline underline-offset-4">Privacy</a> <span aria-hidden="true">·</span> <a href="{{ route('public.page', 'help') }}" class="underline underline-offset-4">Help centre</a></p>
        </div>
    </main>
</div>
<script>
(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', `Show ${input.id === 'password_confirmation' ? 'password confirmation' : 'password'}`);
    });
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        const show = input.type === 'password'; input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${input.id === 'password_confirmation' ? 'password confirmation' : 'password'}`);
    });
    document.querySelectorAll('form[data-loading-form]').forEach((form) => {
        const button = form.querySelector('button[type="submit"], button:not([type])');
        if (!button) return;
        const text = button.textContent;
        const restore = () => { button.disabled = false; button.textContent = text; form.removeAttribute('aria-busy'); };
        form.addEventListener('submit', () => {
            if (!form.checkValidity()) return;
            button.disabled = true; button.textContent = 'Please wait…'; form.setAttribute('aria-busy', 'true');
            setTimeout(restore, 15000);
        });
        window.addEventListener('pageshow', restore);
    });
})();
</script>
@stack('scripts')
</body>
</html>
