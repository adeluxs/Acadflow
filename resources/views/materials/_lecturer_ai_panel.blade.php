@php
    $lecturerAiUser = auth()->user();
    $lecturerAiRuntime = app(\App\Services\Ai\AiRuntimeConfigService::class);
    $lecturerAiEnabled = $lecturerAiUser
        && \App\Services\FeatureAccessService::canAccessFeature('ai_assistant', $lecturerAiUser)
        && $lecturerAiRuntime->featureEnabled('lecturer_assistant', $lecturerAiUser->university_id)
        && $lecturerAiRuntime->mode($lecturerAiUser->university_id) !== \App\Enums\AiMode::DISABLED;
    $lecturerAiActions = $lecturerAiActions ?? [
        ['action' => 'draft', 'label' => 'Draft content'],
        ['action' => 'outline', 'label' => 'Outline'],
        ['action' => 'lesson_plan', 'label' => 'Lesson plan'],
        ['action' => 'quiz', 'label' => 'Quiz'],
        ['action' => 'summary', 'label' => 'Summary'],
        ['action' => 'rewrite', 'label' => 'Rewrite'],
        ['action' => 'improve_tone', 'label' => 'Improve tone'],
        ['action' => 'rubric', 'label' => 'Rubric'],
    ];
@endphp
@if($lecturerAiEnabled)
<section data-lecturer-ai data-endpoint="{{ $aiEndpoint }}" data-target="{{ $editorTargetId }}"
         class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/60 p-5 shadow-sm">
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-sm font-black text-white">AI</div>
        <div>
            <h2 class="text-base font-black text-slate-900">Lecturer AI Studio</h2>
            <p class="text-xs text-slate-600">Generate a draft, then review it and insert it into the editor. Drafts are never saved automatically.</p>
        </div>
    </div>

    <label class="mt-4 block text-xs font-bold text-slate-600">What should the assistant produce?
        <textarea data-ai-instruction rows="2" maxlength="4000" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="e.g. A week 4 lesson plan on database normalization for 200-level students."></textarea>
    </label>

    <div class="mt-3 flex flex-wrap gap-2">
        @foreach($lecturerAiActions as $aiAction)
            <button type="button" data-ai-action="{{ $aiAction['action'] }}"
                    class="rounded-full border border-indigo-200 bg-white px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-60">
                {{ $aiAction['label'] }}
            </button>
        @endforeach
    </div>

    <div class="mt-4 hidden rounded-xl border border-slate-200 bg-white p-4" data-ai-result>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-black uppercase tracking-wide text-indigo-600" data-ai-status>Draft</p>
            <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-amber-700">Draft only — review before saving</span>
        </div>
        <div class="mt-2 max-h-80 overflow-y-auto whitespace-pre-wrap rounded-xl bg-slate-50/70 p-3 text-sm leading-7 text-slate-800" data-ai-answer></div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="hidden rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700" data-ai-insert>Insert into editor</button>
            <button type="button" class="hidden rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" data-ai-copy>Copy</button>
            <button type="button" class="hidden rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700 hover:bg-rose-50" data-ai-retry>Try again</button>
        </div>
    </div>
</section>
@endif
