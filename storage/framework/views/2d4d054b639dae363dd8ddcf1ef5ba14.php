<?php $__env->startSection('title', 'My Submissions Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My Submissions Dashboard</h1>
        <p class="text-gray-600 mt-2">Track all your submissions across courses</p>
    </div>

    <!-- Stats Summary -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Total</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo e($submissions->total()); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Submitted</p>
            <p class="text-2xl font-bold text-green-600"><?php echo e($stats['submitted']); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Graded</p>
            <p class="text-2xl font-bold text-blue-600"><?php echo e($stats['graded']); ?></p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Needs Correction</p>
            <p class="text-2xl font-bold text-red-600"><?php echo e($stats['correction_requested']); ?></p>
        </div>
    </div>

    <!-- Due soon / overdue assignments the student has not started -->
    <?php if(isset($dueSoon) && $dueSoon->isNotEmpty()): ?>
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b flex items-center justify-between">
                <h2 class="text-xl font-bold text-gray-900">Due soon & overdue</h2>
                <span class="text-xs font-semibold text-gray-500"><?php echo e($dueSoon->count()); ?> assignment(s)</span>
            </div>
            <div class="divide-y">
                <?php $__currentLoopData = $dueSoon; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php ($deadline = $task->acceptanceDeadline(auth()->user()) ?? ($task->close_at ?? $task->due_date)); ?>
                    <a href="<?php echo e(route('submission-tasks.student.show', ['course' => $task->course ?? $task->course_id, 'task' => $task])); ?>" class="flex items-center justify-between p-4 hover:bg-gray-50">
                        <div>
                            <p class="text-xs font-semibold text-indigo-600"><?php echo e($task->course?->code); ?></p>
                            <p class="font-bold text-gray-900"><?php echo e($task->title); ?></p>
                        </div>
                        <span class="text-sm font-semibold <?php echo e($deadline && $deadline->isPast() ? 'text-red-600' : 'text-gray-700'); ?>">
                            <?php echo e($deadline ? $deadline->format('M j, Y H:i') : 'No deadline'); ?>

                        </span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Submissions List -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-900">All Submissions</h2>
        </div>

        <?php if($submissions && $submissions->count() > 0): ?>
            <div class="divide-y">
                <?php $__currentLoopData = $submissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="p-6 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="font-bold text-gray-900"><?php echo e($submission->title); ?></h3>
                                    <span class="px-2 py-1 text-xs rounded-full
                                        <?php if($submission->status === 'submitted' || $submission->status === 'resubmitted'): ?> bg-green-100 text-green-800
                                        <?php elseif($submission->status === 'graded' || $submission->status === 'approved'): ?> bg-blue-100 text-blue-800
                                        <?php elseif($submission->status === 'correction_requested'): ?> bg-red-100 text-red-800
                                        <?php else: ?> bg-gray-100 text-gray-800
                                        <?php endif; ?>
                                    ">
                                        <?php echo e(ucfirst(str_replace('_', ' ', $submission->status))); ?>

                                        <?php if($submission->is_late): ?>
                                            <span class="ml-1 text-red-600">LATE</span>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="flex gap-6 text-sm text-gray-600">
                                    <span><?php echo e($submission->course->code); ?> - <?php echo e($submission->course->name); ?></span>
                                    <span><?php echo e(ucfirst($submission->type)); ?></span>
                                    <?php if($submission->submitted_at): ?>
                                        <span>Submitted: <?php echo e($submission->submitted_at->format('M d, Y H:i')); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if($submission->grade): ?>
                                    <div class="mt-2">
                                        <span class="text-sm font-semibold text-gray-900">Grade: </span>
                                        <span class="text-lg font-bold <?php echo e($submission->grade->score >= ($submission->task->max_score ?? 100) * 0.6 ? 'text-green-600' : 'text-red-600'); ?>">
                                            <?php echo e($submission->grade->score); ?>/<?php echo e($submission->task->max_score ?? 100); ?>

                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if($submission->comments->whereIn('type', ['general', 'feedback'])->count() > 0): ?>
                                    <div class="mt-2 p-3 bg-blue-50 rounded border border-blue-200">
                                        <p class="text-sm font-semibold text-blue-900 mb-1">Latest Feedback:</p>
                                        <p class="text-sm text-blue-800">
                                            <?php echo e(Str::limit($submission->comments->whereIn('type', ['general', 'feedback'])->last()->content, 100)); ?>

                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex gap-2 ml-4">
                                <a href="<?php echo e(route('submissions.show', $submission)); ?>" 
                                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                                    View
                                </a>

                                <?php if(in_array($submission->status, ['correction_requested', 'draft'])): ?>
                                    <a href="<?php echo e(route('submissions.show', $submission)); ?>" 
                                       class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                                        <?php echo e($submission->status === 'correction_requested' ? 'Resubmit' : 'Edit'); ?>

                                    </a>
                                <?php endif; ?>

                                <?php if($submission->versions->where('is_current', true)->first()): ?>
                                    <a href="<?php echo e(route('submissions.view', [$submission, $submission->versions->where('is_current', true)->first()->id])); ?>" 
                                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm"
                                       target="_blank">
                                        View File
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="p-6">
                <?php echo e($submissions->links()); ?>

            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-lg mb-2">No submissions yet</p>
                <p class="text-gray-400">Enroll in courses and start submitting assignments</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/student-dashboard.blade.php ENDPATH**/ ?>