<?php $__env->startSection('title','Paid Feature Access'); ?>
<?php $__env->startSection('page-title','Paid Feature Access'); ?>
<?php $__env->startSection('page-subtitle','See what is free, sponsored, fixed-access, pay-per-use, or AI-billed before you use it'); ?>
<?php $__env->startSection('content'); ?>
<?php if(session('success')): ?>
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div>
<?php endif; ?>
<?php if(isset($errors) && $errors->any()): ?>
    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div>
<?php endif; ?>

<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Spending balance</p><p class="mt-2 text-3xl font-black"><?php echo e($wallet->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$wallet->spending_balance_minor)); ?></p><a href="<?php echo e(route('commerce.wallet')); ?>" class="mt-3 inline-flex text-sm font-bold text-indigo-600">Fund wallet →</a></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Institution sponsorship</p><p class="mt-2 text-lg font-black"><?php echo e($commercial ? 'Eligible & active' : ($institutionEligible ? 'No active commercial account' : 'Not eligible')); ?></p><p class="mt-2 text-sm text-slate-500">Sponsored features/AI use the institution prepaid balance only for verified members of an eligible institution.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Billing safety</p><p class="mt-2 text-lg font-black">Reserve → complete → settle</p><p class="mt-2 text-sm text-slate-500">Pay-per-use and AI charges are reserved before execution and released if the protected operation fails.</p></div>
</div>

<section class="mt-6 rounded-3xl border bg-white p-6 shadow-sm">
    <div><p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">Non-AI features</p><h2 class="mt-1 text-xl font-black">Feature pricing</h2></div>
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <?php $__currentLoopData = $featureRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article id="feature-<?php echo e($row['key']); ?>" class="rounded-2xl border p-5 <?php echo e(request('feature') === $row['key'] ? 'border-indigo-400 ring-2 ring-indigo-100' : ''); ?>">
                <div class="flex items-start justify-between gap-4"><div><h3 class="font-black text-slate-900"><?php echo e($row['title']); ?></h3><p class="mt-1 text-sm leading-6 text-slate-500"><?php echo e($row['description']); ?></p></div>
                    <?php if(!$row['rule']): ?><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Free</span>
                    <?php elseif($row['sponsored']): ?><span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Institution sponsored</span>
                    <?php elseif($row['pricing_type']==='fixed_access' && $row['entitlement']): ?><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Active</span>
                    <?php else: ?><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">Paid</span><?php endif; ?>
                </div>
                <?php if($row['rule']): ?>
                    <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="font-bold"><?php echo e(str($row['pricing_type'])->replace('_',' ')->headline()); ?> · <?php echo e($row['rule']->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$row['rule']->unit_amount_minor)); ?></p>
                        <?php if($row['pricing_type']==='fixed_access'): ?><p class="mt-1 text-xs text-slate-500">Access duration: <?php echo e((int)data_get($row['rule']->metadata,'duration_days',0) > 0 ? data_get($row['rule']->metadata,'duration_days').' days' : 'No expiry'); ?></p><?php else: ?><p class="mt-1 text-xs text-slate-500">Charged per <?php echo e(data_get($row['rule']->metadata,'unit_label',$row['unit_label'] ?? 'use')); ?> after a successful operation.</p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if($row['rule'] && $row['pricing_type']==='fixed_access' && !$row['sponsored'] && !$row['entitlement']): ?>
                    <form method="POST" action="<?php echo e(route('commerce.features.purchase')); ?>" class="mt-4"><?php echo csrf_field(); ?><input type="hidden" name="feature" value="<?php echo e($row['key']); ?>"><input type="hidden" name="request_id" value="<?php echo e((string)\Illuminate\Support\Str::uuid()); ?>"><button class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white">Activate access</button></form>
                <?php elseif($row['entitlement']): ?>
                    <p class="mt-3 text-xs font-semibold text-emerald-700">Active<?php echo e($row['entitlement']->expires_at ? ' until '.$row['entitlement']->expires_at->format('d M Y, H:i') : ' with no expiry'); ?>.</p>
                <?php endif; ?>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>

<section class="mt-6 rounded-3xl border bg-white p-6 shadow-sm">
    <div><p class="text-xs font-black uppercase tracking-[.16em] text-violet-600">AI usage</p><h2 class="mt-1 text-xl font-black">Assistant pricing</h2><p class="mt-1 text-sm text-slate-500">Assistant-specific pricing overrides the global AI price. If no override exists, the global free allowance and request charge apply.</p></div>
    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <?php $__currentLoopData = $aiRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="rounded-2xl border p-4"><p class="font-black"><?php echo e($row['title']); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e($row['category']); ?></p>
                <?php if(!$row['pricing']['enabled']): ?><p class="mt-3 text-sm font-bold text-emerald-700">AI monetization disabled</p>
                <?php else: ?><p class="mt-3 text-sm font-bold"><?php echo e($row['pricing']['currency']); ?> <?php echo e(\App\Support\Money::fromMinor((int)$row['pricing']['amount_minor'])); ?> / successful request</p><p class="mt-1 text-xs text-slate-500"><?php echo e($row['pricing']['free_daily_requests']); ?> free successful request(s) per day<?php echo e($row['pricing']['feature_specific'] ? ' for this assistant' : ' across global AI pricing'); ?>.</p><?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="rounded-3xl border bg-white p-6 shadow-sm"><h2 class="text-lg font-black">Recent paid-feature usage</h2><div class="mt-3 divide-y"><?php $__empty_0 = true; $__currentLoopData = $featureUsage; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $usage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="py-3 text-sm"><div class="flex justify-between gap-3"><span class="font-semibold"><?php echo e(data_get(config('monetization.pricing_features.'.$usage->feature,[]),'title',str($usage->feature)->headline())); ?></span><span class="font-black"><?php echo e($usage->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$usage->total_amount_minor)); ?></span></div><p class="mt-1 text-xs text-slate-500"><?php echo e(str($usage->pricing_type)->replace('_',' ')->headline()); ?> · <?php echo e(str($usage->funding_source)->replace('_',' ')->headline()); ?> · <?php echo e(str($usage->status)->headline()); ?> · <?php echo e($usage->created_at?->format('d M Y, H:i')); ?></p></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="py-5 text-sm text-slate-500">No paid-feature usage yet.</p><?php endif; ?></div></section>
    <section class="rounded-3xl border bg-white p-6 shadow-sm"><h2 class="text-lg font-black">Recent AI billing</h2><div class="mt-3 divide-y"><?php $__empty_0 = true; $__currentLoopData = $aiUsage; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $usage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="py-3 text-sm"><div class="flex justify-between gap-3"><span class="font-semibold"><?php echo e(data_get(config('monetization.ai_features.'.$usage->feature,[]),'title',str($usage->feature)->headline())); ?></span><span class="font-black"><?php echo e($usage->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$usage->user_charge_minor)); ?></span></div><p class="mt-1 text-xs text-slate-500"><?php echo e($usage->provider ?: 'No provider charge'); ?> · <?php echo e(str(data_get($usage->metadata,'funding_source','platform'))->replace('_',' ')->headline()); ?> · <?php echo e(str($usage->status)->headline()); ?> · <?php echo e($usage->created_at?->format('d M Y, H:i')); ?></p></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="py-5 text-sm text-slate-500">No AI billing records yet.</p><?php endif; ?></div></section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/commerce/features.blade.php ENDPATH**/ ?>