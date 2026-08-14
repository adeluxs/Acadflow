<?php
    $siteName = \App\Services\SettingService::get('site_name', 'AcadFlow');
    $primaryColor = (string) \App\Services\SettingService::get('primary_color', '#4f46e5');
    if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) $primaryColor = '#4f46e5';
    [$primaryR, $primaryG, $primaryB] = sscanf($primaryColor, '#%02x%02x%02x');
    $registerUrl = \Illuminate\Support\Facades\Route::has('register') ? route('register') : route('login');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="theme-color" content="<?php echo e($primaryColor); ?>">
    <title><?php echo e($siteName); ?> — Academic Workflow Platform</title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <style>
        :root{--acad-primary:<?php echo e($primaryColor); ?>;--acad-primary-rgb:<?php echo e($primaryR); ?> <?php echo e($primaryG); ?> <?php echo e($primaryB); ?>;}
        .landing-glow{background:radial-gradient(circle at 78% 24%,rgb(var(--acad-primary-rgb)/.15),transparent 27%),radial-gradient(circle at 16% 35%,rgb(99 102 241/.08),transparent 27%),linear-gradient(180deg,#fff 0%,#fafbff 100%)}
        .hero-title-accent{color:var(--acad-primary)}
        .mini-scrollbar::-webkit-scrollbar{width:4px}.mini-scrollbar::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:99px}
    </style>
</head>
<body class="landing-glow min-h-screen text-slate-950 antialiased">
    <header class="relative z-20">
        <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 lg:px-8" aria-label="Main navigation">
            <a href="<?php echo e(url('/')); ?>" class="flex items-center gap-3" aria-label="<?php echo e($siteName); ?> home">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl text-white shadow-sm" style="background:var(--acad-primary)">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M2.5 9L12 4l9.5 5L12 14 2.5 9zM6 11.2V16c3.7 2.5 8.3 2.5 12 0v-4.8M21.5 9v6"/></svg>
                </span>
                <span class="text-2xl font-black tracking-tight">Acad<span class="hero-title-accent">Flow</span></span>
            </a>

            <div class="hidden items-center gap-9 text-sm font-medium text-slate-800 lg:flex">
                <a href="#features" class="hover:text-indigo-600">Features</a>
                <a href="#solutions" class="inline-flex items-center gap-1 hover:text-indigo-600">Solutions <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M7 10l5 5 5-5"/></svg></a>
                <a href="#pricing" class="hover:text-indigo-600">Pricing</a>
                <a href="#resources" class="inline-flex items-center gap-1 hover:text-indigo-600">Resources <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M7 10l5 5 5-5"/></svg></a>
                <a href="#about" class="hover:text-indigo-600">About</a>
            </div>

            <div class="flex items-center gap-3">
                <?php if(auth()->guard()->check()): ?>
                    <a href="<?php echo e(route('dashboard')); ?>" class="hidden px-3 py-2 text-sm font-semibold text-slate-800 sm:inline-flex">Dashboard</a>
                    <a href="<?php echo e(route('dashboard')); ?>" class="acad-primary-button !rounded-lg !px-6">Open AcadFlow</a>
                <?php else: ?>
                    <a href="<?php echo e(route('login')); ?>" class="hidden px-3 py-2 text-sm font-semibold text-slate-800 sm:inline-flex">Log in</a>
                    <a href="<?php echo e($registerUrl); ?>" class="acad-primary-button !rounded-lg !px-6">Get Started</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>
        <section class="mx-auto grid max-w-7xl gap-12 px-5 pb-8 pt-7 lg:grid-cols-[.88fr_1.2fr] lg:items-center lg:px-8 lg:pb-4 lg:pt-7">
            <div class="relative z-10 pb-3">
                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50/70 px-3.5 py-2 text-xs font-semibold text-indigo-700">
                    <span class="text-base leading-none">✦</span> AI-Powered Academic Workflow Platform
                </div>
                <h1 class="mt-7 max-w-xl text-5xl font-black leading-[1.08] tracking-[-0.045em] text-slate-950 sm:text-6xl lg:text-[64px]">
                    Manage. Teach.<br>Learn. Grow.<br><span class="hero-title-accent">All in One Platform.</span>
                </h1>
                <p class="mt-6 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">
                    AcadFlow helps universities, polytechnics, lecturers, students, and independent academic members streamline learning workflows with AI assistance, automation, collaboration, and real-time insights.
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="<?php echo e($registerUrl); ?>" class="acad-primary-button !rounded-xl !px-6 !py-3.5">Get Started Free <span class="ml-2">→</span></a>
                    <a href="#solutions" class="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-white px-6 py-3.5 text-sm font-bold text-indigo-700 shadow-sm hover:bg-indigo-50">Book a Demo <span class="ml-3 text-[10px]">▶</span></a>
                </div>
                <div class="mt-7 flex items-center gap-4">
                    <div class="flex -space-x-2">
                        <?php $__currentLoopData = ['IB','AM','TK','SA','DM']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $avatar): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-slate-800 text-[9px] font-bold text-white"><?php echo e($avatar); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <div><div class="text-sm tracking-[.12em] text-amber-400">★★★★★</div><p class="mt-1 max-w-[170px] text-xs leading-4 text-slate-600">Built for academic communities across Africa</p></div>
                </div>
            </div>

            <div class="relative lg:-mr-8">
                <div class="absolute -right-10 top-4 h-64 w-64 rounded-full bg-indigo-200/30 blur-3xl"></div>
                <div class="relative overflow-hidden rounded-[22px] border border-indigo-100 bg-white shadow-2xl shadow-indigo-100/70">
                    <div class="grid min-h-[535px] grid-cols-[158px_1fr] sm:grid-cols-[178px_1fr]">
                        <aside class="border-r border-slate-100 bg-white p-4">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background:var(--acad-primary)"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-width="1.8" d="M2.5 9L12 4l9.5 5L12 14 2.5 9zM6 11v5c4 2.4 8 2.4 12 0v-5"/></svg></span>
                                <span class="text-sm font-black">Acad<span class="hero-title-accent">Flow</span></span>
                            </div>
                            <div class="mt-4 space-y-1 text-[10px] font-semibold text-slate-600">
                                <?php $__currentLoopData = [['Dashboard','⌂'],['Courses','▯'],['Assignments','▤'],['Attendance','▣'],['Discussions','▢'],['AI Assistant','✦'],['Gradebook','▦'],['Reports','⌁'],['Billing','▣'],['Settings','⚙']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i=>$item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="flex items-center gap-2 rounded-lg px-2.5 py-2.5 <?php echo e($i===0 ? 'bg-indigo-50 text-indigo-700' : ''); ?>"><span class="w-4 text-center"><?php echo e($item[1]); ?></span><span><?php echo e($item[0]); ?></span><?php if($item[0]==='AI Assistant'): ?><span class="ml-auto rounded-full bg-indigo-100 px-1.5 py-0.5 text-[8px] text-indigo-700">New</span><?php endif; ?></div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <div class="mt-16 rounded-xl bg-slate-50 p-2.5"><div class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-800 text-[8px] font-bold text-white">IM</span><div class="min-w-0"><p class="truncate text-[9px] font-bold">Dr. Ibrahim M.</p><p class="text-[8px] text-slate-500">Lecturer</p></div></div></div>
                        </aside>

                        <div class="mini-scrollbar overflow-hidden bg-white p-5">
                            <div class="flex items-center justify-between gap-3">
                                <div><p class="text-lg font-black">Dashboard</p><p class="mt-0.5 text-[9px] text-slate-500">Welcome back, Dr. Ibrahim 👋</p></div>
                                <div class="flex items-center gap-2"><span class="hidden rounded-lg border border-slate-200 px-2.5 py-2 text-[8px] font-semibold text-slate-600 sm:inline">▣ 2025/2026 Semester</span><span class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-[10px]">♧</span></div>
                            </div>
                            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <?php $__currentLoopData = [['My Courses','8','Active courses','bg-indigo-50 text-indigo-600'],['My Students','248','Total students','bg-emerald-50 text-emerald-600'],['Pending Reviews','32','Submissions','bg-orange-50 text-orange-600'],['Attendance Today','92%','Average rate','bg-blue-50 text-blue-600']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="rounded-xl border border-slate-100 bg-white p-3 shadow-sm"><div class="flex items-start justify-between"><p class="text-[8px] text-slate-500"><?php echo e($stat[0]); ?></p><span class="flex h-6 w-6 items-center justify-center rounded-md <?php echo e($stat[3]); ?> text-[9px]">◈</span></div><p class="mt-3 text-xl font-black"><?php echo e($stat[1]); ?></p><p class="mt-1 text-[8px] text-slate-500"><?php echo e($stat[2]); ?></p></div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-[1.15fr_.85fr]">
                                <div class="rounded-xl border border-slate-100 p-4 shadow-sm"><div class="flex items-center justify-between"><p class="text-[10px] font-black">Recent Submissions</p><span class="text-[8px] font-bold text-indigo-600">View all</span></div><div class="mt-3 space-y-2"><?php $__currentLoopData = [['Data Structures Assignment','CSC301 · 15 submissions','Pending','bg-amber-50 text-amber-600'],['SIWES Report - Week 8','IND401 · 23 submissions','In Review','bg-blue-50 text-blue-600'],['Final Year Project Chapter 3','CSC499 · 8 submissions','Completed','bg-emerald-50 text-emerald-600']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="flex items-center gap-2 rounded-lg border border-slate-100 p-2.5"><span class="flex h-7 w-7 items-center justify-center rounded-md bg-indigo-50 text-indigo-600 text-[9px]">▤</span><div class="min-w-0 flex-1"><p class="truncate text-[9px] font-bold"><?php echo e($row[0]); ?></p><p class="text-[8px] text-slate-400"><?php echo e($row[1]); ?></p></div><span class="rounded-full px-2 py-1 text-[7px] font-semibold <?php echo e($row[3]); ?>"><?php echo e($row[2]); ?></span></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div>
                                <div class="relative overflow-hidden rounded-xl border border-indigo-100 bg-gradient-to-br from-white to-indigo-50 p-4 shadow-sm"><div class="absolute -right-3 -top-4 h-16 w-16 rounded-full bg-indigo-200/50 blur-xl"></div><div class="relative"><div class="flex items-center justify-between"><div><p class="text-[10px] font-black">✦ AI Assistant</p><p class="mt-1 text-[8px] text-slate-500">How can I help you today?</p></div><span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-950 text-[9px] font-bold text-white">AI</span></div><div class="mt-3 space-y-1.5"><?php $__currentLoopData = ['Review a submission','Check plagiarism','Generate feedback','Analyze document']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tool): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="rounded-md bg-white px-2.5 py-2 text-[8px] font-medium shadow-sm"><?php echo e($tool); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><div class="mt-3 rounded-md bg-indigo-600 px-3 py-2 text-center text-[8px] font-bold text-white">Start with AI Assistant ↗</div></div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="features" class="relative -mt-1 grid grid-cols-3 gap-px overflow-hidden rounded-b-[22px] border border-indigo-100 bg-slate-100 shadow-xl shadow-indigo-100/40 sm:grid-cols-6">
                    <?php $__currentLoopData = [['✦','AI Academic','Assistant','bg-violet-50 text-violet-600'],['▤','Smart Assignment','Management','bg-emerald-50 text-emerald-600'],['▦','Attendance','Tracking','bg-blue-50 text-blue-600'],['⌕','Plagiarism','Detection','bg-orange-50 text-orange-600'],['⌁','Analytics &','Reports','bg-violet-50 text-violet-600'],['▣','Knowledge','Hub','bg-emerald-50 text-emerald-600']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="bg-white px-2 py-5 text-center"><span class="mx-auto flex h-8 w-8 items-center justify-center rounded-lg <?php echo e($feature[3]); ?> text-xs"><?php echo e($feature[0]); ?></span><p class="mt-2 text-[9px] font-semibold leading-3 text-slate-800"><?php echo e($feature[1]); ?><br><?php echo e($feature[2]); ?></p></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </section>

        <section id="solutions" class="mx-auto max-w-6xl px-5 py-6 lg:px-8">
            <div class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-lg shadow-indigo-50/70">
                <h2 class="text-center text-xl font-black tracking-tight text-slate-950 sm:text-2xl">Built for the Entire <span class="hero-title-accent">Academic Community</span></h2>
                <div class="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <?php $__currentLoopData = [
                        ['Super Admin','Manage institutions, users, subscriptions, policies, and system settings.','shield'],
                        ['Lecturers','Create courses, manage assignments, grade, enroll students, and teach.','user'],
                        ['Students','Access courses, submit assignments, track progress, and collaborate.','cap'],
                        ['Members','Research, publish knowledge, join communities, events, and groups.','building'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <article class="flex gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:shadow-sm"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><?php if($role[2]==='cap'): ?>◈<?php elseif($role[2]==='user'): ?>♙<?php elseif($role[2]==='building'): ?>▥<?php else: ?>◇<?php endif; ?></span><div><p class="text-xs font-black"><?php echo e($role[0]); ?></p><p class="mt-1 text-[10px] leading-4 text-slate-500"><?php echo e($role[1]); ?></p></div><span class="ml-auto text-slate-400">→</span></article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </section>

        <section id="resources" class="mx-auto max-w-6xl px-5 pb-12 pt-5 text-center lg:px-8">
            <p class="text-[10px] font-semibold uppercase tracking-[.22em] text-slate-400">Designed for Nigerian higher education</p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-x-9 gap-y-3 text-sm font-black text-slate-700">
                <span>Federal Universities</span><span class="text-slate-300">•</span><span>State Universities</span><span class="text-slate-300">•</span><span>Private Universities</span><span class="text-slate-300">•</span><span>Polytechnics</span><span class="text-slate-300">•</span><span>Independent Researchers</span>
            </div>
        </section>

        <section id="pricing" class="sr-only" aria-label="Pricing">Flexible institutional and member plans are managed from AcadFlow settings.</section>
        <section id="about" class="sr-only" aria-label="About AcadFlow">AcadFlow is an academic workflow platform for learning, research, knowledge, and institutional operations.</section>
    </main>
</body>
</html>
<?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/landing.blade.php ENDPATH**/ ?>