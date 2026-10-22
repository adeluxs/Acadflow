<?php $__env->startSection('title', 'Course Materials'); ?>
<?php $__env->startSection('page-title', 'Course Materials'); ?>
<?php $__env->startSection('page-subtitle', 'Materials available across your accessible courses'); ?>

<?php $__env->startSection('content'); ?>
<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
    <?php $__empty_0 = true; $__currentLoopData = $materials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
        <a href="<?php echo e(route('materials.show', [$material->course, $material])); ?>" class="block bg-white border border-slate-200 rounded-2xl p-5 hover:border-blue-300 hover:shadow-sm transition">
            <p class="text-xs font-semibold text-blue-600"><?php echo e($material->course->code); ?> · <?php echo e(str_replace('_', ' ', ucfirst($material->type))); ?></p>
            <h2 class="font-bold text-slate-900 mt-2"><?php echo e($material->title); ?></h2>
            <p class="text-sm text-slate-500 mt-2"><?php echo e(Str::limit($material->description, 120)); ?></p>
            <p class="text-xs text-slate-400 mt-4">Uploaded by <?php echo e($material->uploader->full_name); ?></p>
        </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
        <div class="md:col-span-2 xl:col-span-3 bg-white border border-dashed border-slate-300 rounded-2xl p-10 text-center text-slate-500">No accessible materials found.</div>
    <?php endif; ?>
</div>
<div class="mt-6"><?php echo e($materials->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/materials/all.blade.php ENDPATH**/ ?>