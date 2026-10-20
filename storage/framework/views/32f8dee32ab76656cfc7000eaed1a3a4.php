<?php $__env->startSection('title', 'Create account'); ?>
<?php $__env->startSection('content'); ?>
<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-9">
    <div class="mb-7">
        <p class="text-sm font-semibold text-indigo-700">Join the academic network</p>
        <h2 class="mt-2 text-3xl font-black text-slate-950">Create your AcadSyntra account</h2>
        <p class="mt-2 text-slate-600">Choose the path that fits your academic work. Start independently and connect an institution later if it becomes relevant.</p>
    </div>

    <form method="POST" action="<?php echo e(route('store-register')); ?>" class="space-y-5" data-loading-form>
        <?php echo csrf_field(); ?>
        <div>
            <label for="account_type" class="mb-2 block text-sm font-semibold text-slate-800">I am joining as</label>
            <select id="account_type" aria-invalid="<?php echo e($errors->has('account_type') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['account_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="account_type-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="account_type" required class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">Select your path</option>
                <?php $__currentLoopData = \App\Services\OnboardingService::PUBLIC_ACCOUNT_PATHS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($value); ?>" <?php if(old('account_type') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php $__errorArgs = ['account_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="account_type-error" class="mt-2 text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <p class="mt-2 text-xs leading-5 text-slate-500">Representing a university, faculty, department or organisation? Institutional onboarding is handled separately so individual users are never blocked from starting.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="first_name" class="mb-2 block text-sm font-semibold text-slate-800">First name</label>
                <input id="first_name" aria-invalid="<?php echo e($errors->has('first_name') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="first_name-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="first_name" value="<?php echo e(old('first_name')); ?>" required autocomplete="given-name" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label for="last_name" class="mb-2 block text-sm font-semibold text-slate-800">Last name</label>
                <input id="last_name" aria-invalid="<?php echo e($errors->has('last_name') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="last_name-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="last_name" value="<?php echo e(old('last_name')); ?>" required autocomplete="family-name" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
        </div>

        <div>
            <label for="username" class="mb-2 block text-sm font-semibold text-slate-800">Username</label>
            <input id="username" aria-invalid="<?php echo e($errors->has('username') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['username'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="username-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="username" value="<?php echo e(old('username')); ?>" required autocomplete="username" placeholder="letters, numbers, dashes or underscores" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            <?php $__errorArgs = ['username'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="username-error" class="mt-2 text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" aria-invalid="<?php echo e($errors->has('email') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="email-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="email" type="email" value="<?php echo e(old('email')); ?>" required autocomplete="email" class="w-full rounded-2xl border-slate-300 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="email-error" class="mt-2 text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="password" class="mb-2 block text-sm font-semibold text-slate-800">Password</label>
                <div class="relative">
                    <input id="password" aria-invalid="<?php echo e($errors->has('password') ? 'true' : 'false'); ?>" <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-describedby="password-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> name="password" type="password" required minlength="<?php echo e($passwordPolicy['min_length']); ?>" autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="button" data-password-toggle="password" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
                </div>
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800">Confirm password</label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="w-full rounded-2xl border-slate-300 px-4 py-3 pr-16 focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="button" data-password-toggle="password_confirmation" class="absolute inset-y-0 right-3 text-sm font-semibold text-slate-500">Show</button>
                </div>
            </div>
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

        <?php $__errorArgs = ['first_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="first_name-error" class="text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php $__errorArgs = ['last_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="last_name-error" class="text-sm text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <label class="flex items-start gap-3 text-sm text-slate-600">
            <input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-slate-300 text-indigo-700 focus:ring-indigo-500">
            <span>I agree to use AcadSyntra responsibly, respect privacy, and follow academic-integrity and community rules.</span>
        </label>

        <button type="submit" class="w-full rounded-2xl bg-indigo-600 px-5 py-3.5 font-bold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-70">Create account and continue</button>
    </form>

    <p class="mt-7 text-center text-sm text-slate-600">Already registered? <a href="<?php echo e(route('login')); ?>" class="font-bold text-indigo-700 hover:text-indigo-900">Sign in</a></p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/auth/register.blade.php ENDPATH**/ ?>