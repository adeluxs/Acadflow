<?php $__env->startSection('title','Research Impact Rankings'); ?>
<?php $__env->startSection('page-title','Research Impact Rankings'); ?>
<?php $__env->startSection('page-subtitle','Internal and provenance-separated external citation impact'); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('knowledge._nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="space-y-3"><?php $__currentLoopData = $rankings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rank): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a class="block rounded-2xl border bg-white p-5" href="<?php echo e(route('knowledge.show',$rank)); ?>"><div class="flex justify-between"><strong><?php echo e($rank->title); ?></strong><span><?php echo e($rank->internal_citations_count ?? 0); ?> internal · <?php echo e($rank->external_citations_count ?? 0); ?> external</span></div></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/citation-rankings.blade.php ENDPATH**/ ?>