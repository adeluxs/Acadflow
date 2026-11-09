@php
    $quizQuestions = $readEarn['questions'];
    $quizAttempt = $readEarn['attempt'];
    $isCreator = auth()->check() && $publication->creator_id === auth()->id();
    $needsPass = $readEarn['requires_pass'] && ! $readEarn['has_pass'];
@endphp
@auth
@if($isCreator)
    <section id="read-earn" class="rounded-[2rem] border border-dashed border-emerald-300 bg-emerald-50/60 p-6 sm:p-8">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Read &amp; Earn</p>
        @php($quizState = $readEarn['quiz_status'] ?? null)
        <h2 class="mt-1 text-xl font-black text-slate-950">
            @if($quizQuestions->isNotEmpty()){{ $quizQuestions->count() }} question{{ $quizQuestions->count() === 1 ? '' : 's' }} in this guide's bank
            @elseif(($readEarn['ai_questions'] ?? false) && in_array($quizState, [null, 'pending'], true) && $publication->status === 'published')AI is writing this guide's questions…
            @elseif($quizState === 'awaiting_approval')AI questions are waiting for your approval
            @else Add quiz questions so readers can earn from this guide @endif
        </h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">@if($readEarn['ai_questions'] ?? false)AcadSyntra writes the questions from your guide for you. @endif Each reader gets a different random set with shuffled answers, so answers can't be shared. You can edit, remove or add questions any time. Readers who pass count as verified reads for the monthly creator pool.</p>
        <a href="{{ route('knowledge.rewards.questions.edit', $publication) }}" class="mt-4 inline-flex rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white">{{ $quizState === 'awaiting_approval' ? 'Review and approve questions' : 'Review Read & Earn questions' }}</a>
        <a href="{{ route('knowledge.manage.readers', $publication) }}" class="mt-4 ml-2 inline-flex rounded-xl border border-emerald-300 bg-white px-4 py-2.5 text-sm font-black text-emerald-800">📊 See your readers</a>
    </section>
@elseif($quizAttempt)
    <section id="read-earn" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-600">Read &amp; Earn</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-black text-slate-950">You scored {{ $quizAttempt->score_percent }}%</h2>
                <p class="mt-1 text-sm text-slate-600">
                    @if($quizAttempt->status === 'counted')
                        +{{ $quizAttempt->points }} points{{ $quizAttempt->credit_minor > 0 ? ' and ₦'.number_format($quizAttempt->credit_minor / 100, 2).' study credit' : '' }}.
                    @elseif($quizAttempt->status === 'held')
                        <strong class="text-emerald-700">+{{ $quizAttempt->points }} points saved for you.</strong> Get a Reader Pass{{ ($readEarn['hold_days'] ?? 0) > 0 ? ' within '.$readEarn['hold_days'].' days' : '' }} to collect them as points and study credit.
                    @elseif($quizAttempt->status === 'flagged')
                        Your points are held for a quick review.
                    @else
                        {{ \App\Services\Knowledge\ReadToEarnService::REASONS[$quizAttempt->reason] ?? 'No points for this guide.' }}
                    @endif
                </p>
            </div>
            <a href="{{ route('knowledge.rewards.index') }}" class="rounded-xl border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-800">My rewards →</a>
        </div>
        @if($quizAttempt->status === 'held' && $needsPass)
            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">@include('knowledge.rewards._pass-buttons')</div>
        @endif
        <ol class="mt-5 space-y-3">
            @foreach($quizQuestions as $question)
                @php($picked = (int) (($quizAttempt->answers ?? [])[$question->id] ?? -1))
                <li class="rounded-2xl border border-slate-100 p-4 text-sm">
                    <p class="font-bold text-slate-900">{{ $loop->iteration }}. {{ $question->question }}</p>
                    <p class="mt-2 {{ $picked === $question->correct_option ? 'text-emerald-700' : 'text-rose-700' }}">Your answer: {{ $question->options[$picked] ?? 'None' }}</p>
                    @if($picked !== $question->correct_option)<p class="text-slate-600">Correct answer: {{ $question->options[$question->correct_option] ?? '' }}</p>@endif
                    @if($question->explanation)<p class="mt-1 text-xs text-slate-500">{{ $question->explanation }}</p>@endif
                    @include('knowledge.rewards._report-question', ['question' => $question])
                </li>
            @endforeach
        </ol>
    </section>
@elseif($readEarn['earnable'])
    <section id="read-earn" class="rounded-[2rem] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-indigo-50 p-6 shadow-sm sm:p-8">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Read &amp; Earn</p>
        <h2 class="mt-1 text-2xl font-black text-slate-950">What did you learn?</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Answer {{ $quizQuestions->count() }} questions about this guide. Pass to earn points and study credit. You get <strong>one attempt</strong>, so read the guide properly first.</p>
            @if($needsPass && ($readEarn['hold_days'] ?? 0) > 0)
                <p class="mt-2 max-w-2xl rounded-xl bg-white/70 px-3 py-2 text-xs leading-5 text-slate-500">No Reader Pass yet? Answer anyway. If you pass, your points are saved for {{ $readEarn['hold_days'] }} days and paid as soon as you get the pass.</p>
            @elseif($needsPass)
                <p class="mt-2 max-w-2xl rounded-xl bg-white/70 px-3 py-2 text-xs leading-5 text-slate-500">You can answer and see how you did. Points and study credit are for Reader Pass holders.</p>
            @endif
            <form method="POST" action="{{ route('knowledge.rewards.quiz.submit', $publication) }}" class="mt-5 space-y-4">
                @csrf
                @foreach($quizQuestions as $question)
                    <fieldset class="rounded-2xl border border-slate-200 bg-white p-4">
                        <legend class="px-1 text-sm font-black text-slate-900">{{ $loop->iteration }}. {{ $question->question }}</legend>
                        <div class="mt-2 grid gap-2">
                            @foreach(($question->display_options ?? $question->options) as $index => $option)
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-100 px-3 py-2.5 text-sm hover:bg-slate-50">
                                    <input type="radio" required name="answers[{{ $question->id }}]" value="{{ $index }}" @checked((string) old('answers.'.$question->id) === (string) $index) class="mt-0.5">
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
                <label class="block rounded-2xl border border-slate-200 bg-white p-4 text-sm font-bold text-slate-900">
                    What did you learn, and how will it help you? <span class="font-normal text-slate-500">(optional bonus points)</span>
                    <textarea name="reflection" rows="4" maxlength="3000" class="mt-2 w-full rounded-xl border-slate-300 font-normal" placeholder="In your own words…">{{ old('reflection') }}</textarea>
                </label>
                @error('answers')<p class="text-sm font-bold text-rose-700">{{ $message }}</p>@enderror
                <button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-emerald-800">Submit answers</button>
            </form>
            <details class="mt-4 text-xs text-slate-500">
                <summary class="cursor-pointer font-bold text-slate-600">Something wrong with a question?</summary>
                <div class="mt-2 space-y-2">@foreach($quizQuestions as $question)<div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-white/70 px-3 py-2"><span>{{ $loop->iteration }}. {{ \Illuminate\Support\Str::limit($question->question, 90) }}</span>@include('knowledge.rewards._report-question', ['question' => $question, 'compact' => true])</div>@endforeach</div>
            </details>
    </section>
@endif
@endauth
