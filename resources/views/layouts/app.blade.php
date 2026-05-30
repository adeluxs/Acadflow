<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'AcadFlow')</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="AcadFlow">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">

    {{-- Styles --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="/css/mobile-optimized.css" rel="stylesheet">


    @stack('styles')
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    {{-- Offline Banner --}}
    <div id="offline-banner" class="fixed top-0 left-0 right-0 z-50 hidden bg-amber-500 text-white text-center text-sm py-2 px-4">
        <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856a1.54 1.54 0 002.502-1.667L13.732 4a1.54 1.54 0 00-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            You are offline. Changes will sync when connection returns.
        </span>
    </div>

    {{-- PWA Install Prompt --}}
    <div id="pwa-install-prompt" class="fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:w-96 z-50 hidden">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-2xl p-4">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold">
                        A
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm">Install AcadFlow</h3>
                        <p class="text-xs text-slate-500">Add to home screen for quick access</p>
                    </div>
                </div>
                <button onclick="dismissPwaPrompt()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="flex gap-2">
                <button onclick="installPwa()" class="flex-1 rounded-xl bg-blue-600 text-white text-sm py-2.5 px-4 hover:bg-blue-700 transition">
                    Install
                </button>
                <button onclick="dismissPwaPrompt()" class="flex-1 rounded-xl bg-slate-100 text-slate-700 text-sm py-2.5 px-4 hover:bg-slate-200 transition">
                    Not now
                </button>
            </div>
        </div>
    </div>

    {{-- Sync Status --}}
    <div id="sync-status" class="fixed bottom-4 right-4 z-40 hidden">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-lg px-3 py-2 flex items-center gap-2 text-sm">
            <div id="sync-indicator" class="h-2 w-2 rounded-full bg-slate-300"></div>
            <span id="sync-text">Ready</span>
        </div>
    </div>

    <div class="min-h-screen">
        {{-- Mobile Top Bar --}}
        <header class="lg:hidden sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200">
            <div class="flex items-center justify-between px-4 py-3">
                @auth
                <button id="mobile-menu-btn" onclick="toggleMobileSidebar()" class="h-11 w-11 rounded-2xl border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                @endauth

                <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                    <div class="h-9 w-9 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold">
                        A
                    </div>
                    <span class="font-semibold text-lg text-slate-900">AcadFlow</span>
                </a>

                @auth
                <a href="{{ route('notifications.index') }}" class="h-11 w-11 rounded-2xl border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition relative">
                    <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    @if(auth()->user()?->unreadNotifications?->count())
                        <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center">
                            {{ auth()->user()->unreadNotifications->count() }}
                        </span>
                    @endif
                </a>
                @endauth
            </div>
        </header>

        <div class="flex">
            {{-- Sidebar overlay on mobile --}}
            @auth
            <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/60 z-40 hidden lg:hidden" onclick="toggleMobileSidebar()"></div>
            @endauth

            {{-- Sidebar --}}
            @auth
            <aside id="sidebar" class="hidden lg:flex lg:w-80 lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 bg-white border-r border-slate-200">
                <div class="px-6 py-5 border-b border-slate-200">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                        <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center shadow-sm text-white font-bold">
                            A
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold leading-tight text-slate-900">AcadFlow</h1>
                            <p class="text-xs text-slate-500">Academic Workflow Platform</p>
                        </div>
                    </a>
                </div>

                <div class="flex-1 px-4 py-4 overflow-y-auto">
                    @include('partials.sidebar')
                </div>

                <div class="p-4 border-t border-slate-200">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-sm font-medium text-slate-900">
                            {{ auth()->user()->first_name ?? auth()->user()->name ?? 'User' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ auth()->user()->email }}
                        </p>
                    </div>
                </div>
            </aside>
            @endauth

            {{-- Main content --}}
            <main class="flex-1 lg:pl-80 min-w-0">
                <div class="px-4 sm:px-6 lg:px-8 py-6">
                    {{-- Desktop top header --}}
                    <div class="hidden lg:flex items-center justify-between mb-8">
                        <div>
                            <h2 class="text-3xl font-semibold text-slate-900">@yield('page-title', 'Dashboard')</h2>
                            <p class="text-sm text-slate-500 mt-1">@yield('page-subtitle', 'Welcome back')</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <a href="{{ route('notifications.index') }}" class="h-11 w-11 rounded-2xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition relative">
                                <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                @if(auth()->user()?->unreadNotifications?->count())
                                    <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center">
                                        {{ auth()->user()->unreadNotifications->count() }}
                                    </span>
                                @endif
                            </a>

                            {{-- User dropdown --}}
                            <div class="relative">
                                <button
                                    type="button"
                                    onclick="toggleUserDropdown()"
                                    class="h-11 px-4 rounded-2xl bg-white border border-slate-200 flex items-center gap-3 hover:bg-slate-50 transition"
                                >
                                    <div class="h-8 w-8 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
                                        @if(auth()->user()?->avatar)
                                            <img src="{{ asset('storage/' . auth()->user()->avatar) }}" alt="Avatar" class="h-full w-full object-cover">
                                        @else
                                            <span class="text-sm font-semibold text-slate-600">
                                                {{ strtoupper(substr(auth()->user()?->first_name ?? 'U', 0, 1)) }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="leading-tight text-left">
                                        <p class="text-sm font-medium text-slate-900">
                                            {{ auth()->user()?->first_name ?? auth()->user()?->name ?? 'User' }}
                                        </p>
                                        <p class="text-xs text-slate-500 capitalize">
                                            {{ str_replace('_', ' ', auth()->user()?->role ?? 'member') }}
                                        </p>
                                    </div>

                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <div
                                    id="userDropdown"
                                    class="hidden absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 overflow-hidden"
                                >
                                    <a href="{{ route('settings.index') }}" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                        Account Settings
                                    </a>

                                    @if(Route::has('profile.show'))
                                        <a href="{{ route('profile.show') }}" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                            My Profile
                                        </a>
                                    @endif

                                    @if(auth()->user()?->isStudent())
                                        @if(Route::has('subscription.show'))
                                            <a href="{{ route('subscription.show') }}" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                                Subscription
                                            </a>
                                        @endif
                                    @endif

                                    <div class="border-t border-slate-100"></div>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50">
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <script src="{{ mix('js/app.js') }}"></script>
    <script src="/js/sync-manager.js"></script>

    <script>
        let deferredPrompt = null;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            setTimeout(() => {
                const prompt = document.getElementById('pwa-install-prompt');
                if (prompt && !localStorage.getItem('pwa_prompt_dismissed')) {
                    prompt.classList.remove('hidden');
                }
            }, 3000);
        });

        window.addEventListener('appinstalled', () => {
            dismissPwaPrompt();
            deferredPrompt = null;
        });

        function installPwa() {
            if (!deferredPrompt) return;

            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(() => {
                deferredPrompt = null;
                dismissPwaPrompt();
            });
        }

        function dismissPwaPrompt() {
            const prompt = document.getElementById('pwa-install-prompt');
            if (prompt) prompt.classList.add('hidden');
            localStorage.setItem('pwa_prompt_dismissed', 'true');
        }

        function updateOnlineStatus() {
            const banner = document.getElementById('offline-banner');
            if (!banner) return;

            if (navigator.onLine) {
                banner.classList.add('hidden');
            } else {
                banner.classList.remove('hidden');
            }
        }

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        document.addEventListener('DOMContentLoaded', updateOnlineStatus);

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/serviceworker.js')
                    .then((registration) => {
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            if (!newWorker) return;

                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    showUpdateNotification();
                                }
                            });
                        });
                    })
                    .catch((error) => console.error('SW registration failed:', error));
            });
        }

        function showUpdateNotification() {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-white rounded-2xl shadow-2xl border border-slate-200 p-4 z-50 max-w-sm';
            notification.innerHTML = `
                <p class="text-sm font-medium text-slate-900 mb-2">New version available</p>
                <p class="text-xs text-slate-500 mb-3">Reload to get the latest update.</p>
                <button onclick="location.reload()" class="rounded-xl bg-blue-600 text-white text-sm px-4 py-2 hover:bg-blue-700 transition">
                    Update Now
                </button>
            `;
            document.body.appendChild(notification);
        }

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (!sidebar || !overlay) return;

            const isHidden = sidebar.classList.contains('hidden');

            if (isHidden) {
                sidebar.classList.remove('hidden');
                sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-50', 'w-80', 'max-w-[85vw]');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('fixed', 'inset-y-0', 'left-0', 'z-50', 'w-80', 'max-w-[85vw]');
                overlay.classList.add('hidden');
            }
        }

        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            if (!dropdown) return;
            dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('userDropdown');
            if (!dropdown) return;

            const clickedInsideDropdown = event.target.closest('#userDropdown');
            const clickedUserButton = event.target.closest('button[onclick="toggleUserDropdown()"]');

            if (!clickedInsideDropdown && !clickedUserButton) {
                dropdown.classList.add('hidden');
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');
                if (sidebar) {
                    sidebar.classList.remove('fixed', 'inset-y-0', 'left-0', 'z-50', 'w-80', 'max-w-[85vw]');
                    sidebar.classList.add('hidden');
                }
                if (overlay) overlay.classList.add('hidden');
            }
        });

        if (window.syncManager && typeof window.syncManager.addListener === 'function') {
            window.syncManager.addListener((event) => {
                const indicator = document.getElementById('sync-indicator');
                const text = document.getElementById('sync-text');
                const container = document.getElementById('sync-status');

                if (!indicator || !text || !container) return;

                container.classList.remove('hidden');

                switch (event) {
                    case 'sync-started':
                        indicator.className = 'h-2 w-2 rounded-full bg-amber-400 animate-pulse';
                        text.textContent = 'Syncing...';
                        break;
                    case 'sync-success':
                        indicator.className = 'h-2 w-2 rounded-full bg-emerald-500';
                        text.textContent = 'Synced';
                        setTimeout(() => container.classList.add('hidden'), 2500);
                        break;
                    case 'sync-error':
                        indicator.className = 'h-2 w-2 rounded-full bg-rose-500';
                        text.textContent = 'Sync failed';
                        break;
                    case 'action-queued':
                        indicator.className = 'h-2 w-2 rounded-full bg-blue-500';
                        text.textContent = 'Queued';
                        break;
                    case 'online':
                        indicator.className = 'h-2 w-2 rounded-full bg-emerald-500';
                        text.textContent = 'Online';
                        break;
                    case 'offline':
                        indicator.className = 'h-2 w-2 rounded-full bg-slate-400';
                        text.textContent = 'Offline';
                        break;
                }
            });
        }
    </script>

    @stack('scripts')
</body>
</html>
