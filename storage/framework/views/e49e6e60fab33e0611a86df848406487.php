<?php $__env->startSection('title', 'Reset password'); ?>
<?php $__env->startSection('content'); ?>
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <p class="text-sm font-semibold text-indigo-700">Account recovery</p>
    <h2 class="mt-2 text-3xl font-black text-slate-950">Reset your password</h2>
    <p class="mt-2 text-slate-600">Enter the email linked to your account. We will send a time-limited reset link when the account exists.</p>
    <form method="POST" action="<?php echo e(route('password.email')); ?>" class="mt-7 space-y-5" data-loading-form>
        <?php echo csrf_field(); ?>
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" aria-invalid="<?php echo e($errors->has('email') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="email-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="email" type="email" value="<?php echo e(old('email')); ?>" required autofocus autocomplete="email" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="email-error" class="mt-2 text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <button class="w-full rounded-2xl bg-indigo-600 px-5 py-3.5 font-bold text-white hover:bg-indigo-700">Send reset link</button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="<?php echo e(route('login')); ?>" class="font-bold text-indigo-700">Back to sign in</a></p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/auth/forgot-password.blade.php ENDPATH**/ ?>