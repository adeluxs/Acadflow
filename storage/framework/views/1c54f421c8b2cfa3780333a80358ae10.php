<?php $__env->startSection('title', 'Lecturer Dashboard'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>
<?php $__env->startSection('page-subtitle', 'Welcome back, '.(auth()->user()->first_name ?? 'Lecturer').' 👋'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $overviewTotal = max(1, (int)($submissionOverview['total'] ?? 0));
    $pendingDeg = (($submissionOverview['pending'] ?? 0) / $overviewTotal) * 360;
    $reviewDeg = $pendingDeg + (($submissionOverview['in_review'] ?? 0) / $overviewTotal) * 360;
    $returnedDeg = $reviewDeg + (($submissionOverview['returned'] ?? 0) / $overviewTotal) * 360;
    $dashboardUser = auth()->user();
    $featureVisible = fn (string $feature): bool => \App\Services\FeatureAccessService::shouldShowInNavigation($feature, $dashboardUser);
?>
<div class="space-y-5">
    <section class="overflow-hidden rounded-3xl border border-indigo-100 bg-gradient-to-r from-slate-950 via-indigo-950 to-indigo-800 text-white shadow-sm" data-teaching-today><div class="grid gap-5 p-6 lg:grid-cols-[1fr_auto] lg:items-center"><div><p class="text-xs font-bold uppercase tracking-widest text-indigo-200">Teaching today</p><h2 class="mt-2 text-2xl font-black">Move one course forward.</h2><p class="mt-2 text-sm text-indigo-100"><?php echo e($pendingReviews ? $pendingReviews.' submission'.($pendingReviews === 1 ? '' : 's').' need feedback.' : ($teachingSignals['openQuestions'] ? $teachingSignals['openQuestions'].' open student question'.($teachingSignals['openQuestions'] === 1 ? '' : 's').' need a response.' : 'Your review queue is clear. Prepare the next useful teaching item.')); ?></p></div><div class="flex flex-wrap gap-2"><a href="<?php echo e(route('teaching.index')); ?>" class="rounded-xl bg-white px-5 py-3 text-sm font-black text-indigo-950">Open Teaching Studio →</a><?php if($pendingReviews): ?><a href="<?php echo e(route('submissions.lecturer-index', ['status' => 'submitted'])); ?>" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-bold">Review queue</a><?php endif; ?></div></div><div class="grid grid-cols-3 border-t border-white/10 bg-white/5 text-center"><div class="p-3"><strong class="block text-lg"><?php echo e($teachingSignals['openQuestions']); ?></strong><span class="text-[10px] text-indigo-200">Open questions</span></div><div class="border-x border-white/10 p-3"><strong class="block text-lg"><?php echo e($featureVisible('ai_assistant') ? $teachingSignals['readyDrafts'] : $courseCount); ?></strong><span class="text-[10px] text-indigo-200"><?php echo e($featureVisible('ai_assistant') ? 'Ready drafts' : 'Courses'); ?></span></div><div class="p-3"><strong class="block text-lg"><?php echo e($teachingSignals['coursesWithoutMaterial']); ?></strong><span class="text-[10px] text-indigo-200">Need material</span></div></div></section>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php $__currentLoopData = [
            ['label'=>'My Courses','value'=>$courseCount,'sub'=>'Active courses','icon'=>'book','classes'=>'bg-indigo-50 text-indigo-600'],
            ['label'=>'My Students','value'=>$studentCount,'sub'=>'Unique students','icon'=>'users','classes'=>'bg-emerald-50 text-emerald-600'],
            ['label'=>'Pending Reviews','value'=>$pendingReviews,'sub'=>'Submissions','icon'=>'file','classes'=>'bg-orange-50 text-orange-600'],
            ['label'=>'Average Class','value'=>$averageClass !== null ? number_format($averageClass, 1).'%' : '—','sub'=>'Graded work','icon'=>'chart','classes'=>'bg-blue-50 text-blue-600'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="acad-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div><p class="text-xs font-medium text-slate-500"><?php echo e($stat['label']); ?></p><p class="mt-2 text-2xl font-black tracking-tight text-slate-950"><?php echo e($stat['value']); ?></p><p class="mt-1 text-[11px] text-slate-500"><?php echo e($stat['sub']); ?></p></div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl <?php echo e($stat['classes']); ?>">
                        <?php if($stat['icon']==='book'): ?><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M5 4h5a3 3 0 013 3v13a3 3 0 00-3-3H5zM19 4h-5a3 3 0 00-3 3v13a3 3 0 013-3h5z"/></svg>
                        <?php elseif($stat['icon']==='users'): ?><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 10a4 4 0 100-8 4 4 0 000 8zm13 10v-2a4 4 0 00-3-3.87M16 2.13a4 4 0 010 7.75"/></svg>
                        <?php elseif($stat['icon']==='file'): ?><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h6M9 17h6"/></svg>
                        <?php else: ?><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg><?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </section>

    <?php if($featureVisible('submissions')): ?>
    <section class="grid gap-5 xl:grid-cols-2">
        <article class="acad-card p-5">
            <div class="flex items-center justify-between"><h2 class="text-sm font-bold text-slate-950">Submission Overview</h2><a href="<?php echo e(route('submissions.lecturer-index')); ?>" class="text-xs font-semibold acad-link">View all</a></div>
            <div class="mt-5 grid gap-5 sm:grid-cols-[180px_1fr] sm:items-center">
                <div class="mx-auto flex h-36 w-36 items-center justify-center rounded-full" style="background:conic-gradient(#f59e0b 0 <?php echo e($pendingDeg); ?>deg,#2563eb <?php echo e($pendingDeg); ?>deg <?php echo e($reviewDeg); ?>deg,#7c3aed <?php echo e($reviewDeg); ?>deg <?php echo e($returnedDeg); ?>deg,#10b981 <?php echo e($returnedDeg); ?>deg 360deg)">
                    <div class="flex h-24 w-24 flex-col items-center justify-center rounded-full bg-white shadow-inner"><span class="text-2xl font-black"><?php echo e($submissionOverview['total'] ?? 0); ?></span><span class="text-[10px] text-slate-500">Total</span></div>
                </div>
                <div class="space-y-3 text-xs">
                    <?php $__currentLoopData = [
                        ['Pending Review',$submissionOverview['pending'] ?? 0,'bg-amber-500'],
                        ['In Review',$submissionOverview['in_review'] ?? 0,'bg-blue-600'],
                        ['Returned',$submissionOverview['returned'] ?? 0,'bg-violet-600'],
                        ['Approved',$submissionOverview['approved'] ?? 0,'bg-emerald-500'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center gap-2 border-b border-slate-100 pb-2 last:border-0"><span class="h-2 w-2 rounded-full <?php echo e($row[2]); ?>"></span><span class="flex-1 text-slate-600"><?php echo e($row[0]); ?></span><strong class="text-slate-900"><?php echo e($row[1]); ?></strong></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </article>

        <article class="acad-card p-5">
            <div class="flex items-center justify-between"><h2 class="text-sm font-bold text-slate-950">Recent Submissions</h2><a href="<?php echo e(route('submissions.lecturer-index')); ?>" class="text-xs font-semibold acad-link">View all</a></div>
            <div class="mt-4 space-y-2">
                <?php $__empty_0 = true; $__currentLoopData = $recentSubmissions->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                <a href="<?php echo e(route('submissions.review', $submission)); ?>" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 transition hover:border-indigo-200 hover:bg-indigo-50/30">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M6 3h9l4 4v14H6zM9 13h6M9 17h4"/></svg></div>
                    <div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-900"><?php echo e($submission->title); ?></p><p class="mt-0.5 truncate text-[10px] text-slate-500"><?php echo e($submission->course?->code); ?> · <?php echo e($submission->user?->full_name); ?></p></div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-600"><?php echo e(str($submission->status)->headline()); ?></span>
                </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?> <p class="rounded-xl bg-slate-50 p-5 text-center text-xs text-slate-500">No submissions yet.</p> <?php endif; ?>
            </div>
        </article>
    </section>
    <?php endif; ?>

    <section class="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(280px,.55fr)]">
        <?php if($featureVisible('courses')): ?>
        <article class="acad-card p-5">
            <div class="flex items-center justify-between"><h2 class="text-sm font-bold">Course Performance</h2><a href="<?php echo e(route('lecturer.courses')); ?>" class="text-xs font-semibold acad-link">View courses</a></div>
            <?php if($courseCount > $courses->count()): ?><p class="mt-2 text-xs text-slate-500">Showing <?php echo e($courses->count()); ?> of <?php echo e($courseCount); ?> courses. Open View courses for your full list.</p><?php endif; ?>
            <div class="mt-5 space-y-4">
                <?php $__empty_0 = true; $__currentLoopData = $coursePerformance; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                <?php ($score = $item['average'] ?? 0); ?>
                <div><div class="mb-1.5 flex items-center justify-between text-xs"><span class="font-medium text-slate-700"><?php echo e($item['course']->code); ?> · <?php echo e($item['course']->name); ?></span><span class="font-bold text-slate-900"><?php echo e($item['average'] !== null ? number_format($item['average'],1).'%' : 'No grades'); ?></span></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-600" style="width:<?php echo e(min(100,max(0,$score))); ?>%"></div></div></div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?> <p class="text-xs text-slate-500">No assigned courses yet.</p> <?php endif; ?>
            </div>
        </article>
        <?php endif; ?>

        <?php if($featureVisible('ai_assistant')): ?>
        <article class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-white via-indigo-50/50 to-violet-100/70 p-5 shadow-sm">
            <div class="absolute -right-6 -top-8 h-28 w-28 rounded-full bg-indigo-200/40 blur-xl"></div>
            <div class="relative"><div class="flex items-center gap-2"><div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-950 text-white">AI</div><div><h2 class="text-sm font-bold">Teaching Studio</h2><p class="text-[10px] text-slate-500">Prepare, respond and review</p></div></div>
            <div class="mt-4 space-y-2 text-xs"><a href="<?php echo e(route('teaching.index')); ?>" class="block rounded-lg bg-white/90 px-3 py-2.5 shadow-sm">Teaching priorities</a><a href="<?php echo e(route('submissions.lecturer-index')); ?>" class="block rounded-lg bg-white/90 px-3 py-2.5 shadow-sm">Review submissions</a><a href="<?php echo e(route('ai.lecturer.layout.preferences')); ?>" class="block rounded-lg bg-white/90 px-3 py-2.5 shadow-sm">Set document standards</a></div>
            <a href="<?php echo e(route('teaching.index')); ?>" class="mt-4 flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white">Open Teaching Studio →</a></div>
        </article>
        <?php endif; ?>
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        <?php if($featureVisible('attendance') || $featureVisible('assignments')): ?>
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Upcoming Schedule</h2><a href="<?php echo e(route('attendance.lecturer')); ?>" class="text-xs font-semibold acad-link">Attendance</a></div><div class="mt-4 space-y-2">
            <?php $__empty_0 = true; $__currentLoopData = $upcomingTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><a href="<?php echo e(route('submission-tasks.lecturer.show', [$task->course, $task])); ?>" class="flex items-center gap-3 rounded-xl border-b border-slate-100 px-1 py-3 last:border-0"><div class="w-12 rounded-lg bg-indigo-50 p-2 text-center"><p class="text-[9px] font-bold uppercase text-indigo-500"><?php echo e(($task->close_at ?? $task->due_date)?->format('M')); ?></p><p class="text-lg font-black text-indigo-800"><?php echo e(($task->close_at ?? $task->due_date)?->format('d')); ?></p></div><div class="min-w-0"><p class="truncate text-xs font-semibold"><?php echo e($task->title); ?></p><p class="text-[10px] text-slate-500"><?php echo e($task->course?->code); ?> · <?php echo e(($task->close_at ?? $task->due_date)?->format('g:i A')); ?></p></div></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="text-xs text-slate-500">No upcoming deadlines.</p><?php endif; ?>
        </div></article>
        <?php endif; ?>

        <?php if($featureVisible('submissions')): ?>
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Recent Activity</h2><span class="text-[10px] text-slate-400">Latest course activity</span></div><div class="mt-4 space-y-3">
            <?php $__empty_0 = true; $__currentLoopData = $recentSubmissions->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="flex gap-3"><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><div class="min-w-0 flex-1"><p class="truncate text-xs font-medium">New submission: <?php echo e($submission->title); ?></p><p class="text-[10px] text-slate-500"><?php echo e($submission->course?->code); ?> · <?php echo e($submission->created_at?->diffForHumans()); ?></p></div></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="text-xs text-slate-500">No recent activity.</p><?php endif; ?>
        </div></article>
        <?php endif; ?>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/dashboard/lecturer.blade.php ENDPATH**/ ?>