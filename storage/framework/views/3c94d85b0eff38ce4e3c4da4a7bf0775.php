<?php $__env->startSection('title', 'Manage Knowledge Hub'); ?>
<?php $__env->startSection('page-title', 'Knowledge Hub publishing'); ?>
<?php $__env->startSection('page-subtitle', 'Create, review, submit and track every publication through moderation'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $statusStyles = [
        'draft' => 'bg-slate-100 text-slate-700 border-slate-200',
        'pending_review' => 'bg-amber-50 text-amber-700 border-amber-200',
        'changes_requested' => 'bg-orange-50 text-orange-700 border-orange-200',
        'published' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
        'unpublished' => 'bg-violet-50 text-violet-700 border-violet-200',
        'scheduled' => 'bg-blue-50 text-blue-700 border-blue-200',
    ];
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-700 px-6 py-7 text-white md:px-8">
            <div class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div><p class="text-xs font-black uppercase tracking-[.22em] text-indigo-200">Creator Workspace</p><h1 class="mt-2 text-3xl font-black">Publication studio</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">Open every publication regardless of workflow state, edit when permitted, and follow moderation without losing access to your own work.</p></div>
                <div class="flex flex-wrap gap-2"><a href="<?php echo e(route('knowledge.index')); ?>" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-bold hover:bg-white/20">Browse Hub</a><a href="<?php echo e(route('knowledge.promotions.index')); ?>" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-bold hover:bg-white/20">Promote work</a><a href="<?php echo e(route('commerce.wallet')); ?>" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-bold hover:bg-white/20">Earnings</a><a href="<?php echo e(route('knowledge.manage.create')); ?>" class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-800">New publication</a></div>
            </div>
        </div>
    </section>

    <?php if(session('success')): ?><div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900"><?php echo e(session('success')); ?></div><?php endif; ?>
    <?php if(session('info')): ?><div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-900"><?php echo e(session('info')); ?></div><?php endif; ?>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Published</p><p class="mt-2 text-2xl font-black"><?php echo e(number_format((int)($statusCounts['published'] ?? 0))); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e(number_format((int)($accessCounts['premium'] ?? 0))); ?> paid items</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Drafts</p><p class="mt-2 text-2xl font-black"><?php echo e(number_format((int)($statusCounts['draft'] ?? 0))); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e(number_format((int)($statusCounts['rejected'] ?? 0))); ?> rejected</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Readers</p><p class="mt-2 text-2xl font-black"><?php echo e(number_format((int)$engagement->reads)); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e(number_format((int)$engagement->saves)); ?> saves</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Sales</p><p class="mt-2 text-2xl font-black"><?php echo e(number_format((int)$sales->total_sales)); ?></p><p class="mt-1 text-xs text-slate-500">After commission</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Available</p><p class="mt-2 text-2xl font-black"><?php echo e(config('app.currency_symbol', '₦')); ?><?php echo e(number_format((float)($wallet?->available_balance ?? 0), 2)); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e(config('app.currency_symbol', '₦')); ?><?php echo e(number_format((float)($wallet?->pending_balance ?? 0), 2)); ?> pending</p></div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Promotion</p><p class="mt-2 text-2xl font-black"><?php echo e(number_format((int)$campaignSummary->impressions)); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e(number_format((int)$campaignSummary->clicks)); ?> clicks</p></div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        <div class="rounded-3xl border bg-white p-5 shadow-sm"><div class="flex items-center justify-between"><div><h2 class="font-black">Story series</h2><p class="mt-1 text-sm text-slate-500"><?php echo e(number_format($seriesCount)); ?> series in your workspace</p></div><a href="<?php echo e(route('knowledge.series.manage')); ?>" class="rounded-xl border px-3 py-2 text-xs font-bold">Manage series</a></div></div>
        <div class="rounded-3xl border bg-white p-5 shadow-sm"><h2 class="font-black">Top publications</h2><div class="mt-3 divide-y"><?php $__empty_0 = true; $__currentLoopData = $topPublications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $top): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><a href="<?php echo e(route('knowledge.show', $top)); ?>" class="flex items-center justify-between gap-3 py-2 text-sm"><span class="truncate font-bold"><?php echo e($top->title); ?></span><span class="shrink-0 text-xs text-slate-500"><?php echo e(number_format($top->view_count)); ?> reads</span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="py-3 text-sm text-slate-500">Publish content to see reader performance.</p><?php endif; ?></div></div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="font-black text-slate-900">Your publishing pipeline</h2><p class="mt-1 text-sm text-slate-500">Filter by workflow status without changing ownership or access.</p></div>
            <form method="GET"><select name="status" class="rounded-xl border-slate-300 text-sm" onchange="this.form.submit()"><option value="">All statuses</option><?php $__currentLoopData = ['draft','pending_review','changes_requested','scheduled','published','rejected','unpublished']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($status); ?>" <?php if(request('status')===$status): echo 'selected'; endif; ?>><?php echo e(ucwords(str_replace('_',' ',$status))); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></form>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <?php $__empty_0 = true; $__currentLoopData = $publications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $publication): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
            <article class="group rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0"><p class="text-xs font-black uppercase tracking-wide text-indigo-600"><?php echo e(ucwords(str_replace('_',' ',$publication->content_type))); ?></p><h3 class="mt-1 line-clamp-2 text-lg font-black text-slate-900"><?php echo e($publication->title); ?></h3><p class="mt-2 text-xs text-slate-500">Updated <?php echo e(optional($publication->updated_at)->diffForHumans()); ?> <?php if($publication->sourceResearchProject): ?> · Linked research <?php endif; ?></p></div>
                    <span class="shrink-0 rounded-full border px-3 py-1 text-[11px] font-black <?php echo e($statusStyles[$publication->status] ?? 'border-slate-200 bg-slate-50 text-slate-600'); ?>"><?php echo e(ucwords(str_replace('_',' ',$publication->status))); ?></span>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-slate-50 p-3"><p class="text-lg font-black text-slate-900"><?php echo e(number_format($publication->view_count)); ?></p><p class="text-[11px] text-slate-500">Reads</p></div>
                    <div class="rounded-2xl bg-slate-50 p-3"><p class="text-lg font-black text-slate-900"><?php echo e(number_format($publication->bookmark_count)); ?></p><p class="text-[11px] text-slate-500">Saves</p></div>
                    <div class="rounded-2xl bg-slate-50 p-3"><p class="text-sm font-black text-slate-900"><?php echo e(ucfirst($publication->access_type)); ?></p><p class="text-[11px] text-slate-500">Access</p></div>
                </div>
                <?php if($publication->moderation_note): ?><div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"><span class="font-black">Moderation note:</span> <?php echo e($publication->moderation_note); ?></div><?php endif; ?>
                <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4">
                    <span class="text-xs text-slate-500"><?php echo e(ucfirst($publication->visibility)); ?> visibility</span>
                    <div class="flex gap-2">
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update',$publication)): ?><a href="<?php echo e(route('knowledge.manage.edit',$publication)); ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">Edit</a><?php endif; ?>
                        <a href="<?php echo e(route('knowledge.manage.show',$publication)); ?>" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-black text-white hover:bg-indigo-700">Open workspace</a>
                    </div>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
            <div class="lg:col-span-2 rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-2xl">✦</div><h3 class="mt-4 font-black text-slate-900">No publications found</h3><p class="mt-1 text-sm text-slate-500">Create a draft or choose a different status filter.</p><a href="<?php echo e(route('knowledge.manage.create')); ?>" class="mt-5 inline-flex rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Create publication</a></div>
        <?php endif; ?>
    </div>
    <?php echo e($publications->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/manage.blade.php ENDPATH**/ ?>