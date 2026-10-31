<?php
    $resume = $today['sessions']->first();
    $revision = $today['due']->first();
    $urgent = isset($pendingTasks) ? $pendingTasks->first(fn($task) => ($task->close_at ?? $task->due_date)?->lte(now()->addDays(2))) : null;
    $nextUrl = $urgent ? route('courses.assignments', $urgent->course) : ($revision ? route('practice-quizzes.show', $revision) : ($resume ? route('study.show', $resume) : route('study.index')));
    $nextTitle = $urgent ? $urgent->title : ($revision ? $revision->title : ($resume ? $resume->title() : 'Choose a course material or add your own notes'));
?>
<section class="overflow-hidden rounded-3xl border border-indigo-100 bg-white shadow-sm" data-study-today>
    <div class="grid lg:grid-cols-3">
        <div class="bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-6 text-white sm:p-7">
            <p class="text-xs font-bold uppercase tracking-widest text-indigo-200">Today · <?php echo e(now()->format('D, j M')); ?></p>
            <h2 class="mt-3 text-2xl font-black">One useful step forward.</h2>
            <p class="mt-3 text-sm leading-6 text-indigo-100"><?php echo e($urgent ? 'An assignment is closing soon. Give it your next study slot.' : ($revision ? 'A short revision is ready. Try recalling the answers before checking them.' : 'Pick up where you stopped, understand a passage, then test yourself.')); ?></p>
            <a href="<?php echo e($nextUrl); ?>" class="mt-5 block rounded-2xl border border-white/20 bg-white/10 p-4 hover:bg-white/20"><span class="text-[11px] font-bold uppercase tracking-wide text-indigo-200"><?php echo e($urgent ? 'Due soon' : ($revision ? 'Revise next' : 'Continue studying')); ?></span><strong class="mt-1 block text-sm"><?php echo e($nextTitle); ?></strong><span class="mt-3 block text-xs font-bold"><?php echo e($urgent ? 'Open assignment' : ($revision ? 'Start revision' : 'Open study space')); ?> →</span></a>
        </div>
        <div class="space-y-5 p-6 sm:p-7 lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-bold text-slate-950">Your study rhythm</h3><p class="mt-1 text-xs text-slate-500">Saved work, meaningful practice, and your next revision.</p></div><a href="<?php echo e(route('study.index')); ?>" class="text-sm font-bold text-indigo-700">My Study Space →</a></div>
            <?php if($today['aiAvailable']): ?>
            <div class="grid grid-cols-2 gap-3"><div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Practice in the last 7 days</p><p class="mt-1 text-xl font-black"><?php echo e((int)($today['progress']?->sessions ?? 0)); ?> <span class="text-xs font-medium text-slate-500">attempts</span></p></div><div class="rounded-2xl bg-indigo-50 p-4"><p class="text-xs text-indigo-700">Answers checked</p><p class="mt-1 text-xl font-black text-indigo-950"><?php echo e((int)($today['progress']?->correct ?? 0)); ?>/<?php echo e((int)($today['progress']?->total ?? 0)); ?> <span class="text-xs font-medium text-indigo-700">correct</span></p></div></div>
            <?php endif; ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Continue</h4><div class="mt-2 space-y-2"><?php $__empty_1 = true; $__currentLoopData = $today['sessions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $saved): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><a href="<?php echo e(route('study.show', $saved)); ?>" class="block rounded-xl border border-slate-100 p-3 hover:border-indigo-200"><span class="block truncate text-sm font-semibold"><?php echo e($saved->title()); ?></span><span class="text-xs text-slate-500"><?php echo e($saved->course?->code); ?> · <?php echo e($saved->last_studied_at?->diffForHumans()); ?></span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="mt-2 text-sm text-slate-500">Your first study session will appear here.</p><?php endif; ?></div></div>
                <?php if($today['aiAvailable']): ?><div><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Revision ready</h4><div class="mt-2 space-y-2"><?php $__empty_1 = true; $__currentLoopData = $today['due']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $due): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><a href="<?php echo e(route('practice-quizzes.show', $due)); ?>" class="block rounded-xl border border-indigo-100 bg-indigo-50/40 p-3"><span class="block text-sm font-semibold"><?php echo e($due->topic ?: $due->title); ?></span><span class="text-xs text-slate-500"><?php echo e($due->course?->code); ?> · Short saved practice</span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="mt-2 text-sm text-slate-500">Complete a practice quiz to schedule your next revision.</p><?php endif; ?></div></div><?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/study/_today.blade.php ENDPATH**/ ?>