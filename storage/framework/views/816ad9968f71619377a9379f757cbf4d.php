<?php $__env->startSection('title', $submission->title); ?>

<?php $__env->startSection('content'); ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-2xl font-bold"><?php echo e($submission->title); ?></h2>
                    <p class="text-gray-500"><?php echo e($submission->course->name); ?> • <?php echo e(ucfirst($submission->type)); ?></p>
                </div>
                <span class="px-3 py-1 rounded text-sm 
                    <?php if($submission->status === 'graded'): ?> bg-green-100 text-green-800
                    <?php elseif($submission->status === 'approved'): ?> bg-blue-100 text-blue-800
                    <?php elseif(in_array($submission->status, ['submitted', 'under_review'])): ?> bg-yellow-100 text-yellow-800
                    <?php else: ?> bg-gray-100 text-gray-800
                    <?php endif; ?>">
                    <?php echo e(ucfirst(str_replace('_', ' ', $submission->status))); ?>

                </span>
            </div>

            <?php if($submission->description): ?>
                <p class="text-gray-700 mb-6"><?php echo e($submission->description); ?></p>
            <?php endif; ?>

            <h3 class="font-bold mb-3">Files</h3>
            <?php if($submission->versions->isEmpty()): ?>
                <p class="text-gray-500 mb-4">No files uploaded yet.</p>
            <?php else: ?>
                <ul class="space-y-2 mb-6">
                    <?php $__currentLoopData = $submission->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <span><?php echo e($version->file_name); ?></span>
                            <a href="<?php echo e(route('submission-versions.download', ['submission' => $submission, 'version' => $version->id])); ?>" 
                                class="text-indigo-600 hover:underline text-sm">
                                Download
                            </a>
                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            <?php endif; ?>

            <?php if(in_array($submission->status, ['draft', 'correction_requested'])): ?>
                <form method="POST" action="<?php echo e(route('submissions.upload', $submission)); ?>" enctype="multipart/form-data" class="mb-4">
                    <?php echo csrf_field(); ?>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Upload File</label>
                    <input type="file" name="file" class="border rounded px-3 py-2 w-full" required>
                    <button type="submit" class="mt-2 bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        Upload
                    </button>
                </form>

                <?php if($submission->versions->isNotEmpty()): ?>
                    <form method="POST" action="<?php echo e(route('submissions.submit', $submission)); ?>">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                            Submit for Review
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if($submission->type === 'project'): ?>
            <?php echo $__env->make('ai._contextual-assistant', [
                'assistantFeature' => 'project_assistant',
                'assistantEndpoint' => route('ai.context.project', $submission),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <div class="h-6"></div>
        <?php endif; ?>

        <!-- Comments -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold mb-4">Feedback</h3>
            <?php if($submission->comments->isEmpty()): ?>
                <p class="text-gray-500">No feedback yet.</p>
            <?php else: ?>
                <?php $__currentLoopData = $submission->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border-b pb-3 mb-3">
                        <p class="font-medium"><?php echo e($comment->user->full_name); ?></p>
                        <p class="text-gray-700"><?php echo e($comment->content); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo e($comment->created_at->format('M d, Y H:i')); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold mb-3">Details</h3>
            <dl class="space-y-2">
                <dt class="text-gray-500 text-sm">Version</dt>
                <dd class="font-medium"><?php echo e($submission->version); ?></dd>
                <dt class="text-gray-500 text-sm">Due Date</dt>
                <dd class="font-medium"><?php echo e($submission->due_date?->format('M d, Y') ?? 'No deadline'); ?></dd>
                <dt class="text-gray-500 text-sm">Submitted</dt>
                <dd class="font-medium"><?php echo e($submission->submitted_at?->format('M d, Y H:i') ?? '-'); ?></dd>
            </dl>
        </div>

        <?php if($submission->grade): ?>
        <div class="bg-white p-6 rounded-lg shadow mt-4">
            <h3 class="font-bold mb-3">Grade</h3>
            <p class="text-3xl font-bold text-indigo-600">
                <?php echo e($submission->grade->score); ?>/<?php echo e($submission->grade->max_score); ?>

            </p>
            <?php if($submission->grade->feedback): ?>
                <p class="text-gray-600 mt-2"><?php echo e($submission->grade->feedback); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if($aiAnalyses->isNotEmpty()): ?>
        <div class="bg-white p-6 rounded-lg shadow mt-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold">AI Analysis</h3>
                <a href="<?php echo e(route('ai.submission.analysis', $submission)); ?>" class="text-indigo-600 hover:underline text-sm">
                    View details
                </a>
            </div>
            <ul class="space-y-2">
                <?php $__currentLoopData = $aiAnalyses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ai): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="flex items-center justify-between text-sm">
                        <span class="capitalize"><?php echo e(str_replace('_', ' ', $ai->feature)); ?></span>
                        <span class="font-medium">
                            <?php if(!is_null($ai->score)): ?> <?php echo e($ai->score); ?>/100 <?php else: ?> <?php echo e(ucfirst($ai->status)); ?> <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/submissions/show.blade.php ENDPATH**/ ?>