<?php $__env->startSection('title', $task->title); ?>
<?php $__env->startSection('content'); ?>
<?php
    $mySubmission = $studentSubmissions->first();
    $isBeforeOpen = $task->open_at && now()->lt($task->open_at);
    $baseClose = $task->allow_late_submissions ? $task->late_deadline : ($task->due_date ?? $task->close_at);
    $effectiveClose = $task->acceptanceDeadline(auth()->user());
    $hasExtension = $effectiveClose && $baseClose && $effectiveClose->gt($baseClose);
    $isClosed = $effectiveClose && now()->gt($effectiveClose);
    $canResubmit = !$mySubmission || $task->max_resubmissions===null || $mySubmission->resubmission_count < $task->max_resubmissions;
    $typeAvailable = \App\Services\Assignments\AssignmentTypeProfile::isEnabled($task->type);
?>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <section class="rounded-[2rem] bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-7 text-white shadow-xl sm:p-9">
        <a href="<?php echo e(route('courses.assignments',$course)); ?>" class="text-sm font-semibold text-indigo-200 hover:text-white">← Back to assignments</a>
        <div class="mt-5 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-4xl"><div class="flex flex-wrap gap-2"><span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold"><?php echo e(str($task->type)->headline()); ?></span>
                <?php if(!$typeAvailable): ?>
                    <span class="rounded-full bg-rose-400/20 px-3 py-1 text-xs font-bold text-rose-100">Unavailable</span>
                <?php endif; ?>
                <?php if($hasExtension): ?>
                    <span class="rounded-full bg-indigo-400/20 px-3 py-1 text-xs font-bold text-indigo-100">Deadline extended</span>
                <?php endif; ?>
                <?php if($mySubmission): ?>
                    <span class="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-bold text-emerald-200"><?php echo e($mySubmission->is_late ? 'Late · ' : ''); ?><?php echo e(str($mySubmission->status)->headline()); ?></span>
                <?php else: ?>
                    <span class="rounded-full bg-amber-400/15 px-3 py-1 text-xs font-bold text-amber-200">Not submitted</span>
                <?php endif; ?>
            </div><h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl"><?php echo e($task->title); ?></h1><p class="mt-3 text-sm text-slate-300"><?php echo e($course->code); ?> · <?php echo e($course->name); ?></p></div>
            <div class="rounded-2xl border border-white/10 bg-white/10 px-5 py-4 text-center backdrop-blur"><p class="text-xs text-slate-300">Maximum score</p><p class="mt-1 text-3xl font-black"><?php echo e($task->max_score); ?></p></div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_340px]">
        <main class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7">
                <p class="text-xs font-black uppercase tracking-[.18em] text-indigo-600">Assignment brief</p><h2 class="mt-1 text-xl font-black text-slate-900">What you need to do</h2>
                <?php if($task->description): ?><p class="mt-4 whitespace-pre-line text-sm leading-7 text-slate-600"><?php echo e($task->description); ?></p><?php endif; ?>
                <?php if($task->instructions): ?><div class="mt-6 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-5"><p class="text-xs font-black uppercase tracking-wide text-indigo-600">Instructions</p><div class="mt-3 whitespace-pre-line text-sm leading-7 text-slate-700"><?php echo e($task->instructions); ?></div></div><?php endif; ?>
            </section>

            <?php echo $__env->make('ai._contextual-assistant', ['assistantFeature' => 'assignment_assistant', 'assistantEndpoint' => route('ai.context.assignment',[$course,$task])], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <?php if($task->attachments && $task->attachments->count()): ?>
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="text-xl font-black text-slate-900">Supporting materials</h2><div class="mt-4 grid gap-3 sm:grid-cols-2"><?php $__currentLoopData = $task->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('submission-tasks.attachment.download',[$course,$task,$attachment])); ?>" class="flex items-center justify-between rounded-2xl border border-slate-200 p-4 hover:border-indigo-200 hover:bg-indigo-50/30"><div><p class="font-bold text-slate-900"><?php echo e($attachment->file_name); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e(str($attachment->type)->headline()); ?></p></div><span class="text-sm font-bold text-indigo-600">Download</span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></section>
            <?php endif; ?>

            <?php if($studentSubmissions->isNotEmpty()): ?>
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-xl font-black text-slate-900">Your submissions</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"><?php echo e($studentSubmissions->count()); ?> version<?php echo e($studentSubmissions->count()===1?'':'s'); ?></span></div><div class="mt-5 space-y-4">
                    <?php $__currentLoopData = $studentSubmissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <article class="rounded-2xl border <?php echo e($sub->grade ? 'border-violet-200 bg-violet-50/40' : 'border-slate-200'); ?> p-5"><div class="flex items-start justify-between gap-4"><div><p class="font-black text-slate-900">Submission <?php echo e($loop->iteration); ?></p><p class="mt-1 text-xs text-slate-500"><?php if($sub->submitted_at): ?>Submitted <?php echo e($sub->submitted_at->diffForHumans()); ?><?php elseif(in_array($sub->status, ['draft', 'correction_requested'], true)): ?>Draft — not yet submitted <?php else: ?><?php echo e(str($sub->status)->headline()); ?><?php endif; ?> <?php if($sub->is_late): ?>· <span class="font-bold text-rose-600">Late</span><?php endif; ?></p></div><?php if($sub->grade): ?><p class="text-2xl font-black text-violet-700"><?php echo e($sub->grade->score); ?>/<?php echo e($task->max_score); ?></p><?php endif; ?></div>
                        <?php if($sub->versions->isNotEmpty()): ?><div class="mt-4 grid gap-2"><?php $__currentLoopData = $sub->versions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><a href="<?php echo e(route('submission-versions.download',$file)); ?>" class="flex items-center justify-between rounded-xl bg-white px-3 py-2 text-sm"><span class="font-semibold text-slate-700"><?php echo e($file->file_name); ?></span><span class="font-bold text-indigo-600">Download</span></a><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
                        <?php if($sub->comments && $sub->comments->count()): ?><div class="mt-4 space-y-2 border-t border-slate-200 pt-4"><?php $__currentLoopData = $sub->comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $comment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="rounded-xl bg-white p-3"><p class="text-xs font-bold text-slate-800"><?php echo e($comment->user->name ?? $comment->user->full_name ?? 'Reviewer'); ?> · <?php echo e($comment->created_at->format('M j, H:i')); ?></p><p class="mt-1 text-sm text-slate-600"><?php echo e($comment->content); ?></p></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
                        </article>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div></section>
            <?php endif; ?>
        </main>

        <aside class="space-y-5 xl:sticky xl:top-24 xl:self-start">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Timeline</h2><div class="mt-4 space-y-4 text-sm"><div><p class="text-xs font-bold uppercase text-slate-400">Opens</p><p class="mt-1 font-bold text-slate-800"><?php echo e($task->open_at?->format('M j, Y · H:i') ?? 'Immediately'); ?></p></div><div><p class="text-xs font-bold uppercase text-slate-400">Due</p><p class="mt-1 font-bold text-slate-800"><?php echo e($effectiveClose?->format('M j, Y · H:i') ?? 'Not set'); ?></p><?php if($hasExtension): ?><p class="text-xs font-semibold text-indigo-600">Your deadline was extended by your lecturer</p><?php endif; ?></div><div><p class="text-xs font-bold uppercase text-slate-400">Hard deadline</p><p class="mt-1 font-bold text-slate-800"><?php echo e($task->late_deadline?->format('M j, Y · H:i') ?? 'Not set'); ?></p></div></div></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-900">Submission requirements</h2><dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500">Files</dt><dd class="font-bold"><?php echo e($task->min_file_count); ?>–<?php echo e($task->max_file_count); ?></dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Max size</dt><dd class="font-bold"><?php echo e($task->max_file_size_mb); ?> MB</dd></div><div><dt class="text-slate-500">Formats</dt><dd class="mt-1 break-words font-bold"><?php echo e(implode(', ',array_map('strtoupper',$task->allowed_file_types ?? [])) ?: 'As instructed'); ?></dd></div><div class="flex justify-between"><dt class="text-slate-500">Resubmissions</dt><dd class="font-bold"><?php echo e($task->max_resubmissions ?? 'Unlimited'); ?></dd></div></dl><?php if($task->late_submission_penalty_percent>0): ?><div class="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">Late work may receive a <?php echo e($task->late_submission_penalty_percent); ?>% penalty.</div><?php endif; ?></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <?php if(!$typeAvailable): ?><div class="rounded-xl bg-rose-50 p-4 text-center text-sm font-bold text-rose-700">This assignment type is currently unavailable.</div>
                <?php elseif(!$isBeforeOpen && !$isClosed && $canResubmit): ?><a href="<?php echo e(route('submissions.create')); ?>?task_id=<?php echo e($task->id); ?>" class="block w-full rounded-xl bg-indigo-600 px-4 py-3 text-center text-sm font-black text-white hover:bg-indigo-700"><?php echo e($mySubmission ? 'Resubmit assignment' : 'Submit assignment'); ?></a>
                <?php elseif($isBeforeOpen): ?><div class="rounded-xl bg-slate-100 p-4 text-center text-sm font-bold text-slate-600">Opens <?php echo e($task->open_at->diffForHumans()); ?></div>
                <?php elseif($isClosed): ?><div class="rounded-xl bg-rose-50 p-4 text-center text-sm font-bold text-rose-700">Submission window closed</div>
                <?php else: ?><div class="rounded-xl bg-amber-50 p-4 text-center text-sm font-bold text-amber-700">Maximum resubmissions reached</div><?php endif; ?>
                <?php if($mySubmission && $mySubmission->status==='draft'): ?><form action="<?php echo e(route('submissions.submit',$mySubmission)); ?>" method="POST" class="mt-3"><?php echo csrf_field(); ?><button onclick="return confirm('Submit this assignment? You cannot edit it after submission.')" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-black text-white">Submit current draft</button></form><?php endif; ?>
            </section>
        </aside>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/submission-tasks/student-show.blade.php ENDPATH**/ ?>