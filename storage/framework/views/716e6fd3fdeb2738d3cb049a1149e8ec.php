<?php $__env->startSection('title', 'Join '.$course->code); ?>
<?php $__env->startSection('page-title', 'Join Course'); ?>
<?php $__env->startSection('page-subtitle', 'Confirm your enrollment using the course invitation link'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mx-auto bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
    <p class="text-sm font-semibold text-blue-600"><?php echo e($course->code); ?></p>
    <h1 class="text-2xl font-bold text-slate-900 mt-1"><?php echo e($course->name); ?></h1>
    <p class="text-slate-600 mt-3"><?php echo e($course->description ?: 'No course description has been provided.'); ?></p>
    <dl class="grid grid-cols-2 gap-4 mt-6 text-sm">
        <div><dt class="text-slate-500">Workspace</dt><dd class="font-medium"><?php echo e($course->isIndependent() ? 'Independent classroom' : $course->department?->name); ?></dd></div>
        <div><dt class="text-slate-500">Credit hours</dt><dd class="font-medium"><?php echo e($course->credit_hours); ?></dd></div>
    </dl>

    <?php if($alreadyEnrolled): ?>
        <a href="<?php echo e(route('courses.show', $course)); ?>" class="inline-flex mt-8 px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold">Open course</a>
    <?php else: ?>
        <form method="POST" action="<?php echo e(route('courses.join.link.process', $course->uuid)); ?>" class="mt-8">
            <?php echo csrf_field(); ?>
            <button class="px-5 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700">Join this course</button>
        </form>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/courses/join.blade.php ENDPATH**/ ?>