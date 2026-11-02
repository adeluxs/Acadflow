<?php $__env->startSection('title', 'Campus Ambassador'); ?>
<?php $__env->startSection('page-title', 'Campus Ambassador'); ?>
<?php $__env->startSection('page-subtitle', 'Help your classmates. Grow your campus community.'); ?>
<?php $__env->startPush('styles'); ?><link rel="stylesheet" href="<?php echo e(asset('css/ambassadors.css')); ?>"><?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<div class="amb-shell">
    <?php echo $__env->make('ambassadors._own-referral', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="amb-hero"><p class="amb-eyebrow">AcadSyntra campus programme</p><h1>Your campus. Your contribution.</h1><p>Help students get started, share what you learn and turn their feedback into a better academic experience.</p></div>
    <?php if($application): ?>
        <section class="amb-card"><div class="amb-item-head"><div><h2><?php echo e($application->institution); ?></h2><p class="amb-muted"><?php echo e(collect([$application->faculty, $application->department, $application->study_level])->filter()->implode(' · ')); ?></p></div><span class="amb-badge amb-badge-<?php echo e($application->status); ?>"><?php echo e($application->statusLabel()); ?></span></div>
            <?php if($application->status === 'pending'): ?>
                <p class="amb-muted">Submitted <?php echo e(($application->submitted_at ?? $application->created_at)->format('j M Y')); ?>. The platform team reviews applications and replies here. Referral and reporting tools become available after approval.</p>
                <form method="POST" action="<?php echo e(route('ambassadors.withdraw')); ?>" class="amb-actions" onsubmit="return confirm('Withdraw your application? You can update and resubmit it later.')"><?php echo csrf_field(); ?><button class="amb-button">Withdraw application</button></form>
            <?php endif; ?>
            <?php if($application->status === 'approved'): ?><p class="amb-muted">You are an approved campus ambassador. Being an ambassador gives no access to student records or institution administration.</p><?php endif; ?>
            <?php if($application->status === 'suspended'): ?><p class="amb-muted">New referrals, activity submissions and reward fulfilment are paused. Contact the team if you need help with the review.</p><?php endif; ?>
            <?php if($application->status === 'rejected'): ?><p class="amb-muted">This application was not approved. You can update your details below and resubmit.</p><?php endif; ?>
            <?php if($application->status === 'withdrawn'): ?><p class="amb-muted">You withdrew this application. Update it below whenever you are ready.</p><?php endif; ?>
            <?php if($application->review_note): ?><p class="amb-note">Team feedback: <?php echo e($application->review_note); ?></p><?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if(! $application || in_array($application->status, \App\Models\AmbassadorApplication::RESUBMITTABLE, true)): ?>
        <?php if(auth()->user()->isAdmin()): ?><p class="amb-note">Applications are for student accounts. Platform administrators can manage the programme from Campus Ambassadors in the sidebar.</p>
        <?php elseif(! auth()->user()->isStudent()): ?><p class="amb-note">The campus ambassador programme is for student accounts. If you are a lecturer, you can still share AcadSyntra with colleagues from your own dashboard.</p>
        <?php else: ?> <?php echo $__env->make('ambassadors._application-form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?>
    <?php endif; ?>
    <?php if($application && in_array($application->status, ['approved', 'suspended'])): ?>
        <?php echo $__env->make('ambassadors._metrics', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
    <?php if($application?->status === 'approved'): ?>
        <?php
            $referralUrl = route('ambassadors.referral', $application->referral_code);
            $shareMessage = "I’m an AcadSyntra campus ambassador. Join me to explore tools for study, research and sharing academic resources. Participation is optional, and I may earn programme rewards from referrals. Get started: ".$referralUrl;
        ?>
        <div class="amb-grid amb-section">
            <section class="amb-card"><h2>Your invitation link</h2><p class="amb-muted">Share with interested classmates. New registrations through this link count towards your results.</p><div class="amb-fields"><label class="amb-field amb-wide">Select and copy your link<input readonly value="<?php echo e($referralUrl); ?>" onclick="this.select()"></label><label class="amb-field amb-wide">Ready-to-share message<textarea rows="5" readonly onclick="this.select()"><?php echo e($shareMessage); ?></textarea></label></div><div class="amb-actions"><a class="amb-button amb-button-primary" href="https://wa.me/?text=<?php echo e(rawurlencode($shareMessage)); ?>" target="_blank" rel="noopener noreferrer">Share on WhatsApp ↗</a></div><p class="amb-muted">Referral code: <?php echo e($application->referral_code); ?></p></section>
            <section class="amb-card"><h2>Your starter guide</h2><ol><li>Try the study or knowledge tools yourself.</li><li>Choose one useful task and invite a small group.</li><li>Run a 10-minute demo using materials you can share.</li><li>Help interested students sign up and complete onboarding.</li><li>Check in after a week and submit feedback.</li></ol><p class="amb-muted">Use aggregate attendance counts. Do not put classmates’ names, phone numbers, passwords or private academic records in your reports.</p><div class="amb-actions"><a href="<?php echo e(route('public.features')); ?>">Product overview</a><a href="<?php echo e(route('public.help')); ?>">Help centre</a><a href="<?php echo e(route('public.contact')); ?>">Contact the team</a></div></section>
        </div>
        <section class="amb-card amb-section" id="activities"><h2>Programme activities</h2>
            <p class="amb-muted">Activities published by the AcadSyntra team. Pick one when you report, or report your own useful activity.</p>
            <?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <article class="amb-item">
                    <div class="amb-item-head"><div><h3><?php echo e($task->title); ?></h3><p class="amb-muted"><?php echo e(\App\Models\AmbassadorReport::KINDS[$task->kind] ?? $task->kind); ?><?php if($task->ends_on): ?> · Open until <?php echo e($task->ends_on->format('j M Y')); ?><?php endif; ?> <?php if($task->requires_evidence): ?> · Supporting link or file needed <?php endif; ?></p></div></div>
                    <p class="amb-body"><?php echo e($task->summary); ?></p>
                    <?php if($task->instructions): ?><p class="amb-muted"><?php echo e($task->instructions); ?></p><?php endif; ?>
                    <?php if($task->resource_url): ?><p class="amb-muted"><a href="<?php echo e($task->resource_url); ?>" target="_blank" rel="noopener noreferrer">Open programme resource ↗</a></p><?php endif; ?>
                </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="amb-muted">No team activities are open right now. You can still report your own demo, onboarding session or student feedback below.</p><?php endif; ?>
        </section>
        <section class="amb-card amb-section"><h2>Report an activity or share feedback</h2>
            <form method="POST" action="<?php echo e(route('ambassadors.reports.store')); ?>" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="submission_key" value="<?php echo e(old('submission_key', (string) \Illuminate\Support\Str::uuid())); ?>">
                <div class="amb-fields">
                    <?php if($tasks->isNotEmpty()): ?><label class="amb-field amb-wide">Programme activity (optional)<select name="task"><option value="">My own activity</option><?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($task->uuid); ?>" <?php if(old('task') === $task->uuid): echo 'selected'; endif; ?>><?php echo e($task->title); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php $__errorArgs = ['task'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="amb-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label><?php endif; ?>
                    <label class="amb-field">Activity type<select name="kind" required><?php $__currentLoopData = \App\Models\AmbassadorReport::KINDS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('kind') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                    <label class="amb-field">Title<input name="title" value="<?php echo e(old('title')); ?>" maxlength="160" required></label>
                    <label class="amb-field">Activity date<input type="date" name="occurred_on" value="<?php echo e(old('occurred_on', now()->toDateString())); ?>" max="<?php echo e(now()->toDateString()); ?>" required></label>
                    <label class="amb-field">Participants<input type="number" name="participants" value="<?php echo e(old('participants', 0)); ?>" min="0" max="10000" required></label>
                    <label class="amb-field amb-wide">What happened? What did you learn?<textarea name="summary" rows="4" minlength="20" maxlength="5000" required><?php echo e(old('summary')); ?></textarea></label>
                    <label class="amb-field amb-wide">Supporting link (optional)<input type="url" name="evidence_url" value="<?php echo e(old('evidence_url')); ?>" maxlength="1000" placeholder="https://"><small>Only share content you have permission to share. The team can review the link.</small><?php $__errorArgs = ['evidence_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="amb-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                    <label class="amb-field amb-wide">Supporting file (optional)<input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"><small>JPG, PNG, WebP or PDF, up to 5 MB. Only the team and you can open it. Do not include classmates’ names, phone numbers or private records.</small><?php $__errorArgs = ['evidence_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="amb-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                </div><div class="amb-actions"><button class="amb-button amb-button-primary">Submit report</button></div>
            </form>
        </section>
    <?php endif; ?>
    <?php if($application): ?><?php echo $__env->make('ambassadors._reports', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php echo $__env->make('ambassadors._rewards', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/ambassadors/index.blade.php ENDPATH**/ ?>