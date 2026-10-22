<?php $__env->startSection('title', 'My Documents'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My Generated Documents</h1>
        <p class="text-gray-600 mt-2">Download your finalized academic documents</p>
    </div>

    <div class="bg-white rounded-lg shadow">
        <?php if($documents && $documents->count() > 0): ?>
            <div class="divide-y">
                <?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="p-6 flex justify-between items-center hover:bg-gray-50">
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-900"><?php echo e($doc->title); ?></h3>
                            <div class="flex gap-4 text-sm text-gray-600 mt-1">
                                <span>Type: <?php echo e($doc->template?->type ?? 'N/A'); ?></span>
                                <span>Size: <?php echo e(round($doc->file_size / 1024, 2)); ?> KB</span>
                                <span>Generated: <?php echo e($doc->created_at->format('M d, Y')); ?></span>
                            </div>
                        </div>
                        <div>
                            <a href="<?php echo e(route('documents.download', $doc)); ?>" 
                               class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                                Download PDF
                            </a>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="p-6">
                <?php echo e($documents->links()); ?>

            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-lg mb-2">No documents generated yet</p>
                <p class="text-gray-400">Documents are generated from approved/graded submissions</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/documents/index.blade.php ENDPATH**/ ?>