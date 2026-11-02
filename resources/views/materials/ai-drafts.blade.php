@extends('layouts.app')

@section('title', 'AI draft library')

@section('content')
<div class="container mx-auto max-w-5xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('materials.index', $course) }}" class="text-sm font-semibold text-slate-500 hover:text-indigo-700">← Back to course materials</a>
        <h1 class="mt-3 text-2xl font-black text-slate-900">AI draft library</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $course->code }} — {{ $course->name }}. Queued AI drafts appear here. Review a draft, then apply it to create a course material or discard it.</p>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/60 p-5 shadow-sm">
        <h2 class="text-base font-black text-slate-900">Queue batch content</h2>
        <p class="mt-1 text-xs text-slate-600">Add one instruction per line (up to 10). Each line becomes a background AI draft.</p>
        <form id="batchForm" method="POST" action="{{ route('lecturer.ai.batch', $course) }}" class="mt-4 space-y-3">
            @csrf
            <div class="grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)]">
                <label class="text-xs font-bold text-slate-600">Action
                    <select id="batchAction" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                        @foreach(['draft' => 'Draft content','outline' => 'Outline','lesson_plan' => 'Lesson plan','quiz' => 'Quiz','summary' => 'Summary','rubric' => 'Rubric','study_questions' => 'Study questions'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-600">Instructions (one per line)
                    <textarea id="batchInstructions" rows="4" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Week 4 lesson plan on normalization&#10;Quiz on functional dependencies&#10;Outline for the revision class"></textarea>
                </label>
            </div>
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Queue drafts</button>
        </form>
    </section>

    <section class="mt-6 space-y-3">
        @forelse($drafts as $draft)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-slate-600">{{ str_replace('_', ' ', $draft->action) }}</span>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide
                            @class([
                                'bg-amber-100 text-amber-700' => $draft->status === 'pending' || $draft->status === 'generating',
                                'bg-emerald-100 text-emerald-700' => $draft->status === 'ready',
                                'bg-indigo-100 text-indigo-700' => $draft->status === 'applied',
                                'bg-rose-100 text-rose-700' => $draft->status === 'failed',
                                'bg-slate-100 text-slate-500' => $draft->status === 'discarded',
                            ])">{{ $draft->status }}</span>
                    </div>
                    <span class="text-xs text-slate-400">{{ $draft->created_at?->diffForHumans() }}</span>
                </div>
                <p class="mt-2 text-sm font-semibold text-slate-700">{{ $draft->instruction }}</p>
                @if($draft->isReady())
                    <div class="mt-3 max-h-64 overflow-y-auto whitespace-pre-wrap rounded-xl bg-slate-50/70 p-3 text-sm leading-7 text-slate-800">{{ $draft->content }}</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('lecturer.ai.drafts.apply', [$course, $draft]) }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700">Apply as material</button>
                        </form>
                        <form method="POST" action="{{ route('lecturer.ai.drafts.discard', [$course, $draft]) }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-rose-200 px-4 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50">Discard</button>
                        </form>
                    </div>
                @elseif($draft->status === 'failed')
                    <p class="mt-2 text-xs font-semibold text-rose-600">Generation failed ({{ $draft->error_code ?: 'unknown error' }}). Try again with a new instruction.</p>
                @elseif($draft->status === 'applied')
                    <p class="mt-2 text-xs font-semibold text-indigo-600">Applied to a course material{{ $draft->material ? ': '.$draft->material->title : '' }}.</p>
                @else
                    <p class="mt-2 text-xs font-semibold text-slate-500">Processing… refresh to see the result.</p>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">
                No AI drafts yet. Queue a batch above to get started.
            </div>
        @endforelse

        <div class="pt-2">{{ $drafts->links() }}</div>
    </section>
</div>

<script>
(() => {
    const form = document.getElementById('batchForm');
    if (!form) return;
    form.addEventListener('submit', (event) => {
        form.querySelectorAll('[data-batch-input]').forEach((el) => el.remove());
        const action = document.getElementById('batchAction')?.value || 'draft';
        const lines = (document.getElementById('batchInstructions')?.value || '')
            .split('\n').map((line) => line.trim()).filter(Boolean).slice(0, 10);
        if (!lines.length) {
            event.preventDefault();
            return;
        }
        lines.forEach((line, index) => {
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = `items[${index}][action]`;
            actionInput.value = action;
            actionInput.dataset.batchInput = '1';
            const instructionInput = document.createElement('input');
            instructionInput.type = 'hidden';
            instructionInput.name = `items[${index}][instruction]`;
            instructionInput.value = line;
            instructionInput.dataset.batchInput = '1';
            form.append(actionInput, instructionInput);
        });
    });
})();
</script>
@endsection
