<?php $__env->startSection('title', 'Sign in'); ?>
<?php $__env->startSection('content'); ?>
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <div class="mb-8">
        <p class="text-sm font-semibold text-blue-700">Welcome back</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Continue your academic work</h2>
        <p class="mt-2 text-slate-600">Access your research, publications, communities, groups, events and learning activity.</p>
    </div>

    <form method="POST" action="<?php echo e(route('login.store')); ?>" class="space-y-5" data-loading-form>
        <?php echo csrf_field(); ?>
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" value="<?php echo e(old('email')); ?>" required autofocus
                   class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-blue-600 focus:ring-blue-600 <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <label for="password" class="text-sm font-semibold text-slate-800">Password</label>
                <a href="<?php echo e(route('password.request')); ?>" class="text-sm font-semibold text-blue-700 hover:text-blue-900">Forgot password?</a>
            </div>
            <div class="relative">
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-blue-600 focus:ring-blue-600 <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> border-rose-400 <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                <button type="button" data-password-toggle="password" aria-pressed="false" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
            </div>
            <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <label class="flex items-center gap-3 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" <?php if(old('remember')): echo 'checked'; endif; ?> class="rounded border-slate-300 text-blue-700 focus:ring-blue-600">
            Keep me signed in on this device
        </label>

        <button type="submit" class="w-full rounded-2xl bg-blue-700 px-5 py-3.5 font-bold text-white shadow-lg shadow-blue-700/20 transition hover:bg-blue-800 disabled:cursor-wait disabled:opacity-70">Sign in to AcadFlow</button>
    </form>

    <p class="mt-7 text-center text-sm text-slate-600">New to AcadFlow? <a href="<?php echo e(route('register')); ?>" class="font-bold text-blue-700 hover:text-blue-900">Create your account</a></p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/auth/login.blade.php ENDPATH**/ ?>