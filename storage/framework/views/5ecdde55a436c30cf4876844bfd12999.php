<?php $__env->startSection('title', 'Practice quizzes'); ?>
<?php $__env->startSection('page-title', 'Practice quizzes'); ?>
<?php $__env->startSection('page-subtitle', 'Test yourself before it counts'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-4xl space-y-6">
    <?php if(session('success')): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div>
    <?php endif; ?>

    <?php echo $__env->make('student._help', [
        'title' => 'How to use practice quizzes',
        'steps' => [
            'Choose one of your enrolled courses and type the topic you want to practise.',
            'Set how many questions and a difficulty, then click Generate quiz — the questions are written from your course context.',
            'Open the quiz, answer every question, then Submit answers to see your score.',
            'Your score feeds into Mastery & readiness so you can see what to revise next.',
        ],
        'tip' => 'Start with one clear topic (for example “Foreign keys”) rather than a whole course — focused topics produce better questions. Practice never affects your official grades.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <form method="POST" action="<?php echo e(route('practice-quizzes.store')); ?>" class="space-y-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        <?php echo csrf_field(); ?>
        <h2 class="text-base font-black text-slate-900">Generate a practice quiz</h2>
        <?php $__errorArgs = ['topic'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php $__errorArgs = ['course_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-600">Course
                <select name="course_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Select a course</option>
                    <?php $__currentLoopData = $courses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $course): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($course->id); ?>"><?php echo e($course->code); ?> — <?php echo e($course->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Topic
                <input type="text" name="topic" required maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="e.g. Database normalization">
            </label>
            <label class="text-xs font-bold text-slate-600">Number of questions
                <input type="number" name="count" value="5" min="1" max="15" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </label>
            <label class="text-xs font-bold text-slate-600">Difficulty
                <select name="difficulty" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="mixed">Mixed</option>
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </label>
        </div>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Generate quiz</button>
    </form>

    <section class="space-y-3">
        <?php $__empty_0 = true; $__currentLoopData = $quizzes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quiz): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
            <a href="<?php echo e(route('practice-quizzes.show', $quiz)); ?>" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-indigo-200">
                <div>
                    <h3 class="font-black text-slate-900"><?php echo e($quiz->title); ?></h3>
                    <p class="mt-0.5 text-xs text-slate-500"><?php echo e($quiz->course?->code); ?> · <?php echo e($quiz->questionCount()); ?> questions · <?php echo e(str_replace('_', ' ', $quiz->status)); ?></p>
                </div>
                <span class="text-sm font-black text-indigo-700">Open →</span>
            </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No practice quizzes yet.</div>
        <?php endif; ?>
        <div class="pt-2"><?php echo e($quizzes->links()); ?></div>
    </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/practice-quizzes.blade.php ENDPATH**/ ?>