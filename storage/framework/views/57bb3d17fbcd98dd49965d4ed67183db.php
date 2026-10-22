<?php $__env->startSection('title', 'Engagement Moderation'); ?>

<?php $__env->startSection('content'); ?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Engagement moderation</h1>
            <p class="text-muted mb-0">Human review of reported comments, discussions, and publications.</p>
        </div>
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select" aria-label="Report status">
                <option value="">All statuses</option>
                <?php $__currentLoopData = ['open', 'resolved', 'dismissed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>><?php echo e(ucfirst($status)); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <button class="btn btn-outline-primary">Filter</button>
        </form>
    </div>

    <?php $__empty_0 = true; $__currentLoopData = $reports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
        <article class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <span class="badge text-bg-<?php echo e($report->status === 'open' ? 'warning' : 'secondary'); ?>"><?php echo e(ucfirst($report->status)); ?></span>
                        <strong class="ms-2"><?php echo e(str_replace('_', ' ', ucfirst($report->reason))); ?></strong>
                    </div>
                    <small class="text-muted"><?php echo e($report->created_at?->diffForHumans()); ?></small>
                </div>
                <p class="mt-3 mb-1"><strong>Reporter:</strong> <?php echo e($report->reporter?->full_name ?? 'Deleted user'); ?></p>
                <p class="mb-1"><strong>Target:</strong> <?php echo e(class_basename($report->reportable_type)); ?> #<?php echo e($report->reportable_id); ?></p>
                <?php if($report->details): ?><p class="mb-3"><?php echo e($report->details); ?></p><?php endif; ?>

                <?php if($report->status === 'open'): ?>
                    <form method="POST" action="<?php echo e(route('moderation.engagement.review', $report)); ?>" class="row g-2">
                        <?php echo csrf_field(); ?>
                        <div class="col-md-3">
                            <select name="decision" class="form-select" required>
                                <option value="dismiss">Dismiss report</option>
                                <option value="hide">Hide comment</option>
                                <option value="restore">Restore comment</option>
                                <option value="lock_thread">Lock discussion</option>
                                <option value="unpublish">Unpublish publication</option>
                            </select>
                        </div>
                        <div class="col-md-7"><input name="resolution" class="form-control" maxlength="5000" required placeholder="Reason and evidence for the decision"></div>
                        <div class="col-md-2"><button class="btn btn-primary w-100">Record</button></div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border mt-3 mb-0">
                        <?php echo e($report->resolution); ?>

                        <small class="d-block text-muted">Reviewed by <?php echo e($report->reviewer?->full_name ?? 'administrator'); ?> <?php echo e($report->reviewed_at?->diffForHumans()); ?></small>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
        <div class="alert alert-info">No reports match this filter.</div>
    <?php endif; ?>

    <?php echo e($reports->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/moderation/engagement.blade.php ENDPATH**/ ?>