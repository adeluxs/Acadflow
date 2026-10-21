<?php $__env->startSection('title', 'Research export'); ?>
<?php $__env->startSection('page-title', 'Prepare research export'); ?>
<?php $__env->startSection('content'); ?>
<section class="studio-panel mx-auto max-w-2xl" data-export-progress="<?php echo e(route('research.exports.show', $export)); ?>" data-export-state="<?php echo e($status); ?>">
    <h1 class="studio-heading"><?php echo e($research->title); ?></h1>
    <p class="studio-muted mt-4" data-export-message role="status"><?php echo e(match($status) { 'completed' => 'Your export is ready.', 'failed' => 'The export could not be completed. Return to your workspace to retry.', 'processing' => 'Preparing your document in the background. You can continue working.', default => 'Your export is queued. You can continue working while it is prepared.' }); ?></p>
    <div class="mt-5 flex flex-wrap gap-3">
        <a class="studio-button" data-export-download href="<?php echo e($download ?? '#'); ?>" <?php if(!$download): ?> hidden <?php endif; ?>>Download document</a>
        <button class="studio-button-secondary" type="button" data-export-retry hidden>Check status again</button>
        <a class="studio-button-secondary" href="<?php echo e(route('research.show', $research)); ?>">Return to writing</a>
    </div>
    <noscript><p class="mt-4">Reload this page to check the export status.</p></noscript>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/research/export-progress.blade.php ENDPATH**/ ?>