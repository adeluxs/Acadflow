<?php $__env->startSection('title', 'Research Studio'); ?>
<?php $__env->startSection('page-title', 'Research Studio'); ?>
<?php $__env->startSection('page-subtitle', 'Plan, write, review literature and manage research progress in one workspace'); ?>
<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    <?php if($institutionExpansionEnabled && $pendingInvitations->isNotEmpty()): ?>
        <section class="rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Pending supervision invitations</p>
                    <h2 class="mt-1 text-lg font-black text-indigo-950">Projects waiting for your response</h2>
                    <p class="mt-1 text-sm text-indigo-900/75">Accepting an invitation makes you the official supervisor for that project.</p>
                </div>
                <span class="rounded-full bg-white px-3 py-1 text-sm font-bold text-indigo-700 ring-1 ring-indigo-200"><?php echo e($pendingInvitations->count()); ?></span>
            </div>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <?php $__currentLoopData = $pendingInvitations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <article class="rounded-xl border border-indigo-100 bg-white p-4">
                        <p class="font-bold text-slate-950"><?php echo e($invitation->project?->title); ?></p>
                        <p class="mt-1 text-sm text-slate-500">Invited by <?php echo e($invitation->inviter?->full_name ?? $invitation->project?->owner?->full_name); ?></p>
                        <?php if($invitation->message): ?>
                            <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm leading-6 text-slate-700"><?php echo e($invitation->message); ?></p>
                        <?php endif; ?>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="<?php echo e(route('research.invitations.respond', $invitation)); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="decision" value="accept">
                                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Accept supervision</button>
                            </form>
                            <form method="POST" action="<?php echo e(route('research.invitations.respond', $invitation)); ?>">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="decision" value="decline">
                                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Decline</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex gap-2">
            <select name="status" class="rounded-xl border-slate-300 text-sm" onchange="this.form.submit()">
                <option value="">All stages</option>
                <?php $__currentLoopData = ['draft','topic_proposal','proposal_writing','main_writing','validation','supervisor_review','corrections','approved','archived']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($status); ?>" <?php if(request('status') === $status): echo 'selected'; endif; ?>><?php echo e(ucwords(str_replace('_',' ',$status))); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </form>
        <div class="flex flex-wrap gap-2">
            <?php if(auth()->user()->isAdmin()): ?>
                <a href="<?php echo e(route('research.configuration.index')); ?>" class="inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold">Configure types & workflows</a>
                <a href="<?php echo e(route('research.templates.index')); ?>" class="inline-flex items-center justify-center rounded-xl border px-4 py-2.5 text-sm font-semibold">Template versions</a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\ResearchProject::class)): ?>
                <a href="<?php echo e(route('research.create')); ?>" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">New research project</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <a href="<?php echo e(route('research.show', $project)); ?>" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700"><?php echo e($project->researchType?->name ?? 'Research'); ?></span>
                    <span class="text-xs font-medium text-slate-500"><?php echo e(number_format((float)$project->progress)); ?>%</span>
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-900"><?php echo e($project->title); ?></h3>
                <p class="mt-2 line-clamp-2 text-sm text-slate-500"><?php echo e($project->abstract ?: 'No abstract added yet.'); ?></p>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-blue-600" style="width: <?php echo e(min(100, (float)$project->progress)); ?>%"></div></div>
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                    <span><?php echo e(ucwords(str_replace('_',' ', $project->status))); ?></span>
                    <?php if($institutionExpansionEnabled && $project->workspace_mode === 'institutional'): ?>
                        <span><?php echo e($project->supervisor?->full_name ?? 'Supervisor invitation pending'); ?></span>
                    <?php else: ?>
                        <span><?php echo e($project->reference_links_count ?? 0); ?> references</span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="md:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <h3 class="font-semibold text-slate-900">No research projects yet</h3>
                <p class="mt-2 text-sm text-slate-500">Create a project to start writing, building your literature base and tracking research progress.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php echo e($projects->links()); ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/research/index.blade.php ENDPATH**/ ?>