<?php $__env->startSection('title', $quiz->title); ?>
<?php $__env->startSection('page-title', $quiz->title); ?>
<?php $__env->startSection('page-subtitle', ($quiz->course?->code ?? '').' · Practice quiz'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2"><a href="<?php echo e($quiz->studySession ? route('study.show', $quiz->studySession) : route('study.index')); ?>" class="text-sm font-bold text-indigo-700">← <?php echo e($quiz->studySession ? 'Back to my source and explanation' : 'My Study Space'); ?></a><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">AI-generated practice · Unofficial</span></div>
    <?php if(session('success')): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <?php echo $__env->make('student._help', [
        'title' => 'Answering a practice quiz',
        'steps' => [
            'Read each question and choose or type your answer.',
            'Submit answers once — your score is shown and saved under Recent attempts.',
            'Review anything you missed, then take another quiz or open Mastery & readiness.',
        ],
        'tip' => 'This is practice only and does not change your official grades. Attempt every question before submitting so your score reflects your knowledge.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php if($errors->any()): ?><div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>

    <?php ($questions = is_array($quiz->questions) ? $quiz->questions : []); ?>

    <?php if($quiz->status === 'failed' || empty($questions)): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
            <?php echo e(\App\Support\Errors\UserFacingError::fromAiCode($quiz->error_code)['message']); ?> Choose a readable source or try again with a clearer topic.
        </div>
    <?php else: ?>
        <form method="POST" action="<?php echo e(route('practice-quizzes.submit', $quiz)); ?>" class="space-y-5">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="submission_token" value="<?php echo e(\Illuminate\Support\Str::uuid()); ?>">
            <?php $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Question <?php echo e($index + 1); ?> · <?php echo e($question['difficulty'] ?? 'medium'); ?></p>
                    <p class="mt-2 font-semibold text-slate-900"><?php echo e($question['question']); ?></p>
                    <div class="mt-3 space-y-2">
                        <?php if(!empty($question['options'])): ?>
                            <?php $__currentLoopData = $question['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                                    <input type="radio" name="answers[<?php echo e($index); ?>]" value="<?php echo e($option); ?>"> <?php echo e($option); ?>

                                </label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php else: ?>
                            <input type="text" name="answers[<?php echo e($index); ?>]" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Your answer">
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Submit answers</button>
        </form>
    <?php endif; ?>

    <?php if($attempts->isNotEmpty()): ?>
        <?php ($latestAttempt = $attempts->first()); ?>
        <section id="feedback" class="space-y-4 rounded-2xl border border-indigo-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-black">Your feedback</h2><p class="mt-1 text-sm text-slate-500"><?php echo e($latestAttempt->score); ?> of <?php echo e($latestAttempt->total); ?> correct on your latest attempt. Short written answers use exact wording checks.</p></div><span class="rounded-xl bg-indigo-50 px-3 py-2 text-lg font-black text-indigo-800"><?php echo e($latestAttempt->score); ?>/<?php echo e($latestAttempt->total); ?></span></div>
            <?php if($quiz->next_review_at): ?><div class="rounded-xl bg-indigo-50 p-3 text-sm text-indigo-900"><strong>Next revision <?php echo e($quiz->next_review_at->diffForHumans()); ?>.</strong> <?php echo e($latestAttempt->score < $latestAttempt->total ? 'Start with the questions you missed below.' : 'Leave a gap, then try recalling these answers again.'); ?> Saved practice needs no new AI request.</div><?php endif; ?>
            <?php $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php ($feedback = $latestAttempt->answers[$index] ?? []); ?>
                <article class="rounded-xl border p-4 <?php echo e(!empty($feedback['is_correct']) ? 'border-emerald-100 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40'); ?>"><p class="text-xs font-bold <?php echo e(!empty($feedback['is_correct']) ? 'text-emerald-700' : 'text-amber-800'); ?>">Question <?php echo e($index + 1); ?> · <?php echo e(!empty($feedback['is_correct']) ? 'Correct' : 'Revisit this'); ?></p><h3 class="mt-1 text-sm font-semibold"><?php echo e($question['question']); ?></h3><dl class="mt-3 space-y-1 text-sm"><div><dt class="inline font-semibold">Your answer:</dt> <dd class="inline"><?php echo e(($feedback['selected'] ?? '') ?: 'Not answered'); ?></dd></div><div><dt class="inline font-semibold">Expected answer:</dt> <dd class="inline"><?php echo e($feedback['expected'] ?? $question['answer']); ?></dd></div></dl><?php if(!empty($question['explanation'])): ?><p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700"><?php echo e($question['explanation']); ?></p><?php endif; ?> <?php if(!empty($question['source_excerpt'])): ?><blockquote class="mt-3 border-l-2 border-indigo-200 pl-3 text-xs leading-5 text-slate-600">Source passage: <?php echo e($question['source_excerpt']); ?></blockquote><?php endif; ?></article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php if($quiz->studySession): ?><a href="<?php echo e(route('study.show', $quiz->studySession)); ?>" class="inline-block text-sm font-bold text-indigo-700">Review the source and ask about what you missed →</a><?php elseif($quiz->material): ?><a href="<?php echo e(route('materials.show', [$quiz->course, $quiz->material])); ?>" class="inline-block text-sm font-bold text-indigo-700">Review the original source →</a><?php endif; ?>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-black text-slate-900">Recent attempts</h2>
            <div class="mt-3 space-y-2">
                <?php $__currentLoopData = $attempts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attempt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600"><?php echo e($attempt->completed_at?->diffForHumans()); ?></span>
                        <span class="font-black text-slate-900"><?php echo e($attempt->score); ?>/<?php echo e($attempt->total); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </section>
    <?php endif; ?>

    <a href="<?php echo e(route('practice-quizzes.index')); ?>" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All practice quizzes</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/practice-quiz.blade.php ENDPATH**/ ?>