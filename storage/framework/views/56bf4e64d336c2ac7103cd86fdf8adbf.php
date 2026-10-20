<?php $__env->startSection('title', 'Configure ' . $paymentGateway->name); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-8">
        <a href="<?php echo e(route('admin.payment-gateways.index')); ?>" class="text-indigo-600 hover:underline flex items-center gap-2 mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Gateways
        </a>
        <h1 class="text-3xl font-bold">Configure <?php echo e($paymentGateway->name); ?></h1>
        <p class="text-gray-600 mt-2">Set up credentials and configuration for <?php echo e($paymentGateway->name); ?>.</p>
    </div>

    <?php if(session('success')): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <div class="mb-6 rounded-lg bg-red-50 p-4 text-red-700" role="alert"><?php echo e($errors->first()); ?></div>
    <?php endif; ?>
    <div class="mb-6 rounded-lg bg-slate-50 p-4 text-sm">
        <p>Save configuration before testing. Use this webhook URL in your Paystack dashboard for both test and live mode:</p>
        <code class="break-all"><?php echo e(route('commerce.webhook', ['gateway' => $paymentGateway->code])); ?></code>
        <p class="mt-2">Webhook signatures use the secret key for the selected environment.</p>
    </div>
    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="<?php echo e(route('admin.payment-gateways.update', $paymentGateway)); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Code</label>
                    <input type="text" value="<?php echo e($paymentGateway->code); ?>" disabled 
                           class="w-full border-gray-200 bg-gray-50 rounded-lg shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gateway Name</label>
                    <input type="text" value="<?php echo e($paymentGateway->name); ?>" disabled 
                           class="w-full border-gray-200 bg-gray-50 rounded-lg shadow-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="<?php echo e(old('sort_order', $paymentGateway->sort_order)); ?>" 
                           class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" <?php echo e($paymentGateway->is_active ? 'checked' : ''); ?> 
                               class="h-4 w-4 text-indigo-600 rounded">
                        <span class="ml-2 text-sm text-gray-700">Activate gateway</span>
                        <p class="text-xs text-gray-500 ml-2">Enable this gateway for wallet, marketplace and institutional payment flows</p>
                    </label>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t">
                <h3 class="text-lg font-bold mb-4">API Credentials</h3>
                <p class="text-sm text-gray-600 mb-4">These credentials are encrypted and stored securely.</p>
                
                <?php $__currentLoopData = $configFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fieldKey => $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if($field['type'] === 'password'): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e($field['label']); ?></label>
                            <input type="password" name="<?php echo e($fieldKey); ?>" 
                                   class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="<?php echo e($field['placeholder'] ?? ''); ?>"
                                   value="">
                            <?php if(isset($field['hint'])): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo e($field['hint']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current value</p>
                        </div>
                    <?php elseif($field['type'] === 'select'): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e($field['label']); ?></label>
                            <select name="<?php echo e($fieldKey); ?>" 
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <?php $__currentLoopData = $field['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($value); ?>" <?php echo e((old($fieldKey, ($fieldKey === 'environment' ? ($paymentGateway->is_test_mode ? 'test' : 'live') : ($paymentGateway->settings[$fieldKey] ?? $field['default'] ?? null))) == $value) ? 'selected' : ''); ?>>
                                        <?php echo e($label); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    <?php elseif($field['type'] === 'multiselect'): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e($field['label']); ?></label>
                            <select name="<?php echo e($fieldKey); ?>[]" multiple 
                                    class="w-full border-gray-300 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 h-32">
                                <?php $__currentLoopData = $field['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($value); ?>" 
                                            <?php echo e(in_array($value, old($fieldKey, $paymentGateway->settings[$fieldKey] ?? $field['default'] ?? [])) ? 'selected' : ''); ?>>
                                        <?php echo e($label); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple</p>
                        </div>
                    <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="mt-6 pt-6 border-t">
                <h3 class="text-lg font-bold mb-4">Test Connection</h3>
                <p class="text-sm text-gray-600 mb-4">Verify that the gateway is configured correctly.</p>
                <button type="button" onclick="testGatewayConnection('<?php echo e($paymentGateway->id); ?>')" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    Test Connection
                </button>
                <div id="connection-test-result" class="mt-3"></div>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <a href="<?php echo e(route('admin.payment-gateways.index')); ?>" 
                   class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium">
                    Save Configuration
                </button>
            </div>
        </form>
    </div>
</div>

<script>
async function testGatewayConnection(gatewayId) {
    const resultDiv = document.getElementById('connection-test-result');
    const render = (message, tone = 'info', retryable = false) => {
        resultDiv.innerHTML = '';
        const panel = document.createElement('div');
        panel.className = tone === 'success'
            ? 'mt-2 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800'
            : tone === 'error'
                ? 'mt-2 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800'
                : 'mt-2 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm font-semibold text-sky-800';
        panel.textContent = message;
        resultDiv.appendChild(panel);

        if (retryable) {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'mt-2 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs font-bold text-rose-700';
            retry.textContent = 'Try Again';
            retry.addEventListener('click', () => testGatewayConnection(gatewayId));
            resultDiv.appendChild(retry);
        }
    };

    render('Testing gateway connection…');

    try {
        const response = await fetch(<?php echo json_encode(route('admin.payment-gateways.test-connection', $paymentGateway), 512) ?>, {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.status !== 'success') {
            const requestError = new Error(data.message || 'The gateway connection could not be verified.');
            requestError.data = data;
            requestError.status = response.status;
            throw requestError;
        }
        render('Connection successful. The gateway responded correctly.', 'success');
    } catch (error) {
        const detail = window.AcadFlowFeedback?.normalize(error, 'The gateway connection could not be verified right now.') || {
            message: 'The gateway connection could not be verified right now.', retryable: true
        };
        render(detail.message, 'error', detail.retryable);
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/payment-gateways/edit.blade.php ENDPATH**/ ?>