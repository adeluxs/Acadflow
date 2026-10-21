<?php $__env->startSection('title','Citation Network'); ?>
<?php $__env->startSection('page-title','Citation Network'); ?>
<?php $__env->startSection('page-subtitle',$publication->title); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('knowledge._nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="rounded-2xl border bg-white p-6"><pre class="overflow-auto text-xs"><?php echo e(json_encode($graph, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); ?></pre></div>
<?php if(auth()->guard()->check()): ?><form method="POST" action="<?php echo e(route('knowledge.citations.rebuild',$publication)); ?>" class="mt-4"><?php echo csrf_field(); ?><button class="rounded-xl border px-4 py-2">Rebuild internal citations</button></form><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/citations.blade.php ENDPATH**/ ?>