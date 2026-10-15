<?php $__env->startSection('title', 'Create Assignment'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Create New Assignment</h1>
        <p class="text-gray-600 mt-2">Course: <?php echo e($course->name); ?></p>
    </div>

    <!-- Form -->
    <form action="<?php echo e(route('submission-tasks.store', $course)); ?>" method="POST" class="bg-white rounded-lg shadow p-8">
        <?php echo csrf_field(); ?>
        
        <?php echo $__env->make('submission-tasks._form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submission-tasks/create.blade.php ENDPATH**/ ?>