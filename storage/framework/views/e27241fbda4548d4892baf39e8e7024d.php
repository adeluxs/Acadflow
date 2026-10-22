<?php $__env->startSection('title', 'Compare Submission Versions'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold">Compare Versions</h1>
                <p class="text-gray-600"><?php echo e($submission->title); ?> &middot; <?php echo e(ucfirst($submission->type)); ?></p>
            </div>
            <a href="<?php echo e(route('submissions.review', $submission)); ?>" class="text-indigo-600 hover:underline">Back to review</a>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <?php $__currentLoopData = $versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="border rounded p-4 bg-gray-50">
                    <h2 class="text-xl font-semibold mb-3">Version <?php echo e($version->version_number); ?></h2>
                    <p class="text-gray-700 mb-2"><strong>File:</strong> <?php echo e($version->file_name); ?></p>
                    <p class="text-gray-700 mb-2"><strong>Uploaded:</strong> <?php echo e($version->created_at?->format('M d, Y H:i') ?? '-'); ?></p>
                    <p class="text-gray-700 mb-2"><strong>Size:</strong> <?php echo e(number_format($version->file_size / 1024, 2)); ?> KB</p>
                    <p class="text-gray-700 mb-4"><strong>Uploaded by:</strong> <?php echo e($version->uploader?->first_name ?? 'Unknown'); ?> <?php echo e($version->uploader?->last_name ?? ''); ?></p>
                    <a href="<?php echo e(route('submission-versions.download', $version)); ?>" class="inline-block px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Download Version
                    </a>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>

        <div class="mt-6 bg-white p-4 rounded shadow-sm">
            <h3 class="text-lg font-semibold mb-3">Comparison Notes</h3>
            <p class="text-gray-600">This view shows the selected versions side-by-side so a reviewer can compare upload timestamps, file names, sizes, and download each version.</p>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/compare.blade.php ENDPATH**/ ?>