<?php $__env->startSection('title', 'Mastery & readiness'); ?>
<?php $__env->startSection('page-title', 'Mastery & readiness'); ?>
<?php $__env->startSection('page-subtitle', 'See where you are strong and what to revise next'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-4xl space-y-6">
    <?php if(session('success')): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <?php echo $__env->make('student._help', [
        'title' => 'How to read this page',
        'steps' => [
            'Take practice quizzes so your mastery data can build up — flashcard reviews count too.',
            'Exam readiness (advisory) blends mastery, practice, deadlines and card reviews into one percentage.',
            'Focus next on lists your lowest-mastery topics — revise those first.',
            'After a quiz, click Refresh from practice to recompute your figures.',
        ],
        'tip' => 'Readiness is advisory only — always check your lecturer\'s requirements and your official marks.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="<?php echo e(route('mastery.index')); ?>" class="flex flex-wrap items-end gap-3">
            <label class="text-xs font-bold text-slate-600">Course
                <select name="course_id" class="mt-1.5 block rounded-xl border border-slate-300 px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">All courses</option>
                    <?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($course->id); ?>" <?php if(request('course_id') == $course->id): echo 'selected'; endif; ?>><?php echo e($course->code); ?> — <?php echo e($course->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
        </form>
        <form method="POST" action="<?php echo e(route('mastery.recompute')); ?>" class="flex items-end">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="course_id" value="<?php echo e(request('course_id')); ?>">
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700" <?php if(!request('course_id')): echo 'disabled'; endif; ?>>Refresh from practice</button>
        </form>
    </div>

    <?php if($readiness): ?>
        <section class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/60 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Exam readiness (advisory)</p>
                    <p class="mt-1 text-3xl font-black text-slate-900"><?php echo e($readiness->score); ?>%</p>
                </div>
                <span class="text-xs text-slate-500"><?php echo e($readiness->generated_at?->diffForHumans()); ?></span>
            </div>
            <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-4">
                <div class="rounded-xl bg-white p-3">Mastery: <strong><?php echo e($readiness->signals['mastery'] ?? 0); ?>%</strong></div>
                <div class="rounded-xl bg-white p-3">Practice: <strong><?php echo e($readiness->signals['practice'] ?? 0); ?>%</strong></div>
                <div class="rounded-xl bg-white p-3">Deadlines: <strong><?php echo e($readiness->signals['deadlines'] ?? 0); ?>%</strong></div>
                <div class="rounded-xl bg-white p-3">Card reviews: <strong><?php echo e($readiness->signals['flashcard_reviews'] ?? 0); ?></strong></div>
            </div>
        </section>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-black text-slate-900">Focus next on</h2>
        <div class="mt-3 space-y-2">
            <?php $__empty_0 = true; $__currentLoopData = $weak; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                <div class="flex items-center justify-between rounded-xl bg-rose-50 px-3 py-2 text-sm">
                    <span class="font-semibold text-slate-800"><?php echo e($topic->topic); ?> <?php if($topic->course): ?><span class="text-xs text-slate-500">· <?php echo e($topic->course->code); ?></span><?php endif; ?></span>
                    <span class="font-black text-rose-700"><?php echo e($topic->mastery); ?>%</span>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                <p class="text-sm text-slate-500">Take a practice quiz to build your mastery map.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-black text-slate-900">All topics</h2>
        <div class="mt-3 space-y-3">
            <?php $__empty_0 = true; $__currentLoopData = $topics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-600">
                        <span><?php echo e($topic->topic); ?> <?php if($topic->course): ?><span class="text-slate-400">· <?php echo e($topic->course->code); ?></span><?php endif; ?></span>
                        <span class="font-bold"><?php echo e($topic->mastery); ?>%</span>
                    </div>
                    <div class="mt-1 h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-indigo-500" style="width: <?php echo e($topic->mastery); ?>%"></div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                <p class="text-sm text-slate-500">No mastery data yet.</p>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/mastery.blade.php ENDPATH**/ ?>