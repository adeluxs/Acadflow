<?php $__env->startSection('title', $quiz->title); ?>
<?php $__env->startSection('page-title', $quiz->title); ?>
<?php $__env->startSection('page-subtitle', ($quiz->course?->code ?? '').' · Practice quiz'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl space-y-6">
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

    <?php ($questions = is_array($quiz->questions) ? $quiz->questions : []); ?>

    <?php if($quiz->status === 'failed' || empty($questions)): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
            This quiz could not be generated (<?php echo e($quiz->error_code ?: 'no questions returned'); ?>). Try again with a clearer topic.
        </div>
    <?php else: ?>
        <form method="POST" action="<?php echo e(route('practice-quizzes.submit', $quiz)); ?>" class="space-y-5">
            <?php echo csrf_field(); ?>
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