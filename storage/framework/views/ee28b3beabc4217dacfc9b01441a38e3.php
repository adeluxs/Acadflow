<?php $__env->startSection('title', 'Assignments - '.$course->name); ?>
<?php $__env->startSection('content'); ?>
<?php
    $openCount = $tasks->filter(fn($task) => (!$task->open_at || now()->gte($task->open_at)) && (!$task->late_deadline || now()->lte($task->late_deadline)))->count();
    $submittedCount = $tasks->filter(fn($task) => $task->submissions->where('user_id', auth()->id())->isNotEmpty())->count();
?>
<div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-indigo-950 via-slate-950 to-slate-900 p-7 text-white shadow-xl sm:p-9">
        <div class="absolute -right-20 top-0 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="text-xs font-black uppercase tracking-[.25em] text-indigo-300"><?php echo e($course->code); ?> · Coursework</p><h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Assignments</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Track instructions, deadlines, submissions, feedback and grades from one focused workspace.</p></div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur"><p class="text-2xl font-black"><?php echo e($tasks->count()); ?></p><p class="text-[11px] text-slate-300">Total</p></div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur"><p class="text-2xl font-black"><?php echo e($openCount); ?></p><p class="text-[11px] text-slate-300">Open</p></div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur"><p class="text-2xl font-black"><?php echo e($submittedCount); ?></p><p class="text-[11px] text-slate-300">Started</p></div>
            </div>
        </div>
    </section>

    <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <?php $__currentLoopData = [''=>'All','open'=>'Open','submitted'=>'Submitted','graded'=>'Graded']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('courses.assignments', array_merge(['course'=>$course->id], request()->except('status'), $value!=='' ? ['status'=>$value] : []))); ?>" class="whitespace-nowrap rounded-xl px-4 py-2 text-sm font-bold transition <?php echo e(request('status','')===$value ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100'); ?>"><?php echo e($label); ?></a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>

    <div class="grid gap-5 lg:grid-cols-2">
        <?php $__empty_1 = true; $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $mySubmission = $task->submissions->where('user_id', auth()->id())->first();
                $baseClose = $task->allow_late_submissions ? $task->late_deadline : ($task->due_date ?? $task->close_at);
                $effectiveClose = $task->acceptanceDeadline(auth()->user());
                $hasExtension = $effectiveClose && $baseClose && $effectiveClose->gt($baseClose);
                $isOpen = (!$task->open_at || now()->gte($task->open_at)) && (!$effectiveClose || now()->lte($effectiveClose));
                $typeAvailable = \App\Services\Assignments\AssignmentTypeProfile::isEnabled($task->type);
                $canSubmit = $isOpen && $typeAvailable && (!$mySubmission || $task->max_resubmissions===null || $mySubmission->resubmission_count < $task->max_resubmissions);
            ?>
            <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-xs font-black uppercase tracking-[.18em] text-indigo-600"><?php echo e(str($task->type)->headline()); ?></p><h2 class="mt-1 text-xl font-black text-slate-900 group-hover:text-indigo-700"><?php echo e($task->title); ?></h2></div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo e(!$typeAvailable ? 'bg-rose-50 text-rose-700' : ($isOpen ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600')); ?>"><?php echo e(!$typeAvailable ? 'Unavailable' : ($isOpen ? 'Open' : 'Closed')); ?></span>
                    </div>
                    <?php if($task->description): ?><p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600"><?php echo e($task->description); ?></p><?php endif; ?>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Due</p><p class="mt-1 text-sm font-bold <?php echo e($hasExtension ? 'text-indigo-700' : 'text-slate-800'); ?>"><?php echo e($effectiveClose?->format('M j') ?? '—'); ?></p><?php if($hasExtension): ?><p class="mt-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-600">Extended</p><?php endif; ?></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Points</p><p class="mt-1 text-sm font-bold text-slate-800"><?php echo e($task->max_score ?? '—'); ?></p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Files</p><p class="mt-1 text-sm font-bold text-slate-800"><?php echo e($task->min_file_count); ?>–<?php echo e($task->max_file_count); ?></p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Size</p><p class="mt-1 text-sm font-bold text-slate-800"><?php echo e($task->max_file_size_mb); ?> MB</p></div>
                    </div>

                    <?php if($mySubmission): ?>
                        <div class="mt-4 rounded-2xl border <?php echo e($mySubmission->grade ? 'border-violet-200 bg-violet-50' : 'border-emerald-200 bg-emerald-50'); ?> p-4">
                            <div class="flex items-center justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Your work</p><p class="mt-1 text-sm font-bold text-slate-900"><?php echo e($mySubmission->is_late ? 'Late · ' : ''); ?><?php echo e(str($mySubmission->status)->headline()); ?></p></div><?php if($mySubmission->grade): ?><p class="text-xl font-black text-violet-700"><?php echo e($mySubmission->grade->score); ?>/<?php echo e($task->max_score); ?></p><?php endif; ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                        <?php if($task->open_at && now()->lt($task->open_at)): ?><span class="rounded-lg bg-slate-100 px-2.5 py-1 text-slate-600">Opens <?php echo e($task->open_at->diffForHumans()); ?></span>
                        <?php elseif($task->due_date && now()->lt($task->due_date)): ?><span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-emerald-700">Due <?php echo e($task->due_date->diffForHumans()); ?></span>
                        <?php elseif($task->allow_late_submissions && $task->late_deadline && now()->lt($task->late_deadline)): ?><span class="rounded-lg bg-amber-50 px-2.5 py-1 text-amber-700">Late window until <?php echo e($task->late_deadline->format('M j')); ?></span>
                        <?php endif; ?>
                        <?php if($hasExtension): ?><span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-indigo-700">Extension granted · due <?php echo e($effectiveClose->format('M j, H:i')); ?></span><?php endif; ?>
                        <?php if($task->late_submission_penalty_percent > 0): ?><span class="rounded-lg bg-rose-50 px-2.5 py-1 text-rose-700"><?php echo e($task->late_submission_penalty_percent); ?>% late penalty</span><?php endif; ?>
                    </div>
                </div>
                <div class="flex gap-2 border-t border-slate-100 bg-slate-50/70 p-4">
                    <a href="<?php echo e(route('submission-tasks.student.show', ['course' => $course ?? $task->course_id, 'task' => $task])); ?>" class="flex-1 rounded-xl bg-slate-900 px-4 py-2.5 text-center text-sm font-black text-white hover:bg-slate-800">View assignment</a>
                    <?php if($canSubmit): ?><a href="<?php echo e(route('submissions.create')); ?>?task_id=<?php echo e($task->id); ?>" class="flex-1 rounded-xl bg-indigo-600 px-4 py-2.5 text-center text-sm font-black text-white hover:bg-indigo-700"><?php echo e($mySubmission ? 'Resubmit' : 'Submit work'); ?></a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="lg:col-span-2 rounded-[2rem] border border-dashed border-slate-300 bg-white p-14 text-center"><div class="text-4xl">🗂️</div><h2 class="mt-4 text-xl font-black text-slate-900">No assignments yet</h2><p class="mt-2 text-sm text-slate-500">New coursework will appear here when it is published.</p></div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submission-tasks/available.blade.php ENDPATH**/ ?>