<?php $__env->startSection('title', $submission->title); ?>

<?php $__env->startSection('content'); ?>
<?php
    $task = $submission->task;
    $course = $submission->course;

    $isDraft = in_array($submission->status, ['draft', 'correction_requested'], true);
    $isSubmitted = ! $isDraft;

    $submissionFormat = $task?->submission_format ?? 'file';

    $descriptionText = \App\Support\HtmlSanitizer::toPlainText($submission->description);
    $hasText = $descriptionText !== '';
    $hasFiles = $submission->versions->isNotEmpty();

    // A draft can be completed only when it already contains what its format
    // requires, mirroring the same rules the controller enforces on submit.
    $readyToSubmit = match ($submissionFormat) {
        'text' => $hasText,
        'file' => $hasFiles,
        default => $hasText || $hasFiles,
    };

    $statusLabel = \Illuminate\Support\Str::headline($submission->status);
    $statusClasses = match (true) {
        $submission->status === 'graded' => 'bg-emerald-100 text-emerald-800',
        $submission->status === 'approved' => 'bg-blue-100 text-blue-800',
        in_array($submission->status, ['submitted', 'under_review', 'resubmitted'], true) => 'bg-amber-100 text-amber-800',
        $submission->status === 'correction_requested' => 'bg-rose-100 text-rose-800',
        default => 'bg-slate-100 text-slate-700',
    };

    $deadline = $submission->extension_until ?? $submission->due_date ?? $task?->due_date;
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold break-words"><?php echo e($submission->title); ?></h2>
                    <p class="mt-1 text-gray-500">
                        <?php echo e($course?->name); ?>

                        <?php if($course?->code): ?> • <?php echo e($course->code); ?> <?php endif; ?>
                        • <?php echo e(ucfirst($submission->type)); ?>

                    </p>
                    <?php if($task): ?>
                        <p class="mt-1 text-sm text-gray-500">
                            Assignment: <span class="font-medium text-gray-700"><?php echo e($task->title); ?></span>
                            <?php if($task->max_score): ?> · Max score <?php echo e($task->max_score); ?> <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="text-right shrink-0">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold <?php echo e($statusClasses); ?>">
                        <?php echo e($statusLabel); ?><?php if($submission->is_late && $isSubmitted): ?> · Late <?php endif; ?>
                    </span>
                    <p class="mt-2 text-xs text-gray-500">
                        <?php if($submission->submitted_at): ?>
                            Submitted <?php echo e($submission->submitted_at->format('M d, Y · H:i')); ?>

                        <?php else: ?>
                            Not submitted yet
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if($submission->status === 'correction_requested'): ?>
                <div class="mt-6 rounded-lg border border-rose-200 bg-rose-50 p-4">
                    <p class="font-semibold text-rose-900">Corrections requested.</p>
                    <p class="mt-1 text-sm text-rose-800">Review your lecturer's feedback, update your work below, then resubmit.</p>
                </div>
            <?php elseif($isDraft): ?>
                <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="font-semibold text-amber-900">This is a draft — not yet submitted.</p>
                    <p class="mt-1 text-sm text-amber-800">
                        Review your work below. When it is complete, use “Submit for review” so your lecturer can grade it.
                        <?php if (! ($readyToSubmit)): ?>
                            Add your <?php echo e($submissionFormat === 'text' ? 'written answer' : 'file(s)'); ?> first.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>

            
            <?php if($hasText): ?>
                <div class="mt-6">
                    <h3 class="font-bold mb-2">Written submission</h3>
                    <div class="prose prose-sm max-w-none rounded-lg border border-slate-200 bg-slate-50/60 p-4 text-gray-800"><?php echo $submission->description; ?></div>
                </div>
            <?php endif; ?>

            
            <?php if($hasFiles): ?>
                <div class="mt-6">
                    <h3 class="font-bold mb-3">Submitted files</h3>
                    <ul class="space-y-2">
                        <?php $__currentLoopData = $submission->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="flex flex-wrap items-center justify-between gap-3 p-3 bg-gray-50 rounded">
                                <span class="min-w-0 flex-1 truncate font-medium text-gray-800"><?php echo e($version->file_name); ?></span>
                                <span class="flex items-center gap-3 shrink-0">
                                    <a href="<?php echo e(route('submissions.view', ['submission' => $submission, 'version' => $version->id])); ?>"
                                       target="_blank" rel="noopener"
                                       class="text-sm font-semibold text-indigo-600 hover:underline">View</a>
                                    <a href="<?php echo e(route('submission-versions.download', $version)); ?>"
                                       class="text-sm font-semibold text-indigo-600 hover:underline">Download</a>
                                </span>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php elseif($isSubmitted): ?>
                <p class="mt-6 text-gray-500">No files were attached to this submission.</p>
            <?php endif; ?>

            
            <?php if($isDraft): ?>
                <div class="mt-6 border-t pt-6">
                    <?php if($submissionFormat !== 'text'): ?>
                        <h3 class="font-bold mb-3">Add or replace files</h3>
                        <form method="POST" action="<?php echo e(route('submissions.upload', $submission)); ?>" enctype="multipart/form-data" class="mb-4">
                            <?php echo csrf_field(); ?>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="draftFiles">Upload file(s)</label>
                            <input id="draftFiles" type="file" name="files[]" multiple class="border rounded px-3 py-2 w-full" required>
                            <button type="submit" class="mt-2 bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                                Upload
                            </button>
                        </form>
                    <?php endif; ?>

                    
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="<?php echo e(route('submissions.edit', $submission)); ?>"
                           class="inline-flex items-center rounded border border-slate-300 bg-white px-5 py-2 font-semibold text-slate-700 hover:bg-slate-50">
                            Edit draft
                        </a>

                        <?php if($readyToSubmit): ?>
                            <form method="POST" action="<?php echo e(route('submissions.submit', $submission)); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700 font-semibold">
                                    Submit for review
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (! ($readyToSubmit)): ?>
                        <p class="mt-3 text-sm text-gray-500">
                            <?php echo e($submissionFormat === 'text'
                                ? 'Write your answer using “Edit draft” before submitting.'
                                : 'Add the required files above to enable submission.'); ?>

                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if($submission->type === 'project'): ?>
            <?php echo $__env->make('ai._contextual-assistant', [
                'assistantFeature' => 'project_assistant',
                'assistantEndpoint' => route('ai.context.project', $submission),
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <div class="h-6"></div>
        <?php endif; ?>

        <!-- Feedback -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold mb-4">Feedback</h3>
            <?php if($submission->comments->isEmpty()): ?>
                <p class="text-gray-500">No feedback yet.</p>
            <?php else: ?>
                <?php $__currentLoopData = $submission->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border-b last:border-b-0 pb-3 mb-3 last:pb-0 last:mb-0">
                        <p class="font-medium"><?php echo e($comment->user?->full_name); ?></p>
                        <p class="text-gray-700"><?php echo e($comment->content); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo e($comment->created_at->format('M d, Y H:i')); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold mb-3">Details</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-gray-500 text-sm">Status</dt>
                    <dd class="font-medium">
                        <?php echo e($statusLabel); ?><?php if($submission->is_late && $isSubmitted): ?> · Late <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 text-sm">Submitted</dt>
                    <dd class="font-medium"><?php echo e($submission->submitted_at?->format('M d, Y H:i') ?? 'Not submitted'); ?></dd>
                </div>
                <div>
                    <dt class="text-gray-500 text-sm">Due date</dt>
                    <dd class="font-medium">
                        <?php echo e($deadline?->format('M d, Y') ?? 'No deadline'); ?>

                        <?php if($submission->extension_until): ?>
                            <span class="ml-1 text-xs font-semibold text-indigo-600">(extended)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 text-sm">Course</dt>
                    <dd class="font-medium"><?php echo e($course?->code); ?> — <?php echo e($course?->name); ?></dd>
                </div>
                <?php if($task): ?>
                    <div>
                        <dt class="text-gray-500 text-sm">Assignment</dt>
                        <dd class="font-medium"><?php echo e($task->title); ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt class="text-gray-500 text-sm">Version</dt>
                    <dd class="font-medium"><?php echo e($submission->version); ?></dd>
                </div>
            </dl>
        </div>

        <?php if($submission->grade): ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold mb-3">Grade</h3>
                <p class="text-3xl font-bold text-indigo-600">
                    <?php echo e($submission->grade->score); ?>/<?php echo e($submission->grade->max_score ?? $task?->max_score ?? 100); ?>

                </p>
                <?php if($submission->grade->feedback): ?>
                    <p class="text-gray-600 mt-2"><?php echo e($submission->grade->feedback); ?></p>
                <?php endif; ?>
            </div>
        <?php elseif($isSubmitted): ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold mb-2">Grade</h3>
                <p class="text-gray-500 text-sm">Awaiting grading.</p>
            </div>
        <?php endif; ?>

        <?php if($aiAnalyses->isNotEmpty()): ?>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold">AI analysis</h3>
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

        <div class="bg-white p-6 rounded-lg shadow">
            <a href="<?php echo e(route('submissions.dashboard')); ?>" class="text-sm font-semibold text-indigo-600 hover:underline">← Back to my submissions</a>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submissions/show.blade.php ENDPATH**/ ?>