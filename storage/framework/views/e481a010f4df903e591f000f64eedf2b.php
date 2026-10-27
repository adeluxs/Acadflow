<?php $__env->startSection('title', 'Pay for promotion'); ?>
<?php $__env->startSection('page-title', 'Promotion checkout'); ?>
<?php $__env->startSection('page-subtitle', 'Review the campaign before payment'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-2xl space-y-6">
    <?php if(session('success')): ?><div class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900"><?php echo e(session('success')); ?></div><?php endif; ?>
    <section class="rounded-3xl border bg-white p-7 shadow-sm">
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black uppercase text-amber-800">Sponsored campaign</span>
        <h1 class="mt-4 text-2xl font-black"><?php echo e($campaign->publication?->title); ?></h1>
        <p class="mt-2 text-sm text-slate-500"><?php echo e($campaign->package?->name); ?> · <?php echo e($campaign->package?->duration_days); ?> days after verified payment</p>
        <p class="mt-5 text-3xl font-black"><?php echo e(config('app.currency_symbol', '₦')); ?><?php echo e(number_format((float)$campaign->price, 2)); ?></p>
        <form method="POST" action="<?php echo e(route('commerce.purchase-promotion', $campaign)); ?>" class="mt-6 grid gap-4">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="idempotency_key" value="<?php echo e((string) \Illuminate\Support\Str::uuid()); ?>">
            <label class="text-sm font-bold">Payment method<select name="payment_method" class="mt-2 w-full rounded-xl border-slate-300"><option value="wallet">Wallet balance</option><option value="gateway">Payment gateway</option></select></label>
            <label class="text-sm font-bold">Gateway<select name="gateway" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Select for gateway payment</option><?php $__currentLoopData = $checkoutGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($gateway->code); ?>"><?php echo e($gateway->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <button class="rounded-xl bg-indigo-700 px-5 py-3 font-black text-white">Pay and activate</button>
        </form>
        <p class="mt-4 text-xs leading-5 text-slate-500">Payment buys labelled placement and campaign reporting. It does not influence moderation, search credibility, peer-review status or academic labels.</p>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/promotions/checkout.blade.php ENDPATH**/ ?>