<?php $__env->startSection('title','Academic invitation'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-2xl acad-card p-6 sm:p-9">
    <p class="text-sm font-semibold text-indigo-600">Your next academic connection</p>
    <h1 class="mt-2 text-3xl font-bold">Join <?php echo e($invitation->department?->name ?? $invitation->university->name); ?></h1>
    <p class="mt-4 text-slate-600">You were invited as <?php echo e($invitation->role); ?> at <?php echo e($invitation->university->name); ?>. Accept with your existing account. Your personal research, documents and other affiliations remain yours.</p>
    <p class="mt-3 text-sm text-slate-500">This invitation is for <?php echo e($invitation->email); ?> and expires <?php echo e($invitation->expires_at->format('j M Y, H:i')); ?>.</p>
    <form method="POST" action="<?php echo e(route('academic.invitations.consume',$token)); ?>" class="mt-6"><?php echo csrf_field(); ?><button class="acad-primary-button">Accept invitation</button></form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/institution/accept-invitation.blade.php ENDPATH**/ ?>