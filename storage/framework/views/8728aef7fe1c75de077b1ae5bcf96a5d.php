<?php $__env->startSection('title', 'AI Analysis - ' . $submission->title); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold">AI Analysis</h2>
            <p class="text-gray-500"><?php echo e($submission->title); ?> • <?php echo e(ucfirst($submission->type)); ?></p>
        </div>
        <form method="POST" action="<?php echo e(route('ai.submission.reanalyze', $submission)); ?>">
            <?php echo csrf_field(); ?>
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">
                Re-run Analysis
            </button>
        </form>
    </div>

    <?php if($analyses->isEmpty()): ?>
        <div class="bg-white p-8 rounded-lg shadow text-center">
            <p class="text-gray-500">No AI analysis yet. It runs automatically after submission.</p>
        </div>
    <?php else: ?>
        <?php $__currentLoopData = $analyses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $analysis): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold capitalize">
                        <?php echo e(str_replace('_', ' ', $analysis->feature)); ?>

                    </h3>
                    <span class="px-3 py-1 rounded text-xs font-medium
                        <?php if($analysis->status === 'completed'): ?> bg-green-100 text-green-800
                        <?php elseif($analysis->status === 'failed'): ?> bg-red-100 text-red-800
                        <?php else: ?> bg-amber-100 text-amber-800 <?php endif; ?>">
                        <?php echo e(ucfirst($analysis->status)); ?>

                    </span>
                </div>

                <?php if($analysis->status === 'completed'): ?>
                    <?php if(!is_null($analysis->score)): ?>
                        <div class="mb-4">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl font-bold
                                    <?php if($analysis->score >= 75): ?> text-green-600
                                    <?php elseif($analysis->score >= 50): ?> text-amber-600
                                    <?php else: ?> text-red-600 <?php endif; ?>">
                                    <?php echo e($analysis->score); ?>/100
                                </div>
                                <div class="text-sm text-gray-500">Readiness Score</div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="h-2 rounded-full
                                    <?php if($analysis->score >= 75): ?> bg-green-500
                                    <?php elseif($analysis->score >= 50): ?> bg-amber-500
                                    <?php else: ?> bg-red-500 <?php endif; ?>"
                                    style="width: <?php echo e($analysis->score); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($analysis->summary): ?>
                        <p class="text-gray-700 mb-4"><?php echo e($analysis->summary); ?></p>
                    <?php endif; ?>

                    <?php if(!empty($analysis->issues)): ?>
                        <h4 class="font-medium mb-2">Issues & Suggestions</h4>
                        <ul class="space-y-3">
                            <?php $__currentLoopData = $analysis->issues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $issue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="border rounded p-3
                                    <?php if(($issue['severity'] ?? '') === 'critical'): ?> border-red-200 bg-red-50
                                    <?php elseif(($issue['severity'] ?? '') === 'warning'): ?> border-amber-200 bg-amber-50
                                    <?php else: ?> border-gray-200 bg-gray-50 <?php endif; ?>">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="font-medium text-sm">
                                            <?php echo e($issue['message'] ?? ''); ?>

                                        </span>
                                        <span class="text-xs px-2 py-0.5 rounded
                                            <?php if(($issue['severity'] ?? '') === 'critical'): ?> bg-red-200 text-red-800
                                            <?php elseif(($issue['severity'] ?? '') === 'warning'): ?> bg-amber-200 text-amber-800
                                            <?php else: ?> bg-gray-200 text-gray-700 <?php endif; ?>">
                                            <?php echo e(ucfirst($issue['severity'] ?? 'info')); ?>

                                        </span>
                                    </div>
                                    <?php if(!empty($issue['suggestion'])): ?>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <span class="font-medium">Fix:</span> <?php echo e($issue['suggestion']); ?>

                                        </p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-green-700 text-sm">No issues detected.</p>
                    <?php endif; ?>
                <?php elseif($analysis->status === 'queued' || $analysis->status === 'processing'): ?>
                    <p class="text-gray-500">Analysis is <?php echo e($analysis->status); ?>… refresh to see results.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php endif; ?>

    <a href="<?php echo e(route('submissions.show', $submission)); ?>" class="text-indigo-600 hover:underline text-sm">
        ← Back to submission
    </a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/ai/submission-analysis.blade.php ENDPATH**/ ?>