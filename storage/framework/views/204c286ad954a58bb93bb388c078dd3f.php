<?php $__env->startSection('title', 'Edit draft — '.$submission->title); ?>

<?php $__env->startSection('content'); ?>
<?php
    $task = $submission->task;
    $format = $task?->submission_format ?? 'file';
    $acceptsText = in_array($format, ['text', 'both'], true);
    $acceptsFiles = in_array($format, ['file', 'both'], true);
?>

<div class="mx-auto max-w-3xl">
    <a href="<?php echo e(route('submissions.show', $submission)); ?>" class="text-sm font-semibold text-indigo-600 hover:underline">← Back to submission</a>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900">Edit draft</h1>
            <p class="mt-1 text-sm text-gray-500">
                <?php echo e($submission->course?->code); ?> — <?php echo e($submission->course?->name); ?>

                <?php if($task): ?> · <?php echo e($task->title); ?> <?php endif; ?>
            </p>
        </div>
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Draft — not yet submitted</span>
    </div>

    <div class="mt-6 rounded-lg bg-white p-6 shadow">
        <?php if($errors->any()): ?>
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4">
                <ul class="space-y-1 text-sm text-red-700">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li>• <?php echo e($error); ?></li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('submissions.update', $submission)); ?>">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>

            <div class="mb-6">
                <label for="submissionTitle" class="block text-gray-700 text-sm font-bold mb-2">Title</label>
                <input id="submissionTitle" name="title" type="text"
                       class="w-full px-3 py-2 border rounded focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                       value="<?php echo e(old('title', $submission->title)); ?>" required>
            </div>

            <?php if($acceptsText): ?>
                <div class="mb-6">
                    <label for="submissionContent" class="block text-gray-700 text-sm font-bold mb-2">Written answer</label>
                    <p class="mb-2 text-xs text-gray-500">Use the toolbar to format headings, lists, links and emphasis — your answer is saved with the submission.</p>
                    <textarea id="submissionContent" name="description" rows="16" data-rich-editor data-editor-height="420px"><?php echo e(old('description', $submission->description)); ?></textarea>
                </div>
            <?php endif; ?>

            <?php if($acceptsFiles): ?>
                <div class="mb-6 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-700">Files</p>
                    <p class="mt-1 text-xs text-slate-500">Files are managed on the submission page. Use “Add or replace files” there, then submit for review.</p>
                    <?php if($submission->versions->isNotEmpty()): ?>
                        <ul class="mt-3 space-y-1 text-sm text-slate-700">
                            <?php $__currentLoopData = $submission->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="truncate">• <?php echo e($version->file_name); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php else: ?>
                        <p class="mt-2 text-xs text-slate-500">No files attached yet.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-3 border-t pt-6">
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700 font-semibold">
                    Save changes
                </button>
                <a href="<?php echo e(route('submissions.show', $submission)); ?>" class="text-gray-600 font-semibold hover:text-gray-800">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/edit.blade.php ENDPATH**/ ?>