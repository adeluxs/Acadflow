@extends('layouts.app')

@section('title', 'Learning preferences')
@section('page-title', 'Learning preferences')
@section('page-subtitle', 'Tell AcadFlow AI how you learn best')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'How these preferences are used',
        'steps' => [
            'Set your level, pace and preferred explanation style.',
            'Choose a reading level and language so AI answers match how you study.',
            'Add your learning goals, then click Save preferences.',
        ],
        'tip' => 'These preferences are private to you and shape your AI explanations, study plans, practice questions and revision support.',
    ])

    <form method="POST" action="{{ route('student.learning-profile.update') }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')

        <p class="text-sm text-slate-600">These preferences are applied to AI explanations, study plans, practice questions and revision support. They are private to you.</p>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-600">Your level
                <select name="level" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Not set</option>
                    @foreach($options['level'] as $value => $label)
                        <option value="{{ $value }}" @selected($profile->level === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Preferred pace
                <select name="pace" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Not set</option>
                    @foreach($options['pace'] as $value => $label)
                        <option value="{{ $value }}" @selected($profile->pace === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Explanation style
                <select name="explanation_style" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Not set</option>
                    @foreach($options['explanation_style'] as $value => $label)
                        <option value="{{ $value }}" @selected($profile->explanation_style === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Reading level
                <select name="reading_level" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Not set</option>
                    @foreach($options['reading_level'] as $value => $label)
                        <option value="{{ $value }}" @selected($profile->reading_level === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Preferred language
                <input type="text" name="language" value="{{ old('language', $profile->language) }}" maxlength="10"
                       placeholder="e.g. en, fr, ha" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </label>
        </div>

        <label class="block text-xs font-bold text-slate-600">Your learning goals
            <textarea name="goals" rows="3" maxlength="2000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                      placeholder="e.g. Pass my exams, understand programming fundamentals, improve report writing">{{ old('goals', $profile->goals) }}</textarea>
        </label>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Save preferences</button>
            <a href="{{ route('ai.assistant') }}" class="rounded-xl bg-slate-200 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-300">Back to assistant</a>
        </div>
    </form>
</div>
@endsection
