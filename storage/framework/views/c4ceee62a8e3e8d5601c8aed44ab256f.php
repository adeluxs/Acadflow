<?php $__env->startSection('title', 'Billing Management'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Billing Management</h1>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__empty_0 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <tr>
                        <td class="px-6 py-4"><?php echo e($invoice->uuid); ?></td>
                        <td class="px-6 py-4"><?php echo e($invoice->user->email); ?></td>
                        <td class="px-6 py-4">$<?php echo e(number_format($invoice->amount, 2)); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo e($invoice->status === 'paid' ? 'bg-green-100 text-green-800' : 
                                   ($invoice->status === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800')); ?>">
                                <?php echo e(ucfirst($invoice->status)); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if($invoice->status === 'pending'): ?>
                                <form method="POST" action="<?php echo e(route('billing.verify', $invoice)); ?>" class="inline">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="text-green-600 hover:text-green-900">Verify</button>
                                </form>
                                <form method="POST" action="<?php echo e(route('billing.waive', $invoice)); ?>" class="inline ml-2">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="text-red-600 hover:text-red-900">Waive</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No invoices found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/billing/admin.blade.php ENDPATH**/ ?>