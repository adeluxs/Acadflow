<?php $__env->startSection('title', $plan->title); ?>
<?php $__env->startSection('page-title', $plan->title); ?>
<?php $__env->startSection('page-subtitle', ($plan->course?->code ?? '').' · Study plan'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl space-y-6">
    <?php echo $__env->make('student._help', [
        'title' => 'Using your study plan',
        'steps' => [
            'Work through the steps in order — each shows a topic, an objective, an activity and an estimated time.',
            'Tick things off as you finish and revisit the plan through the week.',
            'Regenerate the plan if your deadlines or course coverage change.',
        ],
        'tip' => 'Aim for consistent short sessions rather than one long cram — the plan is a guide, not a rule.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php ($items = is_array($plan->items) ? $plan->items : []); ?>

    <?php if($plan->status !== 'ready' || empty($items)): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            <?php if($plan->status === 'failed'): ?>
                This plan could not be generated (<?php echo e($plan->error_code ?: 'no items returned'); ?>). Try again.
            <?php else: ?>
                Your plan is still being generated. Refresh in a moment.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <section class="space-y-3">
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Step <?php echo e($index + 1); ?> · <?php echo e($item['estimated_minutes'] ?? 30); ?> min</p>
                    <h3 class="mt-1 font-black text-slate-900"><?php echo e($item['topic'] ?? ''); ?></h3>
                    <?php if(!empty($item['objective'])): ?><p class="mt-1 text-sm text-slate-600"><strong>Objective:</strong> <?php echo e($item['objective']); ?></p><?php endif; ?>
                    <?php if(!empty($item['activity'])): ?><p class="mt-1 text-sm text-slate-600"><strong>Activity:</strong> <?php echo e($item['activity']); ?></p><?php endif; ?>
                </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>
    <?php endif; ?>

    <a href="<?php echo e(route('study-plans.index')); ?>" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All study plans</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/study-plan.blade.php ENDPATH**/ ?>