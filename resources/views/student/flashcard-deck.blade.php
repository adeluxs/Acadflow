@extends('layouts.app')

@section('title', $deck->title)
@section('page-title', $deck->title)
@section('page-subtitle', ($deck->course?->code ?? '').' · Flashcard review')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @php($cards = is_array($deck->cards) ? $deck->cards : [])

    <a href="{{ route('ai.assistant') }}" class="inline-flex w-fit items-center gap-1.5 text-xs font-bold text-indigo-700 hover:text-indigo-900">
        <span aria-hidden="true">&larr;</span> Back to AI Assistant
    </a>

    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 text-xs leading-5 text-indigo-900">
        <strong>Active recall:</strong> answer the question in your own words first, then reveal the answer and rate how well you did. Your rating schedules the next review (a rating below "Hard" sends the card back to the start).
    </div>

    @if(empty($cards))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">This deck has no cards.</div>
    @else
        <section class="space-y-4" data-flashcard-deck data-deck-id="{{ $deck->id }}">
            @foreach($cards as $index => $card)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-card data-card-index="{{ $index }}">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Card {{ $index + 1 }} of {{ count($cards) }}</p>
                    <p class="mt-2 text-lg font-black text-slate-900">{{ $card['front'] ?? '' }}</p>

                    <label class="mt-4 block text-xs font-semibold text-slate-600">
                        Your answer (attempt it first)
                        <textarea rows="2" data-recall maxlength="2000" class="mt-1.5 w-full resize-y rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm leading-6 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" placeholder="Type what you remember before revealing the answer… Press Enter to reveal."></textarea>
                    </label>

                    <div class="mt-3">
                        <button type="button" data-reveal class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">Show answer</button>
                    </div>

                    {{-- The answer and rating controls stay hidden until the student
                         has attempted the question and revealed the answer. --}}
                    <div class="mt-3 hidden space-y-3" data-answer>
                        <p class="hidden rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 text-xs text-indigo-900" data-recall-echo></p>
                        <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-700" data-card-back>{{ $card['back'] ?? '' }}</div>

                        {{-- Each rating is a dedicated form carrying an explicit hidden
                             field so the value survives the global duplicate-submit guard
                             (which disables submit buttons on submit). --}}
                        <div class="flex flex-wrap items-center gap-2">
                            @foreach([5 => 'Easy', 4 => 'Good', 3 => 'Hard', 1 => 'Forgot'] as $rating => $label)
                                <form method="POST" action="{{ route('flashcards.review', $deck) }}">
                                    @csrf
                                    <input type="hidden" name="card_index" value="{{ $index }}">
                                    <input type="hidden" name="rating" value="{{ $rating }}">
                                    <button type="submit" class="rounded-xl px-3 py-2 text-xs font-bold
                                        @class([
                                            'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' => $rating >= 4,
                                            'bg-amber-100 text-amber-700 hover:bg-amber-200' => $rating === 3,
                                            'bg-rose-100 text-rose-700 hover:bg-rose-200' => $rating < 3,
                                        ])">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-slate-400">Rate your recall — this schedules the next review.</p>
                    </div>

                    @error('rating')
                        <p class="mt-2 text-xs font-semibold text-rose-600">Your rating was not saved. Please tap a rating button again.</p>
                    @enderror
                </article>
            @endforeach
        </section>
    @endif

    <a href="{{ route('flashcards.index') }}" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All decks</a>
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
@endsection
