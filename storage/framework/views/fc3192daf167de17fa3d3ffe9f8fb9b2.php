<?php $__env->startSection('title', 'Create Research Project'); ?>
<?php $__env->startSection('page-title', 'Create research project'); ?>
<?php $__env->startSection('page-subtitle', 'Create a focused research workspace for planning, writing, literature and progress tracking'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $oldInstitutional = old('institutional_project') === '1' || old('workspace_mode') === 'institutional';
?>
<div class="mx-auto max-w-5xl space-y-5">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">01</div>
            <div>
                <h2 class="font-bold text-slate-950">Research workspace</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Start with the academic essentials. You can develop the title, literature base, sections, milestones and evidence progressively inside Research Studio.</p>
            </div>
        </div>
    </div>

    <form method="POST" action="<?php echo e(route('research.store')); ?>" class="space-y-6">
        <?php echo csrf_field(); ?>

        <?php if($institutionExpansionEnabled && auth()->user()->university_id): ?>
            <section class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="max-w-2xl">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-indigo-700">Institutional option</span>
                            <h2 class="font-bold text-indigo-950">Connect this project to your institution</h2>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-indigo-900/80">Turn this on only when the research should follow your institution/department structure. Institutional projects can invite an eligible lecturer to become supervisor after they accept.</p>
                    </div>
                    <label class="inline-flex cursor-pointer items-center gap-3 rounded-xl border border-indigo-200 bg-white px-4 py-3 text-sm font-bold text-indigo-900 shadow-sm">
                        <input id="institutional-project-toggle" type="checkbox" name="institutional_project" value="1" <?php if($oldInstitutional): echo 'checked'; endif; ?> class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                        Institutional project
                    </label>
                </div>

                <div id="institutional-project-fields" class="mt-5 grid gap-4 border-t border-indigo-100 pt-5 md:grid-cols-2 <?php echo e($oldInstitutional ? '' : 'hidden'); ?>">
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-800">Department</span>
                        <span class="mt-1 block text-xs text-slate-500">Used for institutional governance and department-level visibility.</span>
                        <select name="department_id" id="institutional-department" class="mt-2 w-full rounded-xl border-slate-300">
                            <option value="">Choose department</option>
                            <?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $department): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($department->id); ?>" <?php if(old('department_id', auth()->user()->department_id) == $department->id): echo 'selected'; endif; ?>><?php echo e($department->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-800">Invite supervisor</span>
                        <span class="mt-1 block text-xs text-slate-500">The lecturer receives an invitation and becomes supervisor only after accepting it.</span>
                        <select name="supervisor_invitee_id" id="supervisor-invitee" class="mt-2 w-full rounded-xl border-slate-300">
                            <option value="">Invite later</option>
                            <?php $__currentLoopData = $supervisors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $supervisor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($supervisor->id); ?>" <?php if(old('supervisor_invitee_id') == $supervisor->id): echo 'selected'; endif; ?>>
                                    <?php echo e($supervisor->full_name); ?><?php echo e($supervisor->department?->name ? ' · '.$supervisor->department->name : ''); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </label>

                    <label class="block md:col-span-2">
                        <span class="text-sm font-semibold text-slate-800">Invitation message <span class="font-normal text-slate-400">(optional)</span></span>
                        <textarea name="supervisor_message" rows="2" maxlength="1000" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Add a short note about the project or supervision request."><?php echo e(old('supervisor_message')); ?></textarea>
                    </label>
                </div>
            </section>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-800">Research type</span>
                    <select name="research_type_id" required class="mt-2 w-full rounded-xl border-slate-300">
                        <option value="">Choose research type</option>
                        <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($type->id); ?>" <?php if(old('research_type_id') == $type->id): echo 'selected'; endif; ?>><?php echo e($type->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-semibold text-slate-800">Expected completion</span>
                    <input type="date" name="expected_completion_date" value="<?php echo e(old('expected_completion_date')); ?>" class="mt-2 w-full rounded-xl border-slate-300">
                </label>
            </div>

            <label class="mt-5 block">
                <span class="text-sm font-semibold text-slate-800">Research title</span>
                <span class="mt-1 block text-xs text-slate-500">Use a precise working title. You can refine it later as the research develops.</span>
                <input name="title" value="<?php echo e(old('title')); ?>" required maxlength="255" class="mt-2 w-full rounded-xl border-slate-300" placeholder="e.g. Predicting student academic performance using machine learning">
            </label>

            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-semibold text-slate-800">Research area</span>
                    <input name="research_area" value="<?php echo e(old('research_area')); ?>" class="mt-2 w-full rounded-xl border-slate-300" placeholder="e.g. Educational data mining">
                </label>
                <label class="block">
                    <span class="text-sm font-semibold text-slate-800">Keywords</span>
                    <input name="keywords" value="<?php echo e(old('keywords')); ?>" class="mt-2 w-full rounded-xl border-slate-300" placeholder="education, analytics, machine learning">
                </label>
            </div>

            <label class="mt-5 block">
                <span class="text-sm font-semibold text-slate-800">Proposal summary / abstract</span>
                <span class="mt-1 block text-xs text-slate-500">Describe the problem, purpose, intended approach and expected contribution. A rough draft is fine.</span>
                <textarea name="abstract" rows="7" class="mt-2 w-full rounded-xl border-slate-300" placeholder="What problem are you studying, why does it matter, and how do you intend to investigate it?"><?php echo e(old('abstract')); ?></textarea>
            </label>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="<?php echo e(route('research.index')); ?>" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-center text-sm font-semibold text-slate-700">Cancel</a>
            <button class="rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700">Create research workspace</button>
        </div>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php if($institutionExpansionEnabled && auth()->user()->university_id): ?>
<?php $__env->startPush('scripts'); ?>
<script>
(() => {
    const toggle = document.getElementById('institutional-project-toggle');
    const fields = document.getElementById('institutional-project-fields');
    const department = document.getElementById('institutional-department');
    const supervisor = document.getElementById('supervisor-invitee');

    const syncInstitutionFields = () => {
        const enabled = Boolean(toggle?.checked);
        fields?.classList.toggle('hidden', !enabled);
        if (department) department.required = enabled;
        if (department) department.disabled = !enabled;
        if (supervisor) supervisor.disabled = !enabled;
    };

    toggle?.addEventListener('change', syncInstitutionFields);
    syncInstitutionFields();
})();
</script>
<?php $__env->stopPush(); ?>
<?php endif; ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/research/create.blade.php ENDPATH**/ ?>