<?php $__env->startSection('title','Account Security'); ?>
<?php $__env->startSection('page-title','Account Security'); ?>
<?php $__env->startSection('page-subtitle','Password-protected authenticator setup, recovery codes and session safeguards'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl space-y-6">
    <section class="rounded-2xl border bg-white p-6">
        <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-bold">Authenticator app</h2><p class="mt-1 text-sm text-slate-600">Use a time-based one-time password app. Codes rotate every 30 seconds.</p></div><span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo e($user->two_factor_secret?'bg-green-100 text-green-700':'bg-slate-100 text-slate-600'); ?>"><?php echo e($user->two_factor_secret?'Enabled':'Not enabled'); ?></span></div>
        <?php if(!$user->two_factor_secret && !$twoFactorAvailable): ?>
            <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Two-factor setup is currently disabled by your institution settings.</div>
        <?php elseif(!$user->two_factor_secret && !$pendingSecret): ?>
            <form method="POST" action="<?php echo e(route('security.two-factor.begin')); ?>" class="mt-5"><?php echo csrf_field(); ?><button class="acad-primary-button">Start authenticator setup</button></form>
        <?php elseif(!$user->two_factor_secret): ?>
            <div class="mt-5 rounded-xl border bg-slate-50 p-4"><p class="font-semibold">Manual setup key</p><code class="mt-2 block break-all rounded bg-white p-3 text-sm"><?php echo e($pendingSecret); ?></code><p class="mt-3 text-xs text-slate-500">Provisioning URI for compatible authenticator apps:</p><code class="mt-1 block break-all rounded bg-white p-3 text-xs"><?php echo e($provisioningUri); ?></code></div>
            <form method="POST" action="<?php echo e(route('security.two-factor.confirm')); ?>" class="mt-5 grid gap-3 sm:grid-cols-2"><?php echo csrf_field(); ?><input name="code" inputmode="numeric" maxlength="6" required placeholder="Six-digit code" class="rounded-xl border-slate-300"><input type="password" name="password" required autocomplete="current-password" placeholder="Current password" class="rounded-xl border-slate-300"><button class="acad-primary-button sm:col-span-2">Confirm and enable</button></form>
        <?php else: ?>
            <div class="mt-5 grid gap-4 md:grid-cols-2"><form method="POST" action="<?php echo e(route('security.two-factor.recovery')); ?>" class="space-y-3 rounded-xl border p-4"><?php echo csrf_field(); ?><input type="password" name="password" required autocomplete="current-password" placeholder="Current password" class="w-full rounded-xl border-slate-300"><button class="w-full rounded-xl border px-4 py-2 font-semibold">Regenerate recovery codes</button></form><form method="POST" action="<?php echo e(route('security.two-factor.disable')); ?>" class="space-y-3 rounded-xl border border-red-200 p-4"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><input type="password" name="password" required autocomplete="current-password" placeholder="Current password" class="w-full rounded-xl border-slate-300"><button class="w-full rounded-xl bg-red-600 px-4 py-2 font-semibold text-white">Disable two-factor authentication</button></form></div>
        <?php endif; ?>
    </section>
    <?php if($recoveryCodes): ?>
    <section class="rounded-2xl border border-amber-300 bg-amber-50 p-6"><h2 class="font-bold text-amber-900">Save these recovery codes now</h2><p class="mt-1 text-sm text-amber-800">Each code works once. They will not be displayed again.</p><div class="mt-4 grid gap-2 sm:grid-cols-2"><?php $__currentLoopData = $recoveryCodes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $code): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><code class="rounded bg-white p-3 text-center"><?php echo e($code); ?></code><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></section>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/account/security.blade.php ENDPATH**/ ?>