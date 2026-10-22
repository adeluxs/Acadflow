<?php $__env->startSection('title', $deck->title); ?>
<?php $__env->startSection('page-title', $deck->title); ?>
<?php $__env->startSection('page-subtitle', ($deck->course?->code ?? '').' · Flashcard review'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-3xl space-y-6">
    <?php ($cards = is_array($deck->cards) ? $deck->cards : []); ?>

    <a href="<?php echo e(route('ai.assistant')); ?>" class="inline-flex w-fit items-center gap-1.5 text-xs font-bold text-indigo-700 hover:text-indigo-900">
        <span aria-hidden="true">&larr;</span> Back to AI Assistant
    </a>

    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs leading-5 text-indigo-900">
        <strong>Active recall:</strong> answer the question in your own words first, then reveal the answer and rate how well you did. Your rating schedules the next review (a rating below "Hard" sends the card back to the start).
    </div>

    <?php if(empty($cards)): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">This deck has no cards.</div>
    <?php else: ?>
        <section class="space-y-4" data-flashcard-deck data-deck-id="<?php echo e($deck->id); ?>">
            <?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-card data-card-index="<?php echo e($index); ?>">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Card <?php echo e($index + 1); ?> of <?php echo e(count($cards)); ?></p>
                    <p class="mt-2 text-lg font-black text-slate-900"><?php echo e($card['front'] ?? ''); ?></p>

                    <label class="mt-4 block text-xs font-semibold text-slate-600">
                        Your answer (attempt it first)
                        <textarea rows="2" data-recall maxlength="2000" class="mt-1.5 w-full resize-y rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm leading-6 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" placeholder="Type what you remember before revealing the answer… Press Enter to reveal."></textarea>
                    </label>

                    <div class="mt-3">
                        <button type="button" data-reveal class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Show answer</button>
                    </div>

                    
                    <div class="mt-3 hidden space-y-3" data-answer>
                        <p class="hidden rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 text-xs text-indigo-900" data-recall-echo></p>
                        <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-700" data-card-back><?php echo e($card['back'] ?? ''); ?></div>

                        
                        <div class="flex flex-wrap items-center gap-2">
                            <?php $__currentLoopData = [5 => 'Easy', 4 => 'Good', 3 => 'Hard', 1 => 'Forgot']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rating => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <form method="POST" action="<?php echo e(route('flashcards.review', $deck)); ?>">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="card_index" value="<?php echo e($index); ?>">
                                    <input type="hidden" name="rating" value="<?php echo e($rating); ?>">
                                    <button type="submit" class="rounded-xl px-3 py-2 text-xs font-bold
                                        class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                            'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' => $rating >= 4,
                                            'bg-amber-100 text-amber-700 hover:bg-amber-200' => $rating === 3,
                                            'bg-rose-100 text-rose-700 hover:bg-rose-200' => $rating < 3,
                                        ]); ?>""><?php echo e($label); ?></button>
                                </form>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                        <p class="text-[11px] text-slate-400">Rate your recall — this schedules the next review.</p>
                    </div>

                    <?php $__errorArgs = ['rating'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-2 text-xs font-semibold text-rose-600">Your rating was not saved. Please tap a rating button again.</p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </article>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </section>
    <?php endif; ?>

    <a href="<?php echo e(route('flashcards.index')); ?>" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All decks</a>
</div>

<script>
(() => {
    const deck = document.querySelector('[data-flashcard-deck]');
    const deckId = deck?.dataset.deckId || '0';
    const storageKey = (index) => 'acadflow:flashcard-recall:' + deckId + ':' + index;

    document.querySelectorAll('[data-card]').forEach((card) => {
        const index = card.dataset.cardIndex;
        const recall = card.querySelector('[data-recall]');
        const answer = card.querySelector('[data-answer]');
        const reveal = card.querySelector('[data-reveal]');
        const echo = card.querySelector('[data-recall-echo]');

        // Restore a previously typed attempt so a refresh or revisit does not lose it.
        try {
            const saved = window.localStorage.getItem(storageKey(index));
            if (saved && recall) recall.value = saved;
        } catch (e) {}

        recall?.addEventListener('input', () => {
            try { window.localStorage.setItem(storageKey(index), recall.value); } catch (e) {}
        });

        const doReveal = () => {
            const typed = (recall?.value || '').trim();
            if (echo && typed) {
                echo.textContent = 'Your answer: ' + typed;
                echo.classList.remove('hidden');
            }
            answer?.classList.remove('hidden');
            reveal?.classList.add('hidden');
            if (recall) recall.setAttribute('readonly', 'readonly');
        };

        reveal?.addEventListener('click', doReveal);

        // Enter reveals (Shift+Enter keeps a newline in the attempt box).
        recall?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                doReveal();
            }
        });
    });
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/student/flashcard-deck.blade.php ENDPATH**/ ?>