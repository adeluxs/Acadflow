<?php
    $safeUiMessage = static fn ($value, $fallback) => \App\Support\Errors\UserFacingError::safeMessage(is_string($value) ? $value : null, $fallback);
?>

<?php if(session('status')): ?>
    <div class="mb-5 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" role="status">
        <?php echo e($safeUiMessage(session('status'), 'Your request has been updated.')); ?>

    </div>
<?php endif; ?>
<?php if(session('success')): ?>
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
        <?php echo e($safeUiMessage(session('success'), 'Completed successfully.')); ?>

    </div>
<?php endif; ?>
<?php if(session('error')): ?>
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
        <p class="font-semibold"><?php echo e($safeUiMessage(session('error'), 'We could not complete your request right now. Please try again.')); ?></p>
        <?php if(session('request_id')): ?>
            <p class="mt-1 text-xs font-normal text-rose-700/80">Request ID: <?php echo e(session('request_id')); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if(session('retry_after')): ?>
    <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900" aria-live="polite">
        <span data-rate-limit-countdown data-retry-after="<?php echo e((int) session('retry_after')); ?>">Please wait before trying again.</span>
    </div>
<?php endif; ?>
<?php if(isset($errors) && $errors->any()): ?>
    <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
        <p class="font-semibold">Please correct the highlighted information.</p>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($safeUiMessage($error, 'Please review this field and try again.')); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
<?php endif; ?>
<?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/partials/feedback.blade.php ENDPATH**/ ?>