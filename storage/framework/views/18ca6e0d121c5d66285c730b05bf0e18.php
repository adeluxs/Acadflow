<?php $__env->startSection('title', $course->name); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6"><?php echo e($course->code); ?> - <?php echo e($course->name); ?></h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <div class="text-gray-500 text-sm">Credits</div>
                <div><?php echo e($course->credit_hours); ?></div>
            </div>
            <div>
                <div class="text-gray-500 text-sm">Department</div>
                <div><?php echo e($course->department->name); ?></div>
            </div>
        </div>
    </div>

    <h2 class="text-xl font-bold mb-4">Enrolled Students</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php $__empty_0 = true; $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <tr>
                        <td class="px-6 py-4"><?php echo e($enrollment->user->email); ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo e($enrollment->status === 'enrolled' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'); ?>">
                                <?php echo e(ucfirst($enrollment->status)); ?>

                            </span>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <tr>
                        <td colspan="2" class="px-6 py-4 text-center text-gray-500">No enrollments.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($enrollments->hasPages()): ?>
        <div class="mt-5"><?php echo e($enrollments->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/enrollments/course.blade.php ENDPATH**/ ?>