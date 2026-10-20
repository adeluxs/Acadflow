<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Set up your workspace'); ?> · <?php echo e($siteName); ?></title>
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo e(asset('branding/acadflow-icon-192.png')); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo e(asset('branding/acadflow-icon-180.png')); ?>">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<?php echo $__env->make('partials.global-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="<?php echo e(route('home')); ?>" class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-white p-1 shadow-sm ring-1 ring-slate-200"><img src="<?php echo e(asset('branding/acadflow-mark.png')); ?>" alt="" class="h-full w-full object-contain"></span>
            <span><strong class="block text-lg"><?php echo e($siteName); ?></strong><span class="block text-xs text-slate-500">Personal workspace setup</span></span>
        </a>
        <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Sign out</button></form>
    </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <?php if(session('success')): ?><div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?php echo e(session('success')); ?></div><?php endif; ?>
    <?php if(session('info')): ?><div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"><?php echo e(session('info')); ?></div><?php endif; ?>
    <?php if($errors->any()): ?><div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><p class="font-semibold">Please correct the following:</p><ul class="mt-2 list-disc pl-5"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
    <?php echo $__env->yieldContent('content'); ?>
</main>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/layouts/onboarding.blade.php ENDPATH**/ ?>