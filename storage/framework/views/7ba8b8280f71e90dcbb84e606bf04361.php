<?php $__env->startSection('title', 'AI Analytics'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-5xl mx-auto">
    <h2 class="text-2xl font-bold mb-2">AI Analytics</h2>
    <p class="text-gray-500 mb-6">Usage and performance metrics for the AI Academic Assistant.</p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Total Requests</p>
            <p class="text-2xl font-bold"><?php echo e($summary['total_requests']); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Cache Hits</p>
            <p class="text-2xl font-bold text-green-600"><?php echo e($summary['cache_hits']); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Rule Engine</p>
            <p class="text-2xl font-bold"><?php echo e($summary['rule_engine_requests']); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Provider</p>
            <p class="text-2xl font-bold"><?php echo e($summary['provider_requests']); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Est. Savings ($)</p>
            <p class="text-2xl font-bold text-emerald-600"><?php echo e($summary['estimated_savings']); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Avg Time (s)</p>
            <p class="text-2xl font-bold"><?php echo e($summary['average_processing_time']); ?></p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Failure Rate</p>
            <p class="text-2xl font-bold"><?php echo e(number_format($summary['failure_rate'] * 100, 2)); ?>%</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow">
            <p class="text-sm text-gray-500">Hybrid Requests</p>
            <p class="text-2xl font-bold"><?php echo e($summary['hybrid_requests']); ?></p>
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
        <h3 class="font-semibold mb-3">Most Used Features</h3>
        <?php if(empty($summary['top_features'])): ?>
            <p class="text-gray-500">No usage data yet.</p>
        <?php else: ?>
            <ul class="space-y-2">
                <?php $__currentLoopData = $summary['top_features']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="flex items-center justify-between text-sm border-b pb-2">
                        <span class="capitalize"><?php echo e(str_replace('_', ' ', $feature)); ?></span>
                        <span class="font-medium"><?php echo e($count); ?></span>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/ai/analytics.blade.php ENDPATH**/ ?>