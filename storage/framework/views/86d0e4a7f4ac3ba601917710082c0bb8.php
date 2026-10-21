<?php $__env->startSection('title', 'Manage Extensions'); ?>

<?php $__env->startSection('content'); ?>
<?php ($baseDeadline = $task->late_deadline ?? $task->close_at ?? $task->due_date); ?>
<div class="container mx-auto max-w-6xl px-4 py-8">
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Manage Extensions</h1>
            <p class="mt-2 text-gray-600"><?php echo e($task->title); ?></p>
        </div>
        <a href="<?php echo e(route('submission-tasks.lecturer.show', [$course, $task])); ?>" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Back to assignment</a>
    </div>

    <?php if($errors->any()): ?>
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul>
        </div>
    <?php endif; ?>

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b p-6">
                    <h2 class="text-xl font-bold text-gray-900">Enrolled students</h2>
                    <p class="mt-1 text-sm text-gray-600">Submitting this form creates or updates the existing extension record; no duplicate record is created.</p>
                </div>

                <?php $__empty_0 = true; $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <?php ($extension = $extensions->get($enrollment->user_id)); ?>
                    <div class="border-b p-6 last:border-b-0">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo e($enrollment->user->name); ?></p>
                                <p class="text-sm text-gray-600"><?php echo e($enrollment->user->email); ?></p>
                            </div>
                            <?php if($extension): ?>
                                <form action="<?php echo e(route('submission-tasks.extension.revoke', [$course, $task, $extension])); ?>" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="text-sm font-semibold text-red-600 hover:text-red-800" onclick="return confirm('Revoke this extension?')">Revoke</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <form action="<?php echo e(route('submission-tasks.extension.grant', [$course, $task])); ?>" method="POST" class="grid gap-4 md:grid-cols-3">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="student_id" value="<?php echo e($enrollment->user_id); ?>">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Original deadline</label>
                                <div class="mt-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700"><?php echo e($baseDeadline?->format('M d, Y H:i') ?? 'Not set'); ?></div>
                            </div>
                            <div>
                                <label for="extended_deadline_<?php echo e($enrollment->user_id); ?>" class="block text-sm font-medium text-gray-700">Extended deadline</label>
                                <input id="extended_deadline_<?php echo e($enrollment->user_id); ?>" type="datetime-local" name="extended_deadline"
                                       value="<?php echo e(old('student_id') == $enrollment->user_id ? old('extended_deadline') : $extension?->extended_deadline?->format('Y-m-d\TH:i')); ?>"
                                       min="<?php echo e(now()->format('Y-m-d\TH:i')); ?>" required
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="reason_<?php echo e($enrollment->user_id); ?>" class="block text-sm font-medium text-gray-700">Reason</label>
                                <input id="reason_<?php echo e($enrollment->user_id); ?>" name="reason" maxlength="1000"
                                       value="<?php echo e(old('student_id') == $enrollment->user_id ? old('reason') : $extension?->reason); ?>"
                                       class="mt-1 w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional reason">
                            </div>
                            <div class="md:col-span-3 flex items-center justify-between gap-4">
                                <p class="text-xs text-gray-500">
                                    <?php if($extension): ?>
                                        Current extension: <?php echo e($extension->extended_deadline?->format('M d, Y H:i')); ?>

                                    <?php else: ?>
                                        No extension granted.
                                    <?php endif; ?>
                                </p>
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    <?php echo e($extension ? 'Update extension' : 'Grant extension'); ?>

                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <div class="p-10 text-center text-sm text-gray-500">No enrolled students were found for this course and semester.</div>
                <?php endif; ?>
            </div>
            <?php if($enrollments->hasPages()): ?>
                <div class="mt-5"><?php echo e($enrollments->links()); ?></div>
            <?php endif; ?>
        </div>

        <aside class="space-y-6">
            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="font-bold text-gray-900">Assignment</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-gray-500">Type</dt><dd class="font-semibold text-gray-900"><?php echo e(ucfirst($task->type)); ?></dd></div>
                    <div><dt class="text-gray-500">Base deadline</dt><dd class="font-semibold text-gray-900"><?php echo e($baseDeadline?->format('M d, Y H:i') ?? 'Not set'); ?></dd></div>
                    <div><dt class="text-gray-500">Enrolled students</dt><dd class="font-semibold text-gray-900"><?php echo e($enrollments->total()); ?></dd></div>
                    <div><dt class="text-gray-500">Extensions granted</dt><dd class="font-semibold text-gray-900"><?php echo e($extensionCount); ?></dd></div>
                </dl>
            </div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 text-sm text-blue-900">
                Extensions are scoped to this assignment and enrolled student. Updating an extension preserves the original deadline and replaces only the approved extended deadline and reason.
            </div>
        </aside>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submission-tasks/extensions.blade.php ENDPATH**/ ?>