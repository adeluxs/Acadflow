<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Workspace'); ?> · <?php echo e($siteName); ?></title>

    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <?php if(auth()->guard()->check()): ?>
    <meta name="push-account-id" content="<?php echo e(auth()->id()); ?>">
    <meta name="push-config-url" content="<?php echo e(route('browser-push.configuration')); ?>">
    <meta name="push-worker-url" content="<?php echo e($pwaAvailable ? $pwaWorkerUrl : '/pushworker.js'); ?>">
    <?php endif; ?>

    
    <?php if($pwaAvailable): ?>
    <link rel="manifest" href="/manifest.webmanifest">
    <?php endif; ?>
    <meta name="theme-color" content="<?php echo e($primaryColor); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo e($siteName); ?>">
    <?php if($siteFavicon): ?>
        <link rel="icon" type="image/png" sizes="192x192" href="<?php echo e($siteFavicon); ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" sizes="192x192" href="<?php echo e(asset('branding/acadsyntra-icon-192.png')); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo e(asset('branding/acadsyntra-icon-180.png')); ?>">
    <?php endif; ?>

    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <link href="/css/mobile-optimized.css" rel="stylesheet">
    <style>:root{--acad-primary:<?php echo e($primaryColor); ?>;--acad-primary-rgb:<?php echo e($primaryR); ?> <?php echo e($primaryG); ?> <?php echo e($primaryB); ?>;}</style>


    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php echo $__env->make('partials.global-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    
    <div id="offline-banner" class="fixed top-0 left-0 right-0 z-50 hidden bg-amber-500 text-white text-center text-sm py-2 px-4">
        <span class="inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856a1.54 1.54 0 002.502-1.667L13.732 4a1.54 1.54 0 00-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            You are offline. Changes will sync when connection returns.
        </span>
    </div>

    
    <?php if($pwaAvailable): ?>
    <template id="acadsyntra-update-template">
        <div id="acadsyntra-update-toast" class="fixed top-4 right-4 left-4 sm:left-auto z-50 sm:max-w-sm rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl" role="status" aria-live="polite">
            <p class="text-sm font-semibold text-slate-900"><?php echo e($siteName); ?> has been updated</p>
            <p class="mt-1 text-xs text-slate-600" data-update-message>A new version is available. Refresh to enjoy the latest improvements.</p>
            <div class="mt-3 flex items-center gap-2">
                <button type="button" class="acad-primary-button" data-update-refresh>Refresh now</button>
                <button type="button" class="rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" data-update-later>Later</button>
            </div>
        </div>
    </template>
    <div id="pwa-install-prompt" class="fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:w-96 z-50 hidden">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-2xl p-4">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="h-11 w-11 rounded-2xl bg-white p-1 shadow-sm ring-1 ring-slate-200">
                        <img src="<?php echo e(asset('branding/acadsyntra-mark.svg')); ?>" alt="" class="h-full w-full object-contain">
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm">Install <?php echo e($siteName); ?></h3>
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
                <button onclick="installPwa()" class="acad-primary-button flex-1">
                    Install
                </button>
                <button onclick="dismissPwaPrompt()" class="flex-1 rounded-xl bg-slate-100 text-slate-700 text-sm py-2.5 px-4 hover:bg-slate-200 transition">
                    Not now
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    
    <div id="sync-status" class="fixed bottom-4 right-4 z-40 hidden">
        <div class="rounded-2xl border border-slate-200 bg-white shadow-lg px-3 py-2 flex items-center gap-2 text-sm">
            <div id="sync-indicator" class="h-2 w-2 rounded-full bg-slate-300"></div>
            <span id="sync-text">Ready</span>
        </div>
    </div>

    <div class="min-h-screen">
        
        <header class="lg:hidden sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-slate-200">
            <div class="flex items-center justify-between px-4 py-3">
                <?php if(auth()->guard()->check()): ?>
                <button id="mobile-menu-btn" onclick="toggleMobileSidebar()" class="h-11 w-11 rounded-2xl border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <?php endif; ?>

                <a href="<?php echo e(route('dashboard')); ?>" class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white p-0.5 shadow-sm ring-1 ring-slate-200">
                        <img src="<?php echo e(asset('branding/acadsyntra-mark.svg')); ?>" alt="" class="h-full w-full object-contain">
                    </span>
                    <span class="font-bold text-lg text-slate-950"><?php echo e($siteName); ?></span>
                </a>

                <?php if(auth()->guard()->check()): ?>
                <?php if($notificationsVisible): ?>
                <a href="<?php echo e(route('notifications.index')); ?>" class="h-11 w-11 rounded-2xl border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition relative">
                    <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <?php if($unreadNotificationCount > 0): ?>
                        <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center">
                            <?php echo e($unreadNotificationCount); ?>

                        </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </header>

        <div class="flex">
            
            <?php if(auth()->guard()->check()): ?>
            <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/60 z-40 hidden lg:hidden" onclick="toggleMobileSidebar()"></div>
            <?php endif; ?>

            
            <?php if(auth()->guard()->check()): ?>
            <aside id="sidebar" class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 bg-white border-r border-slate-200">
                <div class="px-5 py-5 border-b border-slate-100">
                    <a href="<?php echo e(route('dashboard')); ?>" class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white p-0.5 shadow-sm ring-1 ring-slate-200">
                            <img src="<?php echo e(asset('branding/acadsyntra-mark.svg')); ?>" alt="" class="h-full w-full object-contain">
                        </span>
                        <h1 class="text-lg font-black tracking-tight text-slate-950"><?php echo e($siteName); ?></h1>
                    </a>
                </div>

                <div class="flex-1 px-4 py-4 overflow-y-auto">
                    <?php echo $__env->make('partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>

                <div class="p-3 border-t border-slate-100">
                    <button type="button" onclick="toggleUserDropdown()" class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-slate-50">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-200 text-xs font-bold text-slate-600">
                            <?php if(auth()->user()?->avatar_media_id && auth()->user()?->avatarMedia): ?>
                                <img src="<?php echo e(route('media.preview', auth()->user()->avatarMedia)); ?>" alt="" class="h-full w-full object-cover">
                            <?php elseif(auth()->user()?->avatar): ?>
                                <img src="<?php echo e(asset('storage/' . auth()->user()->avatar)); ?>" alt="" class="h-full w-full object-cover">
                            <?php else: ?>
                                <?php echo e(strtoupper(substr(auth()->user()?->first_name ?? 'U', 0, 1))); ?>

                            <?php endif; ?>
                        </span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold text-slate-900"><?php echo e(auth()->user()?->full_name ?? auth()->user()?->first_name ?? 'User'); ?></span><span class="block truncate text-[10px] capitalize text-slate-500"><?php echo e(str_replace('_',' ',auth()->user()?->role ?? 'member')); ?></span></span>
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M9 7l5 5-5 5"/></svg>
                    </button>
                </div>
            </aside>
            <?php endif; ?>

            
            <main class="flex-1 lg:pl-64 min-w-0">
                <div class="px-4 py-5 sm:px-6 lg:px-7">
                    
                    <div class="hidden lg:flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-xl font-black tracking-tight text-slate-950"><?php echo $__env->yieldContent('page-title', 'Dashboard'); ?></h2>
                            <p class="text-sm text-slate-500 mt-1"><?php echo $__env->yieldContent('page-subtitle', 'Welcome back'); ?></p>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="hidden xl:flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-[11px] font-semibold text-slate-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 012 2v14H3V6a2 2 0 012-2z"/></svg>
                                Academic workspace
                            </div>
                            <?php if($notificationsVisible): ?>
                            <a href="<?php echo e(route('notifications.index')); ?>" class="h-11 w-11 rounded-2xl bg-white border border-slate-200 flex items-center justify-center hover:bg-slate-50 transition relative">
                                <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                <?php if($unreadNotificationCount > 0): ?>
                                    <span class="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center">
                                        <?php echo e($unreadNotificationCount); ?>

                                    </span>
                                <?php endif; ?>
                            </a>
                            <?php endif; ?>

                            
                            <div class="relative">
                                <button
                                    type="button"
                                    onclick="toggleUserDropdown()"
                                    class="h-11 px-4 rounded-2xl bg-white border border-slate-200 flex items-center gap-3 hover:bg-slate-50 transition"
                                >
                                    <div class="h-8 w-8 rounded-full bg-slate-200 flex items-center justify-center overflow-hidden">
                                        <?php if(auth()->user()?->avatar_media_id && auth()->user()?->avatarMedia): ?>
                                            <img src="<?php echo e(route('media.preview', auth()->user()->avatarMedia)); ?>" alt="<?php echo e(auth()->user()->full_name); ?> profile photo" class="h-full w-full object-cover">
                                        <?php elseif(auth()->user()?->avatar): ?>
                                            <img src="<?php echo e(asset('storage/' . auth()->user()->avatar)); ?>" alt="<?php echo e(auth()->user()->full_name); ?> profile photo" class="h-full w-full object-cover">
                                        <?php else: ?>
                                            <span class="text-sm font-semibold text-slate-600">
                                                <?php echo e(strtoupper(substr(auth()->user()?->first_name ?? 'U', 0, 1))); ?>

                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="leading-tight text-left">
                                        <p class="text-sm font-medium text-slate-900">
                                            <?php echo e(auth()->user()?->first_name ?? auth()->user()?->name ?? 'User'); ?>

                                        </p>
                                        <p class="text-xs text-slate-500 capitalize">
                                            <?php echo e(str_replace('_', ' ', auth()->user()?->role ?? 'member')); ?>

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
                                    <?php if($notificationsVisible): ?>
                                    <a href="<?php echo e(route('notifications.settings')); ?>" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                        Notification Preferences
                                    </a>
                                    <?php endif; ?>

                                    <?php if(Route::has('profile.show')): ?>
                                        <a href="<?php echo e(route('profile.show')); ?>" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                            My Profile
                                        </a>
                                        <a href="<?php echo e(route('onboarding.manage')); ?>" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">Manage Onboarding</a>
                                    <?php endif; ?>

                                    <?php if(Route::has('commerce.wallet')): ?>
                                        <a href="<?php echo e(route('commerce.wallet')); ?>" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                            Wallet &amp; Earnings
                                        </a>
                                    <?php endif; ?>
                                    <?php if(Route::has('commerce.features')): ?>
                                        <a href="<?php echo e(route('commerce.features')); ?>" class="block px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">
                                            Paid Feature Access
                                        </a>
                                    <?php endif; ?>

                                    <div class="border-t border-slate-100"></div>

                                    <form method="POST" action="<?php echo e(route('logout')); ?>">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50">
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if($featurePreview && auth()->user()?->isAdmin()): ?>
                        <div class="mb-5 flex flex-col gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span class="font-bold">Admin preview:</span>
                                <?php echo e($featurePreview['title'] ?? 'Feature'); ?> is currently
                                <span class="font-semibold"><?php echo e(str($featurePreview['status'] ?? 'restricted')->headline()); ?></span>
                                for normal users. You can access it because you are an administrator.
                            </div>
                            <?php if(auth()->user()?->role === 'super_admin' && Route::has('admin.settings.features')): ?>
                                <a href="<?php echo e(route('admin.settings.features')); ?>" class="shrink-0 font-bold underline underline-offset-2">Manage features</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php echo $__env->make('partials.feedback', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                    <?php if(request()->routeIs('research.index', 'research.create', 'research.show', 'research.workspace', 'research.plan', 'research.literature.*', 'research.specialized.*', 'research.export.*', 'research.exports.*')): ?>
                        <?php echo $__env->make('research.partials.guide', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endif; ?>

                    <?php if(request()->routeIs('dashboard')): ?>
                        <?php echo $__env->make('onboarding._banner', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php endif; ?>
                    <?php if(session('ambassador_join_intent') && request()->routeIs('dashboard') && auth()->user()?->isStudent()): ?>
                        <div class="alert alert-info" role="status">Ready to continue? <a href="<?php echo e(route('ambassadors.index')); ?>#apply">Finish your Campus Ambassador application.</a></div>
                    <?php endif; ?>
                    <?php if(auth()->user()?->has_ambassador_referral && ! auth()->user()->hasVerifiedEmail() && request()->routeIs('dashboard')): ?>
                        <div class="alert alert-info" role="status">You joined through an ambassador invitation. <a href="<?php echo e(route('ambassadors.index')); ?>">Verify your email and view your invitation progress.</a></div>
                    <?php endif; ?>
                    <?php echo $__env->yieldContent('content'); ?>
                </div>
            </main>
        </div>
    </div>

    <script src="/js/sync-manager.js" defer></script>

    <script>
        <?php if($pwaAvailable): ?>
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

        <?php endif; ?>

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

        <?php if($pwaAvailable): ?>
        if ('serviceWorker' in navigator) {
            // A worker upgrade can retire a cached HTML/error response that
            // broke CSS in this document. Retry only missing local styles,
            // once, after the new worker takes control; preserve form state.
            navigator.serviceWorker.addEventListener('controllerchange', () => {
                const recoverStyles = () => document.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
                    if (link.disabled || link.dataset.workerStyleRetry) return;
                    const url = new URL(link.href, location.href);
                    if (url.origin !== location.origin || !/^\/(?:build\/assets|css)\//.test(url.pathname)) return;
                    // Some browsers expose an empty sheet for cached HTML
                    // rather than reporting a stylesheet load error.
                    try { if (link.sheet?.cssRules.length) return; }
                    catch (_) { if (link.sheet) return; }
                    link.dataset.workerStyleRetry = '1';
                    url.searchParams.set('_sw_retry', Date.now().toString());
                    link.href = url.href;
                });
                if (document.readyState === 'complete') recoverStyles();
                else window.addEventListener('load', recoverStyles, { once: true });
            });
            // Update notice: non-blocking, never tied to navigation or the loader.
            // An updated worker waits (no skipWaiting on install) until the user
            // chooses Refresh now; Later hides this version for the tab session.
            const UPDATE_DISMISSED_KEY = 'acadsyntra_update_dismissed';
            const UPDATE_CHECKED_KEY = 'acadsyntra_update_checked_at';
            const UPDATE_CHECK_INTERVAL_MS = 30 * 60 * 1000;
            let refreshRequested = false;

            navigator.serviceWorker.addEventListener('controllerchange', () => {
                // Reload only for this tab's own explicit request: no reload loops.
                if (!refreshRequested) return;
                refreshRequested = false;
                location.reload();
            });

            // Remember which forms have unsaved edits so Refresh now can warn first.
            document.addEventListener('input', (event) => {
                const form = event.target?.form;
                if (form && (form.method || 'get').toLowerCase() !== 'get') form.dataset.acadUnsaved = '1';
            }, true);
            document.addEventListener('submit', (event) => { delete event.target?.dataset?.acadUnsaved; }, true);

            const workerVersion = (worker) => new Promise((resolve) => {
                const channel = new MessageChannel();
                const timer = setTimeout(() => resolve('unknown'), 1500);
                channel.port1.onmessage = (event) => { clearTimeout(timer); resolve(String(event.data?.version || 'unknown')); };
                try { worker.postMessage({ type: 'GET_VERSION' }, [channel.port2]); }
                catch (_) { clearTimeout(timer); resolve('unknown'); }
            });

            const offerUpdate = async (registration, worker) => {
                // First install has nothing to replace, so there is nothing to announce.
                if (!worker || !navigator.serviceWorker.controller) return;
                const version = await workerVersion(worker);
                let dismissed = null;
                try { dismissed = sessionStorage.getItem(UPDATE_DISMISSED_KEY); } catch (_) {}
                if (dismissed === version || document.getElementById('acadsyntra-update-toast')) return;
                const template = document.getElementById('acadsyntra-update-template');
                if (!template) return;
                const toast = template.content.firstElementChild.cloneNode(true);
                const refresh = toast.querySelector('[data-update-refresh]');
                const message = toast.querySelector('[data-update-message]');
                toast.querySelector('[data-update-later]').addEventListener('click', () => {
                    try { sessionStorage.setItem(UPDATE_DISMISSED_KEY, version); } catch (_) {}
                    toast.remove();
                });
                refresh.addEventListener('click', () => {
                    if (!refresh.dataset.confirmed && document.querySelector('form[data-acad-unsaved="1"]')) {
                        refresh.dataset.confirmed = '1';
                        message.textContent = 'You have unsaved changes on this page. Save them first, or refresh anyway.';
                        refresh.textContent = 'Refresh anyway';
                        return;
                    }
                    refresh.disabled = true;
                    const waiting = registration.waiting;
                    if (!waiting) { location.reload(); return; }
                    refreshRequested = true;
                    waiting.postMessage({ type: 'SKIP_WAITING' });
                    // If activation is slow or blocked, a plain reload still picks up new pages.
                    setTimeout(() => { if (refreshRequested) location.reload(); }, 4000);
                });
                document.body.appendChild(toast);
            };

            const watch = (registration) => {
                if (registration.waiting) void offerUpdate(registration, registration.waiting);
                registration.addEventListener('updatefound', () => {
                    const worker = registration.installing;
                    worker?.addEventListener('statechange', () => {
                        if (worker.state === 'installed') void offerUpdate(registration, worker);
                    });
                });
            };

            const startWorker = async () => {
                try {
                    const workerUrl = new URL(<?php echo json_encode($pwaWorkerUrl, 15, 512) ?>, location.href).href;
                    let registration = await navigator.serviceWorker.getRegistration('/');
                    const current = registration && [registration.active, registration.waiting, registration.installing]
                        .some((worker) => worker?.scriptURL === workerUrl);
                    if (!current) {
                        registration = await navigator.serviceWorker.register(workerUrl, { updateViaCache: 'none' });
                    }
                    watch(registration);
                    // A deploy check is a small background request, at most every 30 minutes,
                    // independent of page navigation; failures are ignored.
                    let checkedAt = 0;
                    try { checkedAt = Number(sessionStorage.getItem(UPDATE_CHECKED_KEY) || 0); } catch (_) {}
                    if (current && Date.now() - checkedAt > UPDATE_CHECK_INTERVAL_MS) {
                        try { sessionStorage.setItem(UPDATE_CHECKED_KEY, String(Date.now())); } catch (_) {}
                        registration.update().catch(() => {});
                    }
                } catch (error) {
                    console.warn('Offline support is unavailable in this browser session.', error);
                }
            };
            window.addEventListener('load', () => {
                (window.requestIdleCallback || ((callback) => setTimeout(callback, 1)))(() => void startWorker(), { timeout: 3000 });
            }, { once: true });
        }
        <?php elseif(!auth()->check() || !app(\App\Services\PushNotificationService::class)->availableFor(auth()->user())): ?>
        // If PWA is switched off after users previously installed it, actively
        // unregister old service workers so the release control takes effect.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.getRegistrations()
                    .then((registrations) => Promise.all(registrations.map((registration) => registration.unregister())))
                    .catch(() => {});
            });
        }
        <?php endif; ?>

        function toggleMobileSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');

            if (!sidebar || !overlay) return;

            const isHidden = sidebar.classList.contains('hidden');

            if (isHidden) {
                sidebar.classList.remove('hidden');
                sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-50', 'w-72', 'max-w-[85vw]');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('hidden');
                sidebar.classList.remove('fixed', 'inset-y-0', 'left-0', 'z-50', 'w-72', 'max-w-[85vw]');
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
                    sidebar.classList.remove('fixed', 'inset-y-0', 'left-0', 'z-50', 'w-72', 'max-w-[85vw]');
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

    <?php echo $__env->yieldPushContent('scripts'); ?>
<?php echo $__env->make('notifications._push-invitation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('partials.whatsapp-support', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/layouts/app.blade.php ENDPATH**/ ?>