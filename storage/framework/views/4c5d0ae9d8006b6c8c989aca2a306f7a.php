<?php $__env->startSection('title', $material->title); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <section class="overflow-hidden rounded-[2rem] border border-slate-800 bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 p-7 text-white shadow-xl sm:p-9">
        <a href="<?php echo e(route('materials.index', $course)); ?>" class="text-sm font-semibold text-indigo-200 hover:text-white">← Back to course materials</a>
        <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-4xl">
                <div class="flex flex-wrap gap-2">
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold"><?php echo e(str($material->type)->replace('_',' ')->headline()); ?></span>
                    <?php if($material->topic): ?><span class="rounded-full bg-cyan-400/15 px-3 py-1 text-xs font-bold text-cyan-200"><?php echo e($material->topic); ?></span><?php endif; ?>
                    <?php if($material->week_number): ?><span class="rounded-full bg-violet-400/15 px-3 py-1 text-xs font-bold text-violet-200">Week <?php echo e($material->week_number); ?></span><?php endif; ?>
                    <?php if(!$material->is_visible): ?><span class="rounded-full bg-amber-400/15 px-3 py-1 text-xs font-bold text-amber-200">Hidden from students</span><?php endif; ?>
                </div>
                <h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl"><?php echo e($material->title); ?></h1>
                <p class="mt-2 text-sm text-slate-300"><?php echo e($course->code); ?> · <?php echo e($course->name); ?></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?php echo e(route('materials.download', [$course, $material])); ?>" class="rounded-xl bg-white px-5 py-2.5 text-sm font-black text-slate-950 hover:bg-slate-100">Download</a>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('update', $material)): ?><a href="<?php echo e(route('lecturer.materials.edit', [$course, $material])); ?>" class="rounded-xl border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-bold hover:bg-white/20">Edit material</a><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,.8fr)]">
        <main class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <div class="flex items-center justify-between gap-4"><h2 class="text-xl font-black text-slate-900">About this resource</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500"><?php echo e($material->download_count ?? 0); ?> downloads</span></div>
                <?php if($material->description): ?><p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600"><?php echo e($material->description); ?></p><?php else: ?><p class="mt-4 text-sm text-slate-500">No additional description was provided.</p><?php endif; ?>
                <div class="mt-6 grid gap-3 border-t border-slate-100 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 p-4"><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">File</p><p class="mt-1 break-all text-sm font-bold text-slate-800"><?php echo e($material->file_name ?: 'Digital resource'); ?></p></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Size</p><p class="mt-1 text-sm font-bold text-slate-800"><?php echo e($material->file_size ? number_format($material->file_size/1024,2).' KB' : '—'); ?></p></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><p class="text-[11px] font-black uppercase tracking-wider text-slate-400">Uploaded</p><p class="mt-1 text-sm font-bold text-slate-800"><?php echo e($material->created_at?->format('M j, Y') ?? '—'); ?></p></div>
                </div>
                <?php if($material->is_public): ?><div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">This material is marked as publicly accessible.</div><?php endif; ?>
            </section>

            <?php echo $__env->make('ai._contextual-assistant', ['assistantFeature' => 'material_assistant', 'assistantEndpoint' => route('ai.context.material',[$course,$material])], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><p class="text-xs font-black uppercase tracking-[.18em] text-indigo-600">Learning conversation</p><h2 class="mt-1 text-xl font-black text-slate-900">Questions & discussion</h2></div>
                    <?php if(auth()->guard()->check()): ?><a href="<?php echo e(route('discussions.create', $course)); ?>" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Start discussion</a><?php endif; ?>
                </div>
                <div class="mt-5 space-y-3">
                    <?php $__empty_1 = true; $__currentLoopData = $discussions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $discussion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <a href="<?php echo e(route('discussions.show', [$course, $discussion])); ?>" class="block rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/30">
                            <div class="flex items-start justify-between gap-4"><div><p class="font-bold text-slate-900"><?php echo e($discussion->title); ?></p><p class="mt-1 text-sm text-slate-500"><?php echo e($discussion->user?->full_name ?? $discussion->user?->name ?? 'Course member'); ?> · <?php echo e($discussion->created_at?->diffForHumans()); ?></p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600"><?php echo e($discussion->replies_count ?? 0); ?> replies</span></div>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No discussion has been linked to this material yet.</div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="space-y-5">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-black text-slate-900">Resource details</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Uploaded by</dt><dd class="mt-1 font-semibold text-slate-800"><?php echo e($material->uploader->full_name ?? $material->uploader->name ?? 'Unknown'); ?></dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">MIME type</dt><dd class="mt-1 break-all font-semibold text-slate-800"><?php echo e($material->mime_type ?: '—'); ?></dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Access</dt><dd class="mt-1 font-semibold text-slate-800"><?php echo e($material->requires_enrollment ? 'Course enrollment required' : ($material->is_public ? 'Public' : 'Course access')); ?></dd></div>
                </dl>
            </section>
            <?php if($material->accessLogs->count()): ?>
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Recent activity</h2><div class="mt-4 space-y-3"><?php $__currentLoopData = $material->accessLogs->take(8); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600"><?php echo e($log->user?->full_name ?? 'User'); ?> · <?php echo e($log->created_at?->diffForHumans()); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></section>
            <?php endif; ?>
        </aside>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/materials/show.blade.php ENDPATH**/ ?>