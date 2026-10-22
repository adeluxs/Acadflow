<?php $__env->startSection('title', 'Deadline plan'); ?>
<?php $__env->startSection('page-title', 'Deadline plan'); ?>
<?php $__env->startSection('page-subtitle', $plan->course?->code); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl space-y-6">
    <?php echo $__env->make('student._help', [
        'title' => 'Working the plan',
        'steps' => [
            'Start at Step 1 and work down the list; each step lists a task and suggested timing.',
            'Regenerate the plan from the deadline coach if anything changes.',
        ],
        'tip' => 'Do the earliest step first — big deadlines are much easier once they are split into small ones.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php ($items = is_array($plan->items) ? $plan->items : []); ?>

    <?php if($plan->summary): ?>
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 text-sm text-slate-700"><?php echo e($plan->summary); ?></div>
    <?php endif; ?>

    <?php if(empty($items)): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            No action plan was generated (<?php echo e($plan->error_code ?: 'no items returned'); ?>). Try again, or confirm a provider is configured in AI Settings.
        </div>
    <?php else: ?>
        <section class="space-y-3">
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Step <?php echo e($index + 1); ?> <?php if(!empty($item['due'])): ?>· due <?php echo e($item['due']); ?><?php endif; ?></p>
                    <h3 class="mt-1 font-black text-slate-900"><?php echo e($item['title'] ?? 'Task'); ?></h3>
                    <?php if(!empty($item['steps']) && is_array($item['steps'])): ?>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">
                            <?php $__currentLoopData = $item['steps']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li><?php echo e(is_array($step) ? json_encode($step) : $step); ?></li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>
    <?php endif; ?>

    <a href="<?php echo e(route('deadline-coach.index')); ?>" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All deadline plans</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/deadline-coach-show.blade.php ENDPATH**/ ?>