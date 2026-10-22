<?php
    $profileUser = auth()->user();
    $profileUser->loadMissing('onboardingState');
?>
<?php if(!filled($profileUser->onboardingState?->data['_banner_dismissed_at'] ?? null)): ?>
<?php
    $profileCompletion = app(\App\Services\OnboardingService::class)->progress($profileUser);
    $nextProfileStep = array_key_first($profileCompletion['suggestions']);
?>
<?php if(!$profileCompletion['fully_completed'] && !$profileCompletion['banner_dismissed'] && $nextProfileStep): ?>
<section aria-label="Complete your profile" class="mb-5 rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 flex-1"><p class="font-bold text-slate-900">Your academic profile is <?php echo e($profileCompletion['percentage']); ?>% complete</p><p class="mt-1 text-sm text-slate-600">Add <?php echo e(implode(', ', array_slice($profileCompletion['suggestions'][$nextProfileStep]['missing_labels'], 0, 2))); ?> for a more personalised AcadSyntra experience.</p>
            <a href="<?php echo e($profileCompletion['steps'][$nextProfileStep]['url']); ?>" class="mt-3 inline-block text-sm font-bold text-blue-700">Complete your profile →</a><a href="<?php echo e(route('onboarding.manage')); ?>" class="ml-4 text-sm font-semibold text-slate-600">Manage onboarding</a></div>
        <form method="POST" action="<?php echo e(route('onboarding.dismiss')); ?>"><?php echo csrf_field(); ?><button class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Dismiss</button></form>
    </div>
    <div role="progressbar" aria-label="Profile completion" aria-valuenow="<?php echo e($profileCompletion['percentage']); ?>" aria-valuemin="0" aria-valuemax="100" class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-700" style="width: <?php echo e($profileCompletion['percentage']); ?>%"></div></div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/onboarding/_banner.blade.php ENDPATH**/ ?>