<?php $__env->startSection('title', $publication->title); ?>
<?php $__env->startSection('page-title', 'Publication workspace'); ?>
<?php $__env->startSection('page-subtitle', 'Review status, content, moderation and available actions'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $editable = auth()->user()->can('update', $publication);
    $submittable = auth()->user()->can('submit', $publication);
    $moderatable = auth()->user()->can('moderate', $publication);
?>
<div class="mx-auto max-w-7xl space-y-6">
    <?php if(session('success')): ?><div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900"><?php echo e(session('success')); ?></div><?php endif; ?>
    <?php if(session('info')): ?><div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm font-medium text-blue-900"><?php echo e(session('info')); ?></div><?php endif; ?>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-700 px-6 py-8 text-white md:px-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-4xl"><div class="flex flex-wrap gap-2 text-[11px] font-black uppercase tracking-wide text-indigo-100"><span><?php echo e(ucwords(str_replace('_',' ',$publication->content_type))); ?></span><span>•</span><span><?php echo e(ucfirst($publication->visibility)); ?></span><span>•</span><span><?php echo e(ucfirst($publication->access_type)); ?></span></div><h1 class="mt-3 text-3xl font-black tracking-tight"><?php echo e($publication->title); ?></h1><p class="mt-3 text-sm leading-6 text-indigo-100"><?php echo e($publication->excerpt ?: 'No excerpt has been added yet.'); ?></p></div>
                <div class="flex flex-wrap gap-2"><a href="<?php echo e(route('knowledge.manage')); ?>" class="rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-bold">Back to publications</a><?php if($editable): ?><a href="<?php echo e(route('knowledge.manage.edit',$publication)); ?>" class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-800">Edit publication</a><?php endif; ?></div>
            </div>
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-4"><div class="bg-white p-4"><p class="text-xs text-slate-500">Status</p><p class="mt-1 font-black text-slate-900"><?php echo e(ucwords(str_replace('_',' ',$publication->status))); ?></p></div><div class="bg-white p-4"><p class="text-xs text-slate-500">Reads</p><p class="mt-1 font-black text-slate-900"><?php echo e(number_format($publication->view_count)); ?></p></div><div class="bg-white p-4"><p class="text-xs text-slate-500">Bookmarks</p><p class="mt-1 font-black text-slate-900"><?php echo e(number_format($publication->bookmark_count)); ?></p></div><div class="bg-white p-4"><p class="text-xs text-slate-500">Updated</p><p class="mt-1 font-black text-slate-900"><?php echo e(optional($publication->updated_at)->diffForHumans()); ?></p></div></div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <main class="space-y-6">
            <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-black text-slate-900">Publication content</h2><a href="<?php echo e(route('knowledge.manage.preview',$publication)); ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700">Preview</a></div>
                <div class="prose prose-slate mt-6 max-w-none leading-8"><?php echo $publication->document?->body ?: '<p class="text-slate-500">No content available.</p>'; ?></div>
            </article>
            <?php if($publication->document?->versions?->isNotEmpty()): ?>
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-black text-slate-900">Version history</h2><div class="mt-4 space-y-3"><?php $__currentLoopData = $publication->document->versions->sortByDesc('version_number')->take(8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $version): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50 p-4"><div><p class="text-sm font-bold text-slate-800">Version <?php echo e($version->version_number ?? $version->id); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e(optional($version->created_at)->format('M j, Y · H:i')); ?> <?php if($version->author): ?> · <?php echo e($version->author->full_name ?? $version->author->name); ?> <?php endif; ?></p></div><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update',$publication)): ?><form method="POST" action="<?php echo e(route('knowledge.manage.versions.restore',[$publication,$version])); ?>"><?php echo csrf_field(); ?><button class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold">Restore</button></form><?php endif; ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></section>
            <?php endif; ?>
        </main>

        <aside class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Workflow</h2><p class="mt-2 text-sm text-slate-500">Actions shown here are permission- and status-aware.</p>
                <?php if($publication->moderation_note): ?><div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><p class="font-black">Moderator feedback</p><p class="mt-1"><?php echo e($publication->moderation_note); ?></p></div><?php endif; ?>
                <?php if($submittable): ?><form method="POST" action="<?php echo e(route('knowledge.manage.submit',$publication)); ?>" class="mt-4"><?php echo csrf_field(); ?><button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white hover:bg-indigo-700">Submit for moderation</button></form><?php elseif($publication->status==='pending_review'): ?><div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900"><p class="font-black">Under review</p><p class="mt-1 text-xs leading-5">Your publication remains accessible here while moderators review it. Editing is locked until a decision is recorded.</p></div><?php elseif($publication->status==='published'): ?><div class="mt-4 rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900"><p class="font-black">Published</p><p class="mt-1 text-xs leading-5">This published version is read-only in the author workflow to protect moderation history.</p></div><?php endif; ?>
                <?php if($moderatable): ?><form method="POST" action="<?php echo e(route('knowledge.manage.moderate',$publication)); ?>" class="mt-5 space-y-3 border-t border-slate-100 pt-5"><?php echo csrf_field(); ?><select name="decision" class="w-full rounded-xl border-slate-300 text-sm"><option value="approve">Approve and publish</option><option value="request_changes">Request changes</option><option value="reject">Reject</option><option value="unpublish">Unpublish</option><option value="archive">Archive</option></select><textarea name="note" rows="3" class="w-full rounded-xl border-slate-300 text-sm" placeholder="Moderation note"></textarea><button class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-black text-white">Record moderation decision</button></form><?php endif; ?>
            </section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Metadata</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs text-slate-500">Creator</dt><dd class="mt-1 font-bold text-slate-800"><?php echo e($publication->creator?->full_name ?? $publication->creator?->name ?? 'Unknown'); ?></dd></div><div><dt class="text-xs text-slate-500">Category</dt><dd class="mt-1 font-bold text-slate-800"><?php echo e($publication->category?->name ?? 'Uncategorized'); ?></dd></div><div><dt class="text-xs text-slate-500">Tags</dt><dd class="mt-2 flex flex-wrap gap-1"><?php $__empty_0 = true; $__currentLoopData = $publication->tags; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><span class="rounded-full bg-indigo-50 px-2 py-1 text-[11px] font-bold text-indigo-700"><?php echo e($tag->name); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><span class="text-slate-400">None</span><?php endif; ?></dd></div></dl></section>
        </aside>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/manage-show.blade.php ENDPATH**/ ?>