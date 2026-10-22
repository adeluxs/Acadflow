<?php $__env->startSection('title', $featureStatus === 'maintenance' ? 'Feature Maintenance' : 'Feature Unavailable'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto flex min-h-[64vh] max-w-4xl items-center justify-center px-4 py-10">
    <div class="w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
        <div class="relative overflow-hidden px-6 py-10 sm:px-10">
            <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-indigo-100/70 blur-2xl"></div>
            <div class="absolute -bottom-20 -left-10 h-52 w-52 rounded-full bg-violet-100/70 blur-2xl"></div>
            <div class="relative">
                <div class="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100">
                    <?php if($featureStatus === 'maintenance'): ?>
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 4h2m-1-1v2m6.4 2.6-1.4 1.4M19 12h2M5 12H3m3.6-4.4L5.2 6.2M8 21h8m-7-4h6a3 3 0 003-3v-1a6 6 0 10-12 0v1a3 3 0 003 3z"/></svg>
                    <?php else: ?>
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?php endif; ?>
                </div>

                <div class="mb-3 inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-600">
                    <?php echo e($featureStatus === 'maintenance' ? 'Temporarily under maintenance' : 'Currently unavailable'); ?>

                </div>
                <h1 class="max-w-2xl text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl"><?php echo e($featureTitle); ?></h1>
                <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600"><?php echo e($featureMessage); ?></p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(route('dashboard')); ?>" class="acad-primary-button inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold">Return to dashboard</a>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>" class="acad-primary-button inline-flex items-center rounded-xl px-5 py-3 text-sm font-semibold">Log in</a>
                    <?php endif; ?>
                    <a href="<?php echo e(route('public.page', 'status')); ?>" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Platform status</a>
                </div>

                <p class="mt-8 text-xs text-slate-400">Feature reference: <?php echo e($feature); ?></p>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/errors/feature-unavailable.blade.php ENDPATH**/ ?>