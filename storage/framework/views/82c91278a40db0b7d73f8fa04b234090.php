<?php $__env->startSection('title', 'My Courses'); ?>
<?php $__env->startSection('page-title', 'My Courses'); ?>
<?php $__env->startSection('page-subtitle', 'Your active learning spaces, materials and assignments in one place'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $totalMaterials = $enrollments->sum(fn($e) => (int) ($e->course?->visible_materials_count ?? 0));
    $totalAssignments = $enrollments->sum(fn($e) => (int) ($e->course?->published_assignments_count ?? 0));
?>
<div class="mx-auto max-w-7xl space-y-6">
    <section class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-950 text-white shadow-xl shadow-slate-900/10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,.45),transparent_40%),radial-gradient(circle_at_bottom_left,rgba(14,165,233,.22),transparent_42%)]"></div>
        <div class="relative p-6 sm:p-8 lg:p-10">
            <div class="flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl"><p class="text-xs font-black uppercase tracking-[.24em] text-indigo-200">Student learning space</p><h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Everything for your semester, organized by course.</h1><p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">Open a course to continue with materials, assignments, discussions and attendance without searching across different parts of AcadFlow.</p></div>
                <div class="grid grid-cols-3 gap-2 sm:min-w-[390px]">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur"><p class="text-2xl font-black"><?php echo e($enrollments->count()); ?></p><p class="mt-1 text-[11px] text-slate-300">Active courses</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur"><p class="text-2xl font-black"><?php echo e($totalMaterials); ?></p><p class="mt-1 text-[11px] text-slate-300">Materials</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur"><p class="text-2xl font-black"><?php echo e($totalAssignments); ?></p><p class="mt-1 text-[11px] text-slate-300">Assignments</p></div>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        <?php $__empty_1 = true; $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $enrollment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($course = $enrollment->course); ?>
            <a href="<?php echo e(route('courses.show', $course)); ?>" class="group relative overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-950/5">
                <div class="absolute right-0 top-0 h-28 w-28 rounded-bl-[5rem] bg-gradient-to-br from-indigo-50 to-sky-50 transition group-hover:scale-110"></div>
                <div class="relative">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-500 text-sm font-black text-white shadow-lg shadow-indigo-500/20"><?php echo e(strtoupper(substr($course->code,0,3))); ?></div>
                        <span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-black text-emerald-700">Enrolled</span>
                    </div>
                    <p class="mt-5 text-xs font-black uppercase tracking-[.15em] text-indigo-600"><?php echo e($course->code); ?></p>
                    <h2 class="mt-1 line-clamp-2 text-xl font-black leading-7 text-slate-950"><?php echo e($course->name); ?></h2>
                    <p class="mt-2 text-sm text-slate-500"><?php echo e($course->department?->name ?? 'Department'); ?> <?php if($enrollment->semester): ?> · <?php echo e($enrollment->semester->name ?? $enrollment->semester->semester ?? 'Current semester'); ?> <?php endif; ?></p>
                    <div class="mt-5 grid grid-cols-3 gap-2">
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-base font-black text-slate-900"><?php echo e($course->credit_hours); ?></p><p class="text-[10px] uppercase tracking-wide text-slate-500">Credits</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-base font-black text-slate-900"><?php echo e($course->visible_materials_count ?? 0); ?></p><p class="text-[10px] uppercase tracking-wide text-slate-500">Materials</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-base font-black text-slate-900"><?php echo e($course->published_assignments_count ?? 0); ?></p><p class="text-[10px] uppercase tracking-wide text-slate-500">Tasks</p></div>
                    </div>
                    <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-sm"><span class="font-semibold text-slate-500"><?php echo e(ucfirst($course->level)); ?> · <?php echo e(ucfirst($course->type)); ?></span><span class="font-black text-indigo-700 transition group-hover:translate-x-1">Open course →</span></div>
                </div>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="md:col-span-2 xl:col-span-3 rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-2xl">◫</div><h2 class="mt-4 text-lg font-black text-slate-900">No active courses yet</h2><p class="mt-2 text-sm text-slate-500">Your enrolled courses will appear here when your institution adds you to the active semester.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/courses/index.blade.php ENDPATH**/ ?>