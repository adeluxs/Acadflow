@extends('layouts.app')

@section('title', 'Add course material')

@section('content')
@php
    $authoringUser = auth()->user();
    $authoringRuntime = app(\App\Services\Ai\AiRuntimeConfigService::class);
    $writingSuggestionsEnabled = $authoringUser
        && $authoringRuntime->featureEnabled('writing_assistant', $authoringUser->university_id);
    $selectedMode = old('mode', request('mode', 'upload')) === 'author' ? 'author' : 'upload';
@endphp
<div class="container mx-auto max-w-3xl px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('materials.index', $course) }}" class="text-sm font-semibold text-slate-500 hover:text-indigo-700">← Back to course materials</a>
        <h1 class="mt-3 text-2xl font-black text-slate-900">Add course material</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $course->code }} — {{ $course->name }}</p>
    </div>

    <form method="POST" action="{{ route('lecturer.materials.store', $course) }}" enctype="multipart/form-data" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <div>
            <span class="block text-sm font-bold text-slate-700">How do you want to create this material?</span>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-indigo-300 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/60">
                    <input type="radio" name="mode" value="upload" @checked($selectedMode === 'upload') class="mt-1">
                    <span><span class="block text-sm font-bold text-slate-900">Upload a file</span><span class="mt-0.5 block text-xs text-slate-500">PDF, DOCX, slides, images and other resources.</span></span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-indigo-300 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/60">
                    <input type="radio" name="mode" value="author" @checked($selectedMode === 'author') class="mt-1">
                    <span><span class="block text-sm font-bold text-slate-900">Write in AcadSyntra</span><span class="mt-0.5 block text-xs text-slate-500">Compose the material with the rich editor and optional AI help.</span></span>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Title *</label>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                   class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm @error('title') border-rose-500 @enderror">
            @error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Description</label>
            <textarea name="description" rows="3" maxlength="20000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Material type *</label>
            <select name="type" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                @foreach(['lecture_note' => 'Lecture Notes','slides' => 'Presentation Slides','reading' => 'Reading Material','video' => 'Video','assignment' => 'Assignment','exam' => 'Quiz','reference' => 'Reference','other' => 'Other'] as $typeValue => $typeLabel)
                    <option value="{{ $typeValue }}" @selected(old('type') === $typeValue)>{{ $typeLabel }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-slate-700">Topic</label>
                <input type="text" name="topic" value="{{ old('topic') }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="e.g. Introduction">
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700">Week number</label>
                <input type="number" name="week_number" value="{{ old('week_number') }}" min="1" max="20" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Sort order (optional)</label>
            <input type="number" name="sequence_order" value="{{ old('sequence_order', 0) }}" min="0" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
        </div>

        {{-- Upload section --}}
        <div id="materialUploadSection" class="{{ $selectedMode === 'author' ? 'hidden' : '' }}">
            <label class="block text-sm font-bold text-slate-700">File *</label>
            <input type="file" name="file" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm @error('file') border-rose-500 @enderror">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-slate-500">Maximum size follows your institution upload policy. Files are security-scanned before publishing.</p>
        </div>

        {{-- Author section --}}
        <div id="materialAuthorSection" class="{{ $selectedMode === 'author' ? '' : 'hidden' }} space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700">Material content</label>
                @error('content')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <textarea id="materialContent" name="content" rows="14" data-rich-editor data-editor-height="380px"
                    @if($writingSuggestionsEnabled) data-ai-url="{{ route('ai.writing') }}" data-ai-enabled="1" data-ai-min-chars="180" data-ai-delay="2200" @endif
                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">{{ old('content') }}</textarea>
                <p class="mt-1 text-xs text-slate-500">Write directly with the rich editor. Formatting is preserved and safely sanitized when saved.</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-bold text-slate-700">Visibility</label>
            <div class="mt-2 space-y-2 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_public" value="0">
                    <input type="checkbox" name="is_public" value="1" @checked(old('is_public'))> Make publicly accessible
                </label>
                <br>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="requires_enrollment" value="0">
                    <input type="checkbox" name="requires_enrollment" value="1" @checked(old('requires_enrollment', true))> Require enrollment to access
                </label>
                <br>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_visible" value="0">
                    <input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', true))> Visible to students
                </label>
            </div>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Save material</button>
            <a href="{{ route('materials.index', $course) }}" class="rounded-xl bg-slate-200 px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-300">Cancel</a>
        </div>
    </form>

    @include('materials._lecturer_ai_panel', [
        'aiEndpoint' => route('lecturer.ai.content', $course),
        'editorTargetId' => 'materialContent',
    ])
</div>

<script>
(() => {
    const radios = [...document.querySelectorAll('input[name="mode"]')];
    const uploadSection = document.getElementById('materialUploadSection');
    const authorSection = document.getElementById('materialAuthorSection');
    if (!radios.length || !uploadSection || !authorSection) return;
    const sync = () => {
        const mode = radios.find(r => r.checked)?.value || 'upload';
        uploadSection.classList.toggle('hidden', mode !== 'upload');
        authorSection.classList.toggle('hidden', mode !== 'author');
    };
    radios.forEach(r => r.addEventListener('change', sync));
    sync();
})();
</script>
@endsection
