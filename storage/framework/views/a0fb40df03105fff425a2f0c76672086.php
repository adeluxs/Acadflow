<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(\App\Services\SettingService::get('site_name', 'UniAcademic')); ?> - <?php echo e(\App\Services\SettingService::get('site_tagline', 'University Academic Management Platform')); ?></title>

    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <?php if(\App\Services\SettingService::get('site_favicon')): ?>
        <link rel="icon" type="image/png" sizes="192x192" href="<?php echo e(\App\Services\SettingService::get('site_favicon')); ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
    <?php endif; ?>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="bg-blue-50 text-slate-900 antialiased">
    <div class="relative overflow-x-hidden">
        <header class="absolute top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-xl border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-sm">
                            A
                        </div>
                        <span class="font-semibold text-xl text-slate-900"><?php echo e(\App\Services\SettingService::get('site_name', 'UniAcademic')); ?></span>
                    </div>

                    <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                        <a href="#features" class="hover:text-slate-900 transition">Features</a>
                        <a href="#testimonials" class="hover:text-slate-900 transition">Testimonials</a>
                        <a href="#pricing" class="hover:text-slate-900 transition">Pricing</a>
                        <a href="#contact" class="hover:text-slate-900 transition">Contact</a>
                    </nav>

                    <div class="flex items-center gap-3">
                        <a href="<?php echo e(route('login')); ?>"
                           class="hidden sm:inline-block text-sm font-medium text-slate-700 hover:text-slate-900 transition">
                            Log in
                        </a>
                        <a href="<?php echo e(route('register')); ?>"
                           class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">
                            Get started
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main class="pt-16">
            <section class="py-20 md:py-28 lg:py-32">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                        <div class="lg:pr-8">
                            <div class="inline-flex items-center gap-2 px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-xs font-medium mb-6">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 8c-2.21 0-4 1.79-4 4 0 .88.29 1.69.8 2.36l-.8.8a6 6 0 111.6-7.2h.4z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 15l2 2 3-3"></path>
                                </svg>
                                <span>Modern academic management platform</span>
                            </div>

                            <h1 class="text-4xl sm:text-4xl md:text-5xl font-bold text-slate-900 leading-tight mb-6">
                                Streamline your academic workflow
                            </h1>

                            <p class="text-lg text-slate-600 mb-8 max-w-md">
                                A comprehensive platform designed for universities, lecturers, and students to manage courses, submissions, AI-powered academic assistance, and analytics all in one place.
                            </p>

                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="<?php echo e(route('register')); ?>"
                                   class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-6 py-3 text-sm font-medium text-white hover:bg-indigo-700 transition">
                                    Get started
                                </a>
                                <a href="<?php echo e(route('login')); ?>"
                                   class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-6 py-3 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                    Watch demo
                                </a>
                            </div>
                        </div>

                        <div class="relative">
                            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 500 500" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="gradient-1" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#a78bfa" />
                                        <stop offset="50%" stop-color="#8b5cf6" />
                                        <stop offset="100%" stop-color="#4361ee" />
                                    </linearGradient>
                                    <linearGradient id="gradient-2" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#f59e0b" />
                                        <stop offset="100%" stop-color="#f97316" />
                                    </linearGradient>
                                    <linearGradient id="gradient-3" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" stop-color="#10b981" />
                                        <stop offset="100%" stop-color="#059669" />
                                    </linearGradient>
                                </defs>

                                <circle cx="250" cy="250" r="180" fill="url(#gradient-1)" opacity="0.15" />
                                <circle cx="180" cy="180" r="100" fill="url(#gradient-2)" opacity="0.08" />
                                <circle cx="320" cy="300" r="80" fill="url(#gradient-3)" opacity="0.1" />

                                <circle cx="380" cy="180" r="20" fill="#4361ee" />
                                <circle cx="420" cy="140" r="16" fill="#8b5cf6" />
                                <circle cx="450" cy="220" r="12" fill="#f59e0b" />
                                <circle cx="120" cy="340" r="18" fill="#10b981" />
                                <circle cx="90" cy="120" r="14" fill="#ef4444" />
                            </svg>

                            <div class="relative grid grid-cols-2 gap-4 mt-8">
                                <div class="bg-white rounded-2xl shadow-xl p-4 border border-slate-200">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="h-8 w-8 rounded-lg bg-indigo-100 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m2 0a2 2 0 100-4 2 2 0 000 4zm0 0h2a2 2 0 100-4m-2 4a2 2 0 01-2-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2h2m0 0v4a2 2 0 002 2h2a2 2 0 002-2v-4m-4 0H9"></path>
                                            </svg>
                                        </div>
                                        <div class="font-semibold text-sm text-slate-900">Course Management</div>
                                    </div>
                                    <p class="text-xs text-slate-500">Easily create and manage academic courses</p>
                                </div>

                                <div class="bg-white rounded-2xl shadow-xl p-4 border border-slate-200">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="h-8 w-8 rounded-lg bg-amber-100 flex items-center justify-center">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m2 0a2 2 0 100-4 2 2 0 000 4zm0 0h2a2 2 0 100-4m-2 4a2 2 0 01-2-2V6a2 2 0 00-2-2H7a2 2 0 00-2 2v6a2 2 0 002 2h2m0 0v4a2 2 0 002 2h2a2 2 0 002-2v-4m-4 0H9"></path>
                                            </svg>
                                        </div>
                                        <div class="font-semibold text-sm text-slate-900">AI Assistance</div>
                                    </div>
                                    <p class="text-xs text-slate-500">Smart validation and academic support</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="py-16 md:py-24">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">
                            Everything you need for modern academia
                        </h2>
                        <p class="text-slate-600 max-w-2xl mx-auto">
                            Our platform provides a complete suite of tools designed to streamline academic workflows
                            for students, lecturers, and administrators.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 hover:shadow-md transition">
                            <div class="h-14 w-14 rounded-xl bg-indigo-100 flex items-center justify-center mb-6">
                                <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 15 7.5 15c1.747 0 3.332 4.477 4.5 5.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332 4.477 4.5 5.253v13C19.832 18.477 18.246 15 16.5 15c-1.747 0-3.332 4.477-4.5 5.253z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">Structured Course Management</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Create, organize, and manage courses with intuitive tools for assignments, materials,
                                and student enrollment.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 hover:shadow-md transition">
                            <div class="h-14 w-14 rounded-xl bg-amber-100 flex items-center justify-center mb-6">
                                <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 6l-6-6 6-6"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12l-6 6 6-6z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">AI-Powered Validation</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Automatically validate submissions with AI-powered checking for structure, layout,
                                and academic standards.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 hover:shadow-md transition">
                            <div class="h-14 w-14 rounded-xl bg-emerald-100 flex items-center justify-center mb-6">
                                <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19V6l12-3v13M9 19a3 3 0 11-6 0 3 3 0 016 0zm12 0a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">Real-time Analytics</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Track performance, engagement, and submission trends with insightful analytics
                                and reporting.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-8">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 hover:shadow-md transition">
                            <div class="h-14 w-14 rounded-xl bg-violet-100 flex items-center justify-center mb-6">
                                <svg class="w-7 h-7 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h1a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2h1m2 0v-6a2 2 0 012-2h2a2 2 0 012 2v6m-6 0v6"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">Submission Tracking</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Monitor submission status and grading progress with real-time tracking dashboards.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 hover:shadow-md transition">
                            <div class="h-14 w-14 rounded-xl bg-rose-100 flex items-center justify-center mb-6">
                                <svg class="w-7 h-7 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10m-5 4l3-3m0 0l3 3m-3-3v11a4 4 0 01-8 0V9a4 4 0 018 0v.5"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">Attendance Management</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Record, track, and report attendance with QR code scanning and session controls.
                            </p>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 hover:shadow-md transition">
                            <div class="h-14 w-14 rounded-xl bg-blue-100 flex items-center justify-center mb-6">
                                <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14l9 5-4 10-9-4-9 4 4-10L3 9z"></path>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-slate-900 mb-3">Discussion Forums</h3>
                            <p class="text-slate-600 text-sm leading-relaxed">
                                Foster collaboration with course discussions, replies, and knowledge sharing.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="testimonials" class="py-16 md:py-24 bg-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">
                            Trusted by educators worldwide
                        </h2>
                        <p class="text-slate-600 max-w-2xl mx-auto">
                            Institutions across the country use our platform to enhance their academic operations.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-indigo-600 mb-2">98%</div>
                            <p class="text-slate-600 text-sm">Student satisfaction rate</p>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-indigo-600 mb-2">500+</div>
                            <p class="text-slate-600 text-sm">Active institutions</p>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-indigo-600 mb-2">24/7</div>
                            <p class="text-slate-600 text-sm">Platform availability</p>
                        </div>
                    </div>

                    <div class="mt-12 bg-slate-50 rounded-3xl p-8 md:p-12">
                        <div class="max-w-3xl mx-auto text-center">
                            <svg class="w-8 h-8 text-slate-400 mx-auto mb-4" fill="currentColor"
                                 viewBox="0 0 24 24">
                                <path
                                    d="M14.046 18.127c.023-.01.047-.02.07-.03h2.023c.04 1.18.06 1.77.06 1.77v.92c0 1.73-.73 3.01-1.84 3.86a3.92 3.92 0 01-2.26 1.21 3.86 3.86 0 01-2.18-.67 3.78 3.78 0 01-1.5-1.64c-.49-.99-.77-2.1-.77-3.25V7.43c0-1.08.32-2.14.95-3.16A4.02 4.02 0 0111.14 1.9c.84 0 1.62.37 2.18 1l-2.7 5.4c-.44.01-.86.15-1.22.41-.36.26-.66.6-1 .99a3.72 3.72 0 00-.53 1.16l-.01.04c-.18.62-.33 1.25-.33 1.88v.88a3.76 3.76 0 001.04 2.84c.64.62 1.52 1.04 2.52 1.19v.14l.01.01c.02 1.01.4 1.94 1.12 2.7-.06.04-.13.07-.21.11a3.78 3.78 0 00-.14-.04c-1.1-.3-2.04-1.15-2.57-2.35-.52-1.2-.68-2.54-.5-3.78l-.02-.01c-.1-.18-.17-.36-.29-.52a2.08 2.08 0 01-.14-.21c-.1-.21-.17-.43-.23-.66a2.5 2.5 0 01-.05-.67l.02-8.29a2.46 2.46 0 01.64-1.71 2.33 2.33 0 011.66-.72h1.36a2.36 2.36 0 011.66.72 2.46 2.46 0 01.64 1.71v6.56c0 .54.09 1.07.27 1.58a2.49 2.49 0 00.88 1.24c.37.29.82.49 1.3.58a2.46 2.46 0 001.46-.14l-.03.02a2.33 2.33 0 00.58.07z"></path>
                            </svg>
                            <p class="text-xl text-slate-600 italic mb-6 max-w-2xl mx-auto">
                                "UniAcademic transformed how our university manages courses and submissions.
                                The AI-powered validation saves us hours of manual review every week."
                            </p>
                            <div class="flex items-center justify-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-semibold text-sm">
                                    D
                                </div>
                                <div class="text-left">
                                    <div class="font-semibold text-slate-900">Dr. Sarah Chen</div>
                                    <div class="text-sm text-slate-500">Dean of Academic Affairs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="pricing" class="py-16 md:py-24">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4">
                            Simple, transparent pricing
                        </h2>
                        <p class="text-slate-600 max-w-2xl mx-auto">
                            Choose the plan that fits your needs. All plans include our core features.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                            <h3 class="text-xl font-bold text-slate-900 mb-4">Starter</h3>
                            <p class="text-3xl font-bold text-slate-900 mb-6">$29<span class="text-base font-medium text-slate-500">/mo</span></p>
                            <ul class="space-y-3 mb-8 text-sm">
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Up to 100 students</li>
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Basic AI validation</li>
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Standard support</li>
                            </ul>
                            <button class="w-full rounded-xl border border-slate-300 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                Start free trial
                            </button>
                        </div>

                        <div class="bg-white rounded-2xl shadow-xl border-2 border-indigo-600 p-8 relative">
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-xs font-medium px-3 py-1 rounded-full">
                                Most popular
                            </div>
                            <h3 class="text-xl font-bold text-slate-900 mb-4">Professional</h3>
                            <p class="text-3xl font-bold text-slate-900 mb-6">$79<span class="text-base font-medium text-slate-500">/mo</span></p>
                            <ul class="space-y-3 mb-8 text-sm">
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Up to 1,000 students</li>
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Advanced AI validation</li>
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Priority support</li>
                            </ul>
                            <button class="w-full rounded-xl bg-indigo-600 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 transition">
                                Get started
                            </button>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                            <h3 class="text-xl font-bold text-slate-900 mb-4">Enterprise</h3>
                            <p class="text-3xl font-bold text-slate-900 mb-6">Custom</p>
                            <ul class="space-y-3 mb-8 text-sm">
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Unlimited students</li>
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Custom AI rules</li>
                                <li class="flex items-center gap-2 text-slate-600"><svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 24/7 support</li>
                            </ul>
                            <button class="w-full rounded-xl border border-slate-300 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                                Contact sales
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer id="contact" class="bg-white border-t border-slate-200 py-12 md:py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8 mb-12">
                    <div class="sm:col-span-2 md:col-span-2 lg:col-span-2">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-sm">
                                A
                            </div>
                            <span class="font-semibold text-xl text-slate-900"><?php echo e(\App\Services\SettingService::get('site_name', 'UniAcademic')); ?></span>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">
                            <?php echo e(\App\Services\SettingService::get('site_tagline', 'University Academic Management Platform')); ?>

                        </p>
                        <p class="text-xs text-slate-400">
                            &copy; <?php echo e(date('Y')); ?> <?php echo e(\App\Services\SettingService::get('site_name', 'UniAcademic')); ?>. All rights reserved.
                        </p>
                    </div>

                    <div>
                        <h4 class="font-semibold text-sm text-slate-900 mb-4">Product</h4>
                        <ul class="space-y-3 text-sm text-slate-500">
                            <li><a href="#features" class="hover:text-slate-900 transition">Features</a></li>
                            <li><a href="#pricing" class="hover:text-slate-900 transition">Pricing</a></li>
                            <li><a href="#testimonials" class="hover:text-slate-900 transition">Testimonials</a></li>
                            <li><a href="#contact" class="hover:text-slate-900 transition">Contact</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-sm text-slate-900 mb-4">Support</h4>
                        <ul class="space-y-3 text-sm text-slate-500">
                            <li><a href="#" class="hover:text-slate-900 transition">Help Center</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Documentation</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Community</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Status</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-sm text-slate-900 mb-4">Company</h4>
                        <ul class="space-y-3 text-sm text-slate-500">
                            <li><a href="#" class="hover:text-slate-900 transition">About</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Blog</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Careers</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Privacy</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-sm text-slate-900 mb-4">Developers</h4>
                        <ul class="space-y-3 text-sm text-slate-500">
                            <li><a href="#" class="hover:text-slate-900 transition">API</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">GitHub</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Changelog</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Security</a></li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="font-semibold text-sm text-slate-900 mb-4">Legal</h4>
                        <ul class="space-y-3 text-sm text-slate-500">
                            <li><a href="#" class="hover:text-slate-900 transition">Terms</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Privacy Policy</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Cookie Policy</a></li>
                            <li><a href="#" class="hover:text-slate-900 transition">Licenses</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
<?php /**PATH C:\Users\Admin\Desktop\uni-management-system\resources\views/landing.blade.php ENDPATH**/ ?>