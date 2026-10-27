<?php $__env->startSection('title', 'Promote your work'); ?>
<?php $__env->startSection('page-title', 'Knowledge Hub promotions'); ?>
<?php $__env->startSection('page-subtitle', 'Clearly labelled sponsored discovery for your published work'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-3xl bg-gradient-to-br from-slate-950 to-indigo-800 p-7 text-white">
        <p class="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Paid promotion</p>
        <h1 class="mt-2 text-3xl font-black">Reach more interested readers</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-indigo-100">Promotions appear as Sponsored placements. They increase discovery only and never add academic validation, peer review or credibility badges.</p>
    </section>
    <?php if(session('success')): ?>
        <div class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900"><?php echo e(session('success')); ?></div>
    <?php endif; ?>
    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Start a campaign</h2>
        <?php if($publications->isEmpty() || $packages->isEmpty()): ?>
            <p class="mt-3 text-sm text-slate-500">You need a public, published item and an active promotion package before you can start.</p>
        <?php else: ?>
            <form method="POST" action="<?php echo e(route('knowledge.promotions.store')); ?>" class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <?php echo csrf_field(); ?>
                <label class="text-sm font-bold">Published work<select name="knowledge_publication_id" required class="mt-2 w-full rounded-xl border-slate-300"><?php $__currentLoopData = $publications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $publication): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($publication->id); ?>"><?php echo e($publication->title); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                <label class="text-sm font-bold">Package<select name="knowledge_promotion_package_id" required class="mt-2 w-full rounded-xl border-slate-300"><?php $__currentLoopData = $packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($package->id); ?>"><?php echo e($package->name); ?> · <?php echo e($package->duration_days); ?> days · <?php echo e(config('app.currency_symbol', '₦')); ?><?php echo e(number_format((float)$package->price, 2)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                <button class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-black text-white">Continue to payment</button>
            </form>
        <?php endif; ?>
    </section>
    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Campaign performance</h2>
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-400"><tr><th class="p-3">Publication</th><th class="p-3">Status</th><th class="p-3">Schedule</th><th class="p-3">Impressions</th><th class="p-3">Clicks</th></tr></thead>
                <tbody class="divide-y">
                    <?php $__empty_0 = true; $__currentLoopData = $campaigns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaign): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                        <tr><td class="p-3"><p class="font-bold"><?php echo e($campaign->publication?->title); ?></p><p class="text-xs text-slate-500"><?php echo e($campaign->package?->name); ?></p></td><td class="p-3"><?php echo e(str($campaign->status)->headline()); ?></td><td class="p-3 text-xs text-slate-500"><?php echo e($campaign->starts_at?->format('M j') ?: 'After payment'); ?> — <?php echo e($campaign->ends_at?->format('M j, Y') ?: '—'); ?></td><td class="p-3 font-bold"><?php echo e(number_format($campaign->impressions)); ?></td><td class="p-3 font-bold"><?php echo e(number_format($campaign->clicks)); ?></td></tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                        <tr><td colspan="5" class="p-8 text-center text-slate-500">No promotion campaigns yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><?php echo e($campaigns->links()); ?></div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/promotions/index.blade.php ENDPATH**/ ?>