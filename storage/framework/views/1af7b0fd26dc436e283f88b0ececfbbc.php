<?php $__env->startSection('title','Certificate Verification'); ?>
<?php $__env->startSection('page-title','Certificate Verification'); ?>
<?php $__env->startSection('page-subtitle','Public authenticity check'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-2xl rounded-3xl border bg-white p-10 text-center"><p class="text-sm uppercase tracking-widest text-emerald-700">Verified academic certificate</p><h1 class="mt-4 text-3xl font-semibold"><?php echo e($certificate->title); ?></h1><p class="mt-4">Issued to <strong><?php echo e($certificate->user?->full_name); ?></strong></p><p class="mt-2 text-sm text-slate-500"><?php echo e($certificate->issuer); ?> · <?php echo e($certificate->issued_on?->format('F j, Y')); ?></p><code class="mt-6 block break-all rounded-xl bg-slate-100 p-3 text-xs"><?php echo e($certificate->verification_code); ?></code></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/certificate.blade.php ENDPATH**/ ?>