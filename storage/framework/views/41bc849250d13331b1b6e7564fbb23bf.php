<?php $__env->startSection('title', 'Create Payment Gateway'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-8">
        <a href="<?php echo e(route('admin.payment-gateways.index')); ?>" class="text-indigo-600 hover:underline flex items-center gap-2 mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Gateways
        </a>
        <h1 class="text-3xl font-bold">Add Payment Gateway</h1>
        <p class="text-gray-600 mt-2">Configure a payment gateway for wallet funding, marketplace payments and institutional billing.</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="<?php echo e(route('admin.payment-gateways.store')); ?>">
            <?php echo csrf_field(); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Code *</label>
                    <input type="text" name="code" value="<?php echo e(old('code')); ?>" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="e.g., paystack" required>
                    <p class="text-xs text-gray-500 mt-1">Unique identifier (lowercase, no spaces)</p>
                    <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Name *</label>
                    <input type="text" name="name" value="<?php echo e(old('name')); ?>" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="e.g., Paystack" required>
                    <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-red-600 text-xs mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <input type="text" name="description" value="<?php echo e(old('description')); ?>" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                           placeholder="Brief description of the gateway">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Environment</label>
                    <select name="is_test_mode" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1">Test Mode (Sandbox)</option>
                        <option value="0">Live Mode (Production)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Test with dummy transactions before going live</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="<?php echo e(old('sort_order', 0)); ?>" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">Lower numbers appear first</p>
                </div>

                <p class="md:col-span-2 text-sm text-gray-600">The gateway starts inactive. Configure credentials and activate it on the next page.</p>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <a href="<?php echo e(route('admin.payment-gateways.index')); ?>" 
                   class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    Create Gateway
                </button>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/payment-gateways/create.blade.php ENDPATH**/ ?>