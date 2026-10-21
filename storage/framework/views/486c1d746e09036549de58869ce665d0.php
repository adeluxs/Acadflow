<?php $__env->startSection('title', 'My Submissions'); ?>

<?php $__env->startSection('content'); ?>
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold">My Submissions</h2>
    <a href="<?php echo e(route('submissions.create')); ?>" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
        + New Submission
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php $__empty_0 = true; $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                <tr>
                    <td class="px-6 py-4">
                        <a href="<?php echo e(route('submissions.show', $submission)); ?>" class="text-indigo-600 hover:underline">
                            <?php echo e($submission->title); ?>

                        </a>
                    </td>
                    <td class="px-6 py-4 text-gray-500"><?php echo e($submission->course->name); ?></td>
                    <td class="px-6 py-4 text-gray-500"><?php echo e(ucfirst($submission->type)); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs 
                            <?php if($submission->status === 'graded'): ?> bg-green-100 text-green-800
                            <?php elseif($submission->status === 'approved'): ?> bg-blue-100 text-blue-800
                            <?php elseif(in_array($submission->status, ['submitted', 'under_review'])): ?> bg-yellow-100 text-yellow-800
                            <?php else: ?> bg-gray-100 text-gray-800
                            <?php endif; ?>">
                            <?php echo e(ucfirst(str_replace('_', ' ', $submission->status))); ?>

                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        <?php echo e($submission->submitted_at?->format('M d, Y') ?? '-'); ?>

                    </td>
                    <td class="px-6 py-4">
                        <a href="<?php echo e(route('submissions.show', $submission)); ?>" class="text-indigo-600 hover:underline text-sm">
                            View
                        </a>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No submissions yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="mt-4">
    <?php echo e($submissions->links()); ?>

</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/index.blade.php ENDPATH**/ ?>