<?php $__env->startSection('title','Preview: '.$publication->title); ?>
<?php $__env->startSection('page-title','Publication Preview'); ?>
<?php $__env->startSection('page-subtitle','Private preview; this does not publish the content'); ?>
<?php $__env->startSection('content'); ?>
<article class="mx-auto max-w-4xl rounded-3xl border bg-white p-8"><span class="rounded-full bg-amber-50 px-3 py-1 text-xs text-amber-800"><?php echo e($publication->status); ?></span><h1 class="mt-4 text-4xl font-semibold"><?php echo e($publication->title); ?></h1><p class="mt-3 text-lg text-slate-500"><?php echo e($publication->excerpt); ?></p><div class="prose mt-8 max-w-none"><?php echo app(\App\Services\RichTextSanitizer::class)->sanitize((string) ($publication->document?->body ?? '')); ?></div></article>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/preview.blade.php ENDPATH**/ ?>