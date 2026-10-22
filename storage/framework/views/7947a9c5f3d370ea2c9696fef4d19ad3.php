<?php $__env->startSection('title', 'Invoice Details'); ?>

<?php $__env->startSection('content'); ?>
<?php ($billingCurrency = strtoupper((string) \App\Services\SettingService::get('currency', 'NGN', auth()->user()?->university_id))); ?>
<div class="container mx-auto max-w-4xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Invoice <?php echo e($invoice->uuid); ?></h1>
            <p class="mt-1 text-sm text-gray-600">Institutional billing record for <?php echo e($invoice->semester?->name ?? 'the selected semester'); ?>.</p>
        </div>
        <a href="<?php echo e(route('billing.my')); ?>" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Back to invoices</a>
    </div>

    <?php if($errors->any()): ?>
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="rounded-lg bg-white p-6 shadow">
        <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-gray-500">Amount</dt>
                <dd class="mt-1 text-2xl font-bold text-gray-900"><?php echo e($invoice->currency ?: $billingCurrency); ?> <?php echo e(\App\Support\Money::fromMinor($invoice->amount_minor !== null ? (int) $invoice->amount_minor : \App\Support\Money::toMinor((string) $invoice->amount))); ?></dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500">Status</dt>
                <dd class="mt-1 font-semibold text-gray-900"><?php echo e(ucfirst($invoice->status)); ?></dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500">Due date</dt>
                <dd class="mt-1 text-gray-900"><?php echo e($invoice->due_date?->format('Y-m-d') ?? '—'); ?></dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500">Paid at</dt>
                <dd class="mt-1 text-gray-900"><?php echo e($invoice->paid_at?->format('Y-m-d H:i') ?? '—'); ?></dd>
            </div>
        </dl>

        <?php if($invoice->status === 'pending'): ?>
            <div class="mt-8 border-t pt-6">
                <h2 class="text-lg font-bold text-gray-900">Submit bank-transfer reference</h2>
                <p class="mt-1 text-sm text-gray-600">This records proof for administrator verification; it does not simulate a card or wallet payment.</p>

                <form method="POST" action="<?php echo e(route('billing.pay', $invoice)); ?>" class="mt-5 space-y-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="payment_method" value="bank_transfer">
                    <div>
                        <label for="transaction_ref" class="block text-sm font-medium text-gray-700">Bank transaction reference</label>
                        <input id="transaction_ref" name="transaction_ref" value="<?php echo e(old('transaction_ref')); ?>" maxlength="100" required
                               class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                               placeholder="Enter the reference supplied by your bank">
                    </div>
                    <button type="submit" class="rounded-lg bg-green-600 px-5 py-2.5 font-semibold text-white hover:bg-green-700">
                        Submit for verification
                    </button>
                </form>
            </div>
        <?php elseif($invoice->status === 'paid'): ?>
            <div class="mt-8 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">This invoice has been verified as paid.</div>
        <?php elseif($invoice->status === 'waived'): ?>
            <div class="mt-8 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">This invoice was waived by an authorized administrator.</div>
        <?php endif; ?>

        <?php if($invoice->payments->isNotEmpty()): ?>
            <div class="mt-8 border-t pt-6">
                <h2 class="text-lg font-bold text-gray-900">Payment submissions</h2>
                <div class="mt-3 space-y-3">
                    <?php $__currentLoopData = $invoice->payments->sortByDesc('created_at'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="rounded-lg border p-4 text-sm">
                            <div class="flex flex-wrap justify-between gap-2">
                                <span class="font-medium text-gray-900"><?php echo e($payment->transaction_ref); ?></span>
                                <span class="font-semibold text-gray-700"><?php echo e(ucfirst($payment->status)); ?></span>
                            </div>
                            <p class="mt-1 text-gray-500">Submitted <?php echo e($payment->created_at?->format('Y-m-d H:i')); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/billing/show.blade.php ENDPATH**/ ?>