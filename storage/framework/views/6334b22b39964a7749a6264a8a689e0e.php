<?php $__env->startSection('title', 'Study plans'); ?>
<?php $__env->startSection('page-title', 'Study plans'); ?>
<?php $__env->startSection('page-subtitle', 'A personal plan for every course'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-4xl space-y-6">
    <?php if(session('success')): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <?php echo $__env->make('student._help', [
        'title' => 'How to use study plans',
        'steps' => [
            'Choose a course and how many weeks you want to plan for.',
            'Click Generate study plan — the AI turns your course topics into week-by-week steps.',
            'Open the plan and work through each step in order.',
        ],
        'tip' => 'Plans can take a moment to generate; if a plan shows as pending, refresh shortly. Create a new plan any time your schedule changes.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <form method="POST" action="<?php echo e(route('study-plans.store')); ?>" class="space-y-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        <?php echo csrf_field(); ?>
        <h2 class="text-base font-black text-slate-900">Create a study plan</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-600">Course
                <select name="course_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Select a course</option>
                    <?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($course->id); ?>"><?php echo e($course->code); ?> — <?php echo e($course->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Weeks
                <input type="number" name="weeks" value="4" min="1" max="12" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </label>
        </div>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Generate study plan</button>
    </form>

    <section class="space-y-3">
        <?php $__empty_0 = true; $__currentLoopData = $plans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
            <a href="<?php echo e(route('study-plans.show', $plan)); ?>" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-indigo-200">
                <div>
                    <h3 class="font-black text-slate-900"><?php echo e($plan->title); ?></h3>
                    <p class="mt-0.5 text-xs text-slate-500"><?php echo e($plan->course?->code); ?> · <?php echo e($plan->itemCount()); ?> items · <?php echo e(str_replace('_', ' ', $plan->status)); ?></p>
                </div>
                <span class="text-sm font-black text-indigo-700">Open →</span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No study plans yet.</div>
        <?php endif; ?>
        <div class="pt-2"><?php echo e($plans->links()); ?></div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/study-plans.blade.php ENDPATH**/ ?>