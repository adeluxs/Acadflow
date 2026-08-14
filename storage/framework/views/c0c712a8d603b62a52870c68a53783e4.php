<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Welcome'); ?> · <?php echo e(\App\Services\SettingService::get('site_name', 'AcadFlow')); ?></title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
<div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_.95fr]">
    <section class="relative hidden overflow-hidden lg:flex lg:min-h-screen lg:flex-col lg:justify-between bg-gradient-to-br from-blue-700 via-indigo-800 to-slate-950 px-12 py-10 text-white">
        <div class="absolute inset-0 opacity-20" aria-hidden="true">
            <div class="absolute -left-24 top-20 h-80 w-80 rounded-full bg-cyan-300 blur-3xl"></div>
            <div class="absolute bottom-10 right-0 h-96 w-96 rounded-full bg-violet-400 blur-3xl"></div>
        </div>
        <div class="relative">
            <a href="<?php echo e(route('home')); ?>" class="inline-flex items-center gap-3">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white/15 text-xl font-black ring-1 ring-white/20">A</span>
                <span>
                    <strong class="block text-2xl"><?php echo e(\App\Services\SettingService::get('site_name', 'AcadFlow')); ?></strong>
                    <span class="text-sm text-blue-100">Learn. Share. Build Reputation. Earn.</span>
                </span>
            </a>
        </div>

        <div class="relative max-w-xl">
            <p class="mb-4 text-sm font-semibold uppercase tracking-[.24em] text-cyan-200">Academic knowledge infrastructure</p>
            <h1 class="text-5xl font-black leading-tight">Research, publish, collaborate and grow in one trusted academic network.</h1>
            <p class="mt-6 text-lg leading-8 text-blue-100">AcadFlow connects formal research workflows with publishing, communities, learning paths, events, challenges and professional profiles.</p>

            <div class="mt-10 grid grid-cols-2 gap-4 text-sm">
                <?php $__currentLoopData = [
                    ['Research Studio', 'Supervision, writing, validation and approvals'],
                    ['Knowledge Hub', 'Publish and discover academic work'],
                    ['Communities & groups', 'Build public networks and focused teams'],
                    ['Events & challenges', 'Learn, participate, judge and earn recognition'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$heading, $copy]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                        <strong class="block"><?php echo e($heading); ?></strong>
                        <span class="mt-1 block text-blue-100"><?php echo e($copy); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        <p class="relative text-sm text-blue-200">For students, lecturers, researchers, institutions, publishers and independent professionals.</p>
    </section>

    <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 sm:px-8">
        <div class="w-full max-w-xl">
            <a href="<?php echo e(route('home')); ?>" class="mb-8 inline-flex items-center gap-3 lg:hidden">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-blue-700 text-white font-black">A</span>
                <span class="text-xl font-bold text-slate-950"><?php echo e(\App\Services\SettingService::get('site_name', 'AcadFlow')); ?></span>
            </a>

            <?php if(session('status')): ?>
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?php echo e(session('status')); ?></div>
            <?php endif; ?>
            <?php if(session('success')): ?>
                <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?php echo e(session('success')); ?></div>
            <?php endif; ?>
            <?php if(session('error')): ?>
                <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?php echo e(session('error')); ?></div>
            <?php endif; ?>
            <?php if($errors->any()): ?>
                <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    <p class="font-semibold">Please correct the highlighted information.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
                </div>
            <?php endif; ?>

            <?php echo $__env->yieldContent('content'); ?>

            <p class="mt-8 text-center text-xs text-slate-500">By continuing, you agree to follow your institution’s policies and AcadFlow’s academic-integrity rules.</p>
        </div>
    </main>
</div>
<script>
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        button.textContent = input.type === 'password' ? 'Show' : 'Hide';
        button.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
    });

    document.querySelectorAll('form[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = 'Please wait…';
        });
    });
</script>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/layouts/auth.blade.php ENDPATH**/ ?>