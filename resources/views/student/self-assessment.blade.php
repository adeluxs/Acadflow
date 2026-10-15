@extends('layouts.app')

@section('title', 'Self-assessment')
@section('page-title', 'Self-assessment')
@section('page-subtitle', $task->title)

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">{{ $errors->first() }}</div>
    @endif

    @include('student._help', [
        'title' => 'How to self-assess',
        'steps' => [
            'Read your draft against each rubric item and tick the ones it meets.',
            'Set your confidence (0-100) — how ready you feel to submit.',
            'Note what still needs work, then Save — use these notes to improve before you submit.',
        ],
        'tip' => 'Self-assessment is private and does not change your grade; it helps you improve first.',
    ])

    <form method="POST" action="{{ route('self-assessment.store', [$course, $task]) }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <p class="text-sm text-slate-600">Check what your draft already does well, then note what still needs work before you submit.</p>

        @if(!empty($criteria))
            <div class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Rubric self-check</p>
                @foreach($criteria as $criterion)
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="checklist[]" value="{{ $criterion }}" @checked(in_array($criterion, (array) old('checklist', $latest->checklist ?? []), true))>
                        {{ $criterion }}
                    </label>
                @endforeach
            </div>
        @else
            <p class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500">This assignment has no rubric criteria. Use the notes below for your self-check.</p>
        @endif

        <label class="block text-xs font-bold text-slate-600">Confidence in this submission (0-100)
            <input type="number" name="confidence" min="0" max="100" value="{{ old('confidence', $latest->confidence ?? 60) }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        </label>

        <label class="block text-xs font-bold text-slate-600">What still needs work?
            <textarea name="notes" rows="4" maxlength="4000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">{{ old('notes', $latest->notes ?? '') }}</textarea>
        </label>

        <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Save self-assessment</button>
    </form>
</div>
@endsection
