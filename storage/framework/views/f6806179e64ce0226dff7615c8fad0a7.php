<?php $__env->startSection('title', $session->title()); ?>
<?php $__env->startSection('page-title', 'Study: '.$session->title()); ?>
<?php $__env->startSection('content'); ?>
<?php
    $canUseAi = \App\Services\FeatureAccessService::canAccessFeature('ai_assistant', auth()->user());
    $runtime = app(\App\Services\Ai\AiRuntimeConfigService::class);
    $explainAvailable = $canUseAi && $runtime->featureEnabled('material_assistant', auth()->user()->university_id) && $runtime->mode(auth()->user()->university_id) !== \App\Enums\AiMode::DISABLED;
    $practiceAvailable = $canUseAi && $runtime->featureEnabled('practice_generator', auth()->user()->university_id) && $runtime->mode(auth()->user()->university_id) !== \App\Enums\AiMode::DISABLED;
?>
<div class="mx-auto max-w-7xl space-y-6" data-study-workspace>
    <a href="<?php echo e(route('study.index')); ?>" class="text-sm font-bold text-indigo-700">← My Study Space</a>
    <section class="rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-6 text-white sm:p-8"><p class="text-xs font-bold uppercase tracking-widest text-indigo-200"><?php echo e($session->course->code); ?> · <?php echo e($session->note_id ? 'Your private note' : 'Course material'); ?></p><h1 class="mt-2 text-2xl font-black sm:text-3xl"><?php echo e($session->title()); ?></h1><p class="mt-3 text-sm text-indigo-100">Understand one passage. Try five questions. Come back for a short revision.</p><ol class="mt-5 flex flex-wrap gap-3 text-xs font-bold"><li class="rounded-full bg-white/10 px-3 py-2">1 · Read</li><li class="rounded-full bg-white/10 px-3 py-2">2 · Understand <?php echo e($session->explained_at ? '✓' : ''); ?></li><li class="rounded-full bg-white/10 px-3 py-2">3 · Practise <?php echo e($quiz?->last_practiced_at ? '✓' : ''); ?></li><li class="rounded-full bg-white/10 px-3 py-2">4 · Revisit</li></ol></section>
    <?php if($errors->any()): ?><div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><?php endif; ?>
    <?php if(session('success')): ?><p class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"><?php echo e(session('success')); ?></p><?php endif; ?>
    <div class="grid items-start gap-6 xl:grid-cols-2">
        <section class="min-w-0 rounded-3xl border bg-white p-5 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-black">Read the source</h2><?php if($session->material): ?><a href="<?php echo e(route('materials.show', [$session->course, $session->material])); ?>" class="text-xs font-bold text-indigo-700">Open original material →</a><?php endif; ?></div><p class="mt-2 text-xs text-slate-500">Select a passage below to ask about it. This view shows up to 20,000 readable characters.</p><div id="study-source" class="mt-4 max-h-[36rem] select-text overflow-auto whitespace-pre-wrap break-words rounded-2xl bg-slate-50 p-4 text-sm leading-7 text-slate-700" data-study-source><?php echo e($text ?: 'Readable text is not available for this file. Open the original material or add a private text note in My Study Space.'); ?></div></section>
        <div class="min-w-0 space-y-5">
            <section class="rounded-3xl border bg-white p-5 sm:p-6"><h2 class="text-lg font-black">Help me understand</h2><p class="mt-1 text-sm text-slate-500">Ask about the source or highlight the part that feels confusing.</p>
                <?php if($explainAvailable && $text !== ''): ?><form method="POST" action="<?php echo e(route('study.explain', $session)); ?>" class="mt-4 space-y-3"><?php echo csrf_field(); ?><label class="block text-xs font-bold text-slate-600">Selected passage<textarea id="study-selection" name="selected_text" maxlength="2000" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="Select a passage in the source or paste an exact quote"><?php echo e(old('selected_text', $session->selected_text)); ?></textarea></label><label class="block text-xs font-bold text-slate-600">What would help?<textarea name="question" required maxlength="2000" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm"><?php echo e(old('question', $session->question ?: 'Explain this in simple terms, give one example, and ask me a self-check question.')); ?></textarea></label><p class="text-xs text-slate-500">AI explanations and new quizzes use your plan’s allowance. Check the source when an answer is uncertain.</p><button class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Explain this passage</button></form><?php else: ?><p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-500"><?php echo e($text === '' ? 'Choose a source with readable text to use explanations.' : 'AI explanations are currently unavailable. You can still read and keep your saved work.'); ?></p><?php endif; ?>
            </section>
            <?php if($session->explanation): ?><section id="explanation" class="rounded-3xl border border-indigo-200 bg-indigo-50/40 p-5 sm:p-6"><div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-lg font-black">Your explanation</h2><span class="text-xs text-slate-500">Saved <?php echo e($session->explained_at?->diffForHumans()); ?></span></div><?php if($sourceChanged): ?><p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">The source has changed since this explanation. Generate another explanation before relying on it.</p><?php endif; ?><div class="mt-4 whitespace-pre-wrap break-words text-sm leading-7 text-slate-800"><?php echo e($session->explanation); ?></div><p class="mt-4 border-t border-indigo-100 pt-3 text-xs text-slate-500">Source: <?php echo e($session->title()); ?>. Examples and explanations are AI-generated study help.</p></section><?php endif; ?>
            <section class="rounded-3xl border bg-white p-5 sm:p-6"><h2 class="text-lg font-black">Check what you understood</h2><p class="mt-1 text-sm text-slate-500">Answer first, then see explanations and what to revisit.</p><?php if($canUseAi && $quiz): ?><a href="<?php echo e(route('practice-quizzes.show', $quiz)); ?>" class="mt-4 block rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-bold text-white"><?php echo e($quiz->last_practiced_at ? 'Review my saved practice' : 'Continue my saved practice'); ?> →</a><?php if($quiz->next_review_at): ?><p class="mt-3 text-sm text-indigo-700">Next revision <?php echo e($quiz->next_review_at->diffForHumans()); ?>. Your dashboard will keep it ready.</p><?php endif; ?><p class="mt-2 text-xs text-slate-500">Retaking saved practice uses no AI generation.</p><?php endif; ?>
                <?php if($practiceAvailable && $text !== ''): ?><form method="POST" action="<?php echo e(route('study.practice', $session)); ?>" class="mt-4"><?php echo csrf_field(); ?><button class="w-full rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-bold text-indigo-800"><?php echo e($quiz ? 'Generate a fresh 5-question practice' : 'Test my understanding · 5 questions'); ?></button></form><?php elseif(!$canUseAi): ?><p class="mt-3 text-sm text-slate-500">Practice is currently unavailable.</p><?php endif; ?>
            </section>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('scripts'); ?>
<script>
(() => {
    const source = document.querySelector('[data-study-source]');
    const field = document.getElementById('study-selection');
    if (!source || !field) return;
    source.addEventListener('mouseup', () => {
        const selection = window.getSelection();
        if (selection && source.contains(selection.anchorNode) && source.contains(selection.focusNode) && selection.toString().trim()) field.value = selection.toString().trim().slice(0, 2000);
    });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/study/show.blade.php ENDPATH**/ ?>