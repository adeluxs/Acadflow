<?php $__env->startSection('title', 'My Enrollments'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">My Enrollments</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php $__empty_0 = true; $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold"><?php echo e($enrollment->course->code); ?></h3>
                <p class="text-gray-600"><?php echo e($enrollment->course->name); ?></p>
                <div class="mt-4">
                    <span class="px-2 inline-flex text-xs font-semibold rounded-full 
                        <?php echo e($enrollment->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                        <?php echo e(ucfirst($enrollment->status)); ?>

                    </span>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
            <p class="text-gray-500">No enrollments found.</p>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/enrollments/index.blade.php ENDPATH**/ ?>