<?php $__env->startSection('title', 'Student Dashboard'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>
<?php $__env->startSection('page-subtitle', 'Welcome back, '.(auth()->user()->first_name ?? 'Student').' 👋'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $dashboardUser = auth()->user();
    $featureVisible = fn (string $feature): bool => \App\Services\FeatureAccessService::shouldShowInNavigation($feature, $dashboardUser);
?>
<div class="space-y-5">
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php $__currentLoopData = [
            ['My Courses',$enrollments->count(),'Enrolled courses','bg-indigo-50 text-indigo-600'],
            ['Pending Tasks',$pendingTasks->count(),'Assignments','bg-orange-50 text-orange-600'],
            ['My Submissions',$submissionCount,'All submissions','bg-emerald-50 text-emerald-600'],
            ['CGPA',$cgpa !== null ? number_format($cgpa,2) : '—','Current CGPA','bg-blue-50 text-blue-600'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <article class="acad-card p-4"><div class="flex items-start justify-between"><div><p class="text-xs text-slate-500"><?php echo e($stat[0]); ?></p><p class="mt-2 text-2xl font-black"><?php echo e($stat[1]); ?></p><p class="mt-1 text-[11px] text-slate-500"><?php echo e($stat[2]); ?></p></div><div class="flex h-9 w-9 items-center justify-center rounded-xl <?php echo e($stat[3]); ?>"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg></div></div></article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        <?php if($featureVisible('assignments')): ?>
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Upcoming Deadlines</h2><a href="<?php echo e(route('submissions.dashboard')); ?>" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 space-y-2">
            <?php $__empty_1 = true; $__currentLoopData = $pendingTasks->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php ($deadline=$task->close_at ?? $task->due_date); ?>
            <a href="<?php echo e(route('courses.assignments', $task->course)); ?>" class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-0"><div class="w-12 rounded-lg bg-indigo-50 p-2 text-center"><p class="text-[9px] font-bold uppercase text-indigo-500"><?php echo e($deadline?->format('M')); ?></p><p class="text-lg font-black text-indigo-800"><?php echo e($deadline?->format('d')); ?></p></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold"><?php echo e($task->title); ?></p><p class="text-[10px] text-slate-500"><?php echo e($task->course?->code); ?></p></div><span class="text-[10px] text-slate-500"><?php echo e($deadline?->format('g:i A')); ?></span></a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <p class="rounded-xl bg-slate-50 p-5 text-center text-xs text-slate-500">You're caught up. No pending deadlines.</p> <?php endif; ?>
        </div></article>
        <?php endif; ?>

        <?php if($featureVisible('submissions')): ?>
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">My Recent Submissions</h2><a href="<?php echo e(route('submissions.dashboard')); ?>" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 space-y-2">
            <?php $__empty_1 = true; $__currentLoopData = $submissions->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><a href="<?php echo e(route('submissions.show',$submission)); ?>" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M5 12l4 4L19 6"/></svg></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold"><?php echo e($submission->title); ?></p><p class="text-[10px] text-slate-500"><?php echo e($submission->course?->code); ?> · <?php echo e(str($submission->status)->headline()); ?></p></div><span class="text-[10px] text-slate-400"><?php echo e($submission->submitted_at?->format('M j') ?? $submission->created_at?->format('M j')); ?></span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="text-xs text-slate-500">No submissions yet.</p><?php endif; ?>
        </div></article>
        <?php endif; ?>
    </section>

    <?php if($featureVisible('courses')): ?>
    <section class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Course Overview</h2><a href="<?php echo e(route('courses.index')); ?>" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <?php $__empty_1 = true; $__currentLoopData = $courseProgress->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><a href="<?php echo e(route('courses.show',$item['course'])); ?>" class="rounded-xl border border-slate-100 p-4 text-center hover:border-indigo-200"><p class="truncate text-[11px] font-bold"><?php echo e($item['course']->name); ?></p><p class="mt-1 text-[10px] text-slate-500"><?php echo e($item['course']->code); ?></p><div class="acad-progress-ring mx-auto mt-3 flex h-20 w-20 items-center justify-center rounded-full" style="--progress:<?php echo e($item['progress']*3.6); ?>deg"><div class="flex h-14 w-14 items-center justify-center rounded-full bg-white"><span class="text-sm font-black"><?php echo e($item['progress']); ?>%</span></div></div><p class="mt-2 text-[10px] text-slate-500">Progress</p></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="col-span-full text-xs text-slate-500">No enrolled courses yet.</p><?php endif; ?>
    </div></section>
    <?php endif; ?>

    <section class="grid gap-5 xl:grid-cols-2">
        <?php if($featureVisible('ai_assistant')): ?>
        <article class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-white to-indigo-50 p-5 shadow-sm"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-950 text-xs font-bold text-white">AI</div><div><h2 class="text-sm font-bold">AI Academic Assistant</h2><p class="text-[10px] text-slate-500">Get help with your academic work</p></div></div><div class="mt-4 grid grid-cols-2 gap-2 text-xs"><a href="<?php echo e(route('submissions.create')); ?>" class="rounded-lg border bg-white p-3">Check my assignment</a><a href="<?php echo e(route('ai.assistant', ['tool' => 'ask'])); ?>" class="rounded-lg border bg-white p-3">Explain a topic</a><a href="<?php echo e(route('ai.assistant', ['tool' => 'writing'])); ?>" class="rounded-lg border bg-white p-3">Improve my writing</a><a href="<?php echo e(route('knowledge.index')); ?>" class="rounded-lg border bg-white p-3">Study resources</a></div><a href="<?php echo e(route('ai.assistant')); ?>" class="mt-4 flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white">Ask AI Assistant →</a></article>
        <?php endif; ?>

        <?php if($featureVisible('notifications')): ?>
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Announcements</h2><a href="<?php echo e(route('notifications.index')); ?>" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 space-y-2"><?php $__empty_1 = true; $__currentLoopData = $announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><a href="<?php echo e(route('notifications.index')); ?>" class="block rounded-xl border border-slate-100 p-3 hover:bg-slate-50"><p class="text-xs font-semibold"><?php echo e($notification->title); ?></p><p class="mt-1 line-clamp-1 text-[10px] text-slate-500"><?php echo e($notification->message); ?></p><p class="mt-1 text-[9px] text-slate-400"><?php echo e($notification->created_at?->diffForHumans()); ?></p></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="text-xs text-slate-500">No announcements yet.</p><?php endif; ?></div></article>
        <?php endif; ?>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/dashboard/student.blade.php ENDPATH**/ ?>