@extends('layouts.app')
@section('title', 'Read & Earn questions')
@section('page-title', 'Read & Earn questions')
@section('page-subtitle', $publication->title)
@section('content')
@php
    $rows = old('questions', $questions->map(fn ($q) => ['question' => $q->question, 'options' => $q->options, 'correct' => $q->correct_option, 'explanation' => $q->explanation, 'source' => $q->source, 'report_count' => $q->report_count])->all());
    $slots = min(\App\Services\Knowledge\ReadToEarnService::MAX_QUESTIONS, max(5, count($rows) + 1));
@endphp
<div class="mx-auto max-w-4xl space-y-6">
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif

    <section class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
        <h1 class="text-xl font-black text-slate-950">Questions readers answer after your guide</h1>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm leading-6 text-slate-700">
            @if($aiEnabled)<li><strong>You don't have to write these.</strong> When your guide is published, AcadSyntra's AI writes a question bank from it. Questions marked <span class="rounded bg-violet-100 px-1.5 py-0.5 text-xs font-black text-violet-800">AI</span> came from there. Check them, fix anything wrong and add your own if you like.</li>@endif
            <li>Each reader gets <strong>{{ $perReader }}</strong> random questions from this bank with the answers shuffled, so friends can't share answers.</li>
            <li>The guide needs at least <strong>{{ $minQuestions }}</strong> questions before readers can earn from it. Each reader gets one attempt; readers who pass count as <strong>verified reads</strong>.</li>
            @if($retired > 0)<li>{{ $retired }} question{{ $retired === 1 ? ' was' : 's were' }} pulled after readers reported {{ $retired === 1 ? 'it' : 'them' }}. AcadSyntra reviews these.</li>@endif
        </ul>
        @if($publication->quiz_status === 'awaiting_approval')<p class="mt-3 rounded-xl bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-900">New AI questions are waiting for your approval. Review them below and press Save to make them live.</p>@endif
        @if($publication->quiz_status === 'pending')<p class="mt-3 rounded-xl bg-white px-3 py-2 text-sm text-slate-600">AI is writing questions for this guide. Refresh in a minute.</p>@endif
        @if($aiEnabled && $publication->status === 'published')
            <form method="POST" action="{{ route('knowledge.rewards.questions.regenerate', $publication) }}" class="mt-4" onsubmit="return confirm('Replace the AI questions with a new set? Your own questions stay.')">@csrf
                <button class="rounded-xl border border-violet-300 bg-white px-4 py-2 text-sm font-bold text-violet-800">↻ Write new AI questions</button>
            </form>
        @endif
        <a href="{{ route('knowledge.manage.show', $publication) }}" class="mt-4 inline-flex text-sm font-bold text-emerald-800">← Back to the publication workspace</a>
    </section>

    <form method="POST" action="{{ route('knowledge.rewards.questions.update', $publication) }}" class="space-y-4">
        @csrf @method('PUT')
        @for($i = 0; $i < $slots; $i++)
            @php($row = $rows[$i] ?? ['question' => '', 'options' => [], 'correct' => null, 'explanation' => ''])
            <fieldset class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <legend class="px-2 text-sm font-black text-slate-700">Question {{ $i + 1 }}{{ $i >= $minQuestions ? ' (optional)' : '' }}@if(($row['source'] ?? '') === 'ai') <span class="ml-1 rounded bg-violet-100 px-1.5 py-0.5 text-xs font-black text-violet-800">AI</span>@endif @if(($row['report_count'] ?? 0) > 0)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-bold text-amber-800">{{ $row['report_count'] }} report{{ $row['report_count'] === 1 ? '' : 's' }}</span>@endif</legend>
                <input type="hidden" name="questions[{{ $i }}][source]" value="{{ ($row['source'] ?? '') === 'ai' ? 'ai' : 'creator' }}">
                <label class="block text-sm font-bold">Question<input name="questions[{{ $i }}][question]" value="{{ $row['question'] ?? '' }}" maxlength="500" class="mt-1 w-full rounded-xl border-slate-300" placeholder="e.g. Which normal form removes partial dependencies?"></label>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @for($j = 0; $j < 4; $j++)
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                            <input type="radio" name="questions[{{ $i }}][correct]" value="{{ $j }}" @checked(isset($row['correct']) && (string) $row['correct'] === (string) $j) aria-label="Option {{ chr(65 + $j) }} is correct">
                            <span class="font-bold text-slate-500">{{ chr(65 + $j) }}</span>
                            <input name="questions[{{ $i }}][options][{{ $j }}]" value="{{ ($row['options'] ?? [])[$j] ?? '' }}" maxlength="300" class="w-full rounded-lg border-slate-200 text-sm" placeholder="Option {{ chr(65 + $j) }}">
                        </label>
                    @endfor
                </div>
                <p class="mt-1 text-xs text-slate-500">Tick the circle next to the correct answer.</p>
                <label class="mt-3 block text-sm font-bold">Explanation shown after answering <span class="font-normal text-slate-500">(optional)</span><input name="questions[{{ $i }}][explanation]" value="{{ $row['explanation'] ?? '' }}" maxlength="1000" class="mt-1 w-full rounded-xl border-slate-300"></label>
            </fieldset>
        @endfor
        <button class="rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white">Save questions</button>
    </form>
</div>
@endsection
