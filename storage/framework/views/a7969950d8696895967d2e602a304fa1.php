<?php if($ownReferral): ?>
<section class="amb-card amb-section">
    <h2>Your invitation</h2>
    <p>Your account was created through an ambassador invitation. Your progress: <?php echo e($ownReferral->study_days); ?> study days; referral status: <?php echo e($ownReferral->qualification_status); ?>.</p>
    <p class="amb-muted">Referral qualification requires a verified email, completed onboarding, study actions on three separate days within your first 30 days, and at least one action after seven days. Saving a substantive study note or reflection, or submitting a practice quiz with at least three answers, can count. Repeated copies of the same work do not count. The platform team reviews referrals before any performance reward; your private work is not shared with the ambassador.</p>
    <?php if(! auth()->user()->hasVerifiedEmail()): ?>
        <form method="POST" action="<?php echo e(route('ambassadors.verify-email')); ?>"><?php echo csrf_field(); ?><button class="amb-button amb-button-primary">Send email verification link</button></form>
    <?php else: ?><p>Email verified.</p><?php endif; ?>
</section>
<?php endif; ?>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/ambassadors/_own-referral.blade.php ENDPATH**/ ?>