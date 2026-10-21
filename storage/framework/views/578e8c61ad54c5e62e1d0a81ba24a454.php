<?php $__env->startSection('title', 'Payment Gateways'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Payment Gateways</h1>
        <a href="<?php echo e(route('admin.payment-gateways.create')); ?>" 
           class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
            + Add Gateway
        </a>
    </div>

    <?php if(session('success')): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gateway</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Configured</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__empty_0 = true; $__currentLoopData = $gateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <tr>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900"><?php echo e($gateway->name); ?></div>
                            <div class="text-sm text-gray-500"><?php echo e($gateway->description ?? $gateway->code); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo e($gateway->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                <?php echo e($gateway->is_active ? 'Active' : 'Inactive'); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo e($gateway->is_test_mode ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                <?php echo e($gateway->is_test_mode ? 'Test' : 'Live'); ?>

                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if($gateway->is_configured): ?>
                                <span class="text-green-600">✓ Configured</span>
                            <?php else: ?>
                                <span class="text-red-600">✗ Not Configured</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <a href="<?php echo e(route('admin.payment-gateways.edit', $gateway)); ?>" 
                               class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <?php if(!$gateway->is_active): ?>
                                <form method="POST" action="<?php echo e(route('admin.payment-gateways.destroy', $gateway)); ?>" 
                                      class="inline" onsubmit="return confirm('Are you sure?');">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="text-red-600 hover:text-red-900 ml-2">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No payment gateways configured.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/payment-gateways/index.blade.php ENDPATH**/ ?>