<?php $__env->startSection('title', 'Choose new password'); ?>
<?php $__env->startSection('content'); ?>
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <p class="text-sm font-semibold text-indigo-700">Secure your account</p>
    <h2 class="mt-2 text-3xl font-black text-slate-950">Choose a new password</h2>
    <form method="POST" action="<?php echo e(route('password.store')); ?>" class="mt-7 space-y-5" data-loading-form>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo e($request->route('token')); ?>">
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" aria-invalid="<?php echo e($errors->has('email') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="email-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="email" type="email" value="<?php echo e(old('email', $request->email)); ?>" required autocomplete="email" class="w-full rounded-2xl border-slate-300 px-4 py-3">
        </div>
        <div>
            <label for="password" class="mb-2 block text-sm font-semibold text-slate-800">New password</label>
            <div class="relative"><input id="password" aria-invalid="<?php echo e($errors->has('password') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="password-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="password" type="password" required minlength="<?php echo e($passwordPolicy['min_length']); ?>" autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16"><button type="button" data-password-toggle="password" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button></div>
        </div>
        <div>
            <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800">Confirm password</label>
            <div class="relative"><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16"><button type="button" data-password-toggle="password_confirmation" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button></div>
        </div>
        <?php echo $__env->make('auth.partials.password-policy', [
            'passwordPolicy' => $passwordPolicy,
            'passwordInputId' => 'password',
            'confirmationInputId' => 'password_confirmation',
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="password-error" class="text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <button class="w-full rounded-2xl bg-indigo-600 px-5 py-3.5 font-bold text-white hover:bg-indigo-700">Update password</button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/auth/reset-password.blade.php ENDPATH**/ ?>