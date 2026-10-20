@extends('layouts.app')

@section('title', $assistantExperience['page_title'] ?? $assistantExperience['title'] ?? 'AI Academic Assistant')
@section('page-title', $assistantExperience['page_title'] ?? $assistantExperience['title'] ?? 'AI Academic Assistant')
@section('page-subtitle', $assistantExperience['subtitle'] ?? 'Ask questions, improve writing and review citations')

@section('content')
@php
    $personaTitle = $assistantExperience['title'] ?? 'AI Academic Assistant';
    $personaIntro = $assistantExperience['intro'] ?? 'Ask questions, improve academic work, and get role-aware support.';
    $workflowCards = $assistantExperience['workflows'] ?? [];
    $assistantOptions = $assistantExperience['assistant_options'] ?? [];
    $assistantChips = $assistantExperience['chips'] ?? [];
    $promptTips = $assistantExperience['prompt_tips'] ?? [];
@endphp

<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <section class="relative overflow-hidden rounded-[2rem] border border-indigo-100 bg-gradient-to-br from-slate-950 via-indigo-950 to-blue-800 p-6 text-white shadow-xl sm:p-8">
        <div class="absolute -right-16 -top-20 h-72 w-72 rounded-full bg-cyan-300/20 blur-3xl"></div>
        <div class="absolute -bottom-24 left-20 h-72 w-72 rounded-full bg-fuchsia-400/10 blur-3xl"></div>
        <div class="relative grid gap-7 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-end">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-indigo-100">✦ AcadSyntra AI</div>
                <h1 class="mt-4 max-w-3xl text-3xl font-black tracking-tight sm:text-5xl">{{ $personaTitle }}</h1>
                <p class="mt-4 max-w-3xl text-sm leading-7 text-indigo-100 sm:text-base">{{ $personaIntro }}</p>
                @if(!empty($assistantChips))
                    <div class="mt-5 flex flex-wrap gap-2 text-xs font-bold text-indigo-50">
                        @foreach($assistantChips as $chip)
                            <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">{{ $chip }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="rounded-3xl border border-white/15 bg-white/10 p-4 text-xs backdrop-blur">
                <p class="font-black uppercase tracking-[0.16em] text-indigo-100">Runtime route</p>
                <div class="mt-3 grid gap-2">
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/10 p-3"><span>Mode</span><strong id="assistantModeBadge" class="min-w-0 truncate text-right">{{ str_replace('_', ' ', ucfirst($mode)) }}</strong></div>
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/10 p-3"><span>Provider</span><strong id="assistantProviderBadge" class="min-w-0 truncate text-right">{{ $provider }}</strong></div>
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-white/10 p-3"><span>Model</span><strong id="assistantModelBadge" class="min-w-0 truncate text-right">{{ $model }}</strong></div>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="min-w-0 overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-4 sm:px-6">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">{{ $assistantExperience['conversation_title'] ?? 'AI conversation' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $assistantExperience['conversation_hint'] ?? 'Ask for role-aware academic support. Choose a course to narrow the context.' }}</p>
                    </div>
                    <div id="assistantStatus" class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700" role="status" aria-live="polite">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Ready
                    </div>
                </div>
            </div>

            <div id="assistantThread" class="max-h-[620px] min-h-[430px] space-y-4 overflow-y-auto bg-[radial-gradient(circle_at_top_left,rgba(99,102,241,0.08),transparent_34%),linear-gradient(to_bottom,#ffffff,#f8fafc)] p-5 sm:p-7" aria-live="polite">
                <div class="max-w-3xl rounded-3xl border border-indigo-100 bg-white p-5 text-sm leading-7 text-slate-700 shadow-sm">
                    <div class="mb-2 flex items-center gap-2 font-black text-slate-950"><span class="flex h-8 w-8 items-center justify-center rounded-2xl bg-indigo-600 text-white">AI</span>{{ $assistantExperience['ready_heading'] ?? 'AcadSyntra Assistant' }}</div>
                    <p>{{ $assistantExperience['ready_text'] ?? 'I can provide role-aware academic assistance using AcadSyntra AI.' }}</p>
                </div>
            </div>

            <form id="assistantForm" class="border-t border-slate-200 bg-white p-4 sm:p-6" data-timeout-ms="{{ $assistantTimeoutMs }}" novalidate>
                @csrf
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="min-w-0 text-xs font-semibold text-slate-600">Assistant mode
                        <select id="assistantTool" name="tool" class="mt-1.5 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            <option value="ask" @selected($selectedTool === 'ask')>{{ $assistantExperience['ask_label'] ?? 'Ask / Explain' }}</option>
                            <option value="writing" @selected($selectedTool === 'writing')>{{ $isLecturer ? 'Rewrite / Feedback' : 'Improve Writing' }}</option>
                            <option value="citation" @selected($selectedTool === 'citation')>Citation Review</option>
                        </select>
                    </label>
                    <label class="min-w-0 text-xs font-semibold text-slate-600">Course context
                        <select id="assistantCourse" name="course_id" class="mt-1.5 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            <option value="">{{ $assistantExperience['course_label'] ?? 'All authorized knowledge' }}</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="min-w-0 text-xs font-semibold text-slate-600">Specialist behavior
                        <select id="assistantFeature" name="assistant_feature" class="mt-1.5 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            @foreach($assistantOptions as $option)
                                <option value="{{ $option['feature'] }}" @selected($selectedFeature === $option['feature'])>{{ $option['title'] ?? $option['feature'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label id="citationStyleWrap" class="hidden min-w-0 text-xs font-semibold text-slate-600">Citation style
                        <select id="citationStyle" name="style" class="mt-1.5 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            @foreach(['apa','mla','chicago','harvard','ieee','vancouver'] as $style)
                                <option value="{{ $style }}">{{ strtoupper($style) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div id="studyModeWrap" class="mt-3 flex flex-wrap items-center gap-3 text-xs font-semibold text-slate-600">
                    <span>Study mode</span>
                    <label class="inline-flex items-center gap-1"><input type="radio" name="study_mode" value="answer" checked> Direct answer</label>
                    <label class="inline-flex items-center gap-1"><input type="radio" name="study_mode" value="socratic" id="studyModeSocratic"> Socratic (guide me step by step)</label>
                </div>

                <label class="mt-4 block text-xs font-semibold text-slate-600">{{ $assistantExperience['request_label'] ?? 'Your request' }}
                    <textarea id="assistantMessage" name="message" rows="5" maxlength="50000" required class="mt-1.5 w-full resize-y rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm leading-6 outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" placeholder="{{ $assistantExperience['placeholder'] ?? 'Ask AcadSyntra AI for academic assistance…' }}"></textarea>
                </label>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p id="assistantHelpText" class="text-[11px] leading-5 text-slate-500">{{ $assistantExperience['safeguard'] ?? 'AI can make mistakes. Verify grades, academic requirements, institutional policies and source claims before acting on them.' }}</p>
                    <button id="assistantSubmit" type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-black text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span data-submit-label>{{ $assistantExperience['submit_label'] ?? 'Ask AI Assistant' }}</span>
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
            </form>
        </section>

        <aside class="min-w-0 space-y-4">
            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-black text-slate-950">{{ $assistantExperience['workflow_title'] ?? 'Quick actions' }}</h2>
                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-700">Click to draft</span>
                </div>
                <div class="mt-4 grid gap-2">
                    @foreach($workflowCards as $card)
                        <button type="button" data-tool="{{ $card['tool'] }}" data-feature="{{ $card['feature'] ?? '' }}" data-prompt="{{ $card['prompt'] }}" class="quick-prompt group flex w-full items-start gap-3 rounded-2xl border border-slate-200 bg-white p-3 text-left transition hover:-translate-y-0.5 hover:border-indigo-200 hover:bg-indigo-50/60 hover:shadow-sm">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-lg group-hover:bg-indigo-600 group-hover:text-white">{{ $card['icon'] }}</span>
                            <span class="min-w-0"><span class="block text-sm font-black text-slate-900">{{ $card['title'] }}</span><span class="mt-0.5 block line-clamp-2 text-xs leading-5 text-slate-500">{{ Str::limit($card['prompt'], 92) }}</span></span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[2rem] border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                <h2 class="font-black text-slate-950">{{ $assistantExperience['prompt_tips_title'] ?? 'Better prompts' }}</h2>
                <ul class="mt-3 space-y-2 text-xs leading-5">
                    @foreach($promptTips as $tip)
                        <li class="rounded-2xl bg-slate-50 p-3">{{ $tip }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-[2rem] border border-indigo-100 bg-indigo-50/50 p-5 text-sm shadow-sm">
                <h2 class="font-black text-slate-950">Study tools</h2>
                <p class="mt-1 text-xs leading-5 text-slate-600">Each tool opens with a short guide. Use them together to build a daily revision habit.</p>
                <div class="mt-3 grid gap-2 text-xs font-bold text-indigo-700">
                    <a href="{{ route('student.learning-profile') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">Learning preferences</a>
                    <a href="{{ route('study-notes.index') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">My study notes</a>
                    <a href="{{ route('practice-quizzes.index') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">Practice quizzes</a>
                    <a href="{{ route('flashcards.index') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">Flashcards</a>
                    <a href="{{ route('study-plans.index') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">Study plans</a>
                    <a href="{{ route('mastery.index') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">Mastery &amp; readiness</a>
                    <a href="{{ route('deadline-coach.index') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">Deadline coach</a>
                    <a href="{{ route('reflections.index') }}" class="rounded-xl bg-white px-3 py-2.5 shadow-sm">Reflection journal</a>
                </div>
            </div>

            <div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-5 text-xs leading-5 text-amber-900">
                <strong>Academic safeguard:</strong> {{ $assistantExperience['safeguard'] ?? 'Review AI-generated content before relying on it.' }}
            </div>
        </aside>
    </div>
</div>

<script>
(() => {
    const form = document.getElementById('assistantForm');
    const thread = document.getElementById('assistantThread');
    const message = document.getElementById('assistantMessage');
    const tool = document.getElementById('assistantTool');
    const course = document.getElementById('assistantCourse');
    const style = document.getElementById('citationStyle');
    const styleWrap = document.getElementById('citationStyleWrap');
    const assistantFeature = document.getElementById('assistantFeature');
    const submit = document.getElementById('assistantSubmit');
    const submitLabel = submit?.querySelector('[data-submit-label]');
    const status = document.getElementById('assistantStatus');
    const modeBadge = document.getElementById('assistantModeBadge');
    const providerBadge = document.getElementById('assistantProviderBadge');
    const modelBadge = document.getElementById('assistantModelBadge');
    const toolRoutes = @json($toolRoutes);
    const featureRoutes = @json($featureRoutes);
    const assistantExperience = @json($assistantExperience);
    const endpoint = @json(route('ai.assistant.ask'));
    const noteEndpoint = @json(route('study-notes.store'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
    const requestTimeoutMs = Math.max(5000, Number(form?.dataset.timeoutMs || 28000));
    let lastQuestion = '';

    if (!form || !thread || !message || !tool || !submit) return;

    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
    const modeLabel = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    const feedback = () => window.AcadFlowFeedback;

    function setStatus(text, tone = 'ready') {
        if (!status) return;
        const styles = {
            ready: 'inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700',
            busy: 'inline-flex w-fit items-center gap-2 rounded-full bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700',
            error: 'inline-flex w-fit items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700',
        };
        const dot = tone === 'error' ? 'bg-rose-500' : (tone === 'busy' ? 'bg-indigo-500 animate-pulse' : 'bg-emerald-500');
        status.className = styles[tone] || styles.ready;
        status.innerHTML = `<span class="h-2 w-2 rounded-full ${dot}"></span>${esc(text)}`;
    }

    function updateTool() {
        styleWrap?.classList.toggle('hidden', tool.value !== 'citation');
        assistantFeature?.closest('label')?.classList.toggle('opacity-60', tool.value !== 'ask');
        assistantFeature?.toggleAttribute('disabled', tool.value !== 'ask');
        document.getElementById('studyModeWrap')?.classList.toggle('hidden', tool.value !== 'ask');
        const route = tool.value === 'ask'
            ? (featureRoutes[assistantFeature?.value] || toolRoutes.ask)
            : (toolRoutes[tool.value] || toolRoutes.ask);
        if (route) {
            modeBadge.textContent = modeLabel(route.mode);
            providerBadge.textContent = route.provider || 'Unavailable';
            modelBadge.textContent = route.model || 'Provider default';
        }
        if (submitLabel) submitLabel.textContent = tool.value === 'citation' ? 'Review citation' : (tool.value === 'writing' ? 'Improve draft' : (assistantExperience.submit_label || 'Ask AI Assistant'));
    }

    function cleanAiText(value) {
        let text = String(value || '').trim();
        if (!text) return '';
        text = text.replace(/^```(?:json|JSON|markdown|md)?\s*/, '').replace(/\s*```$/, '').trim();
        text = text.replace(/\r\n?/g, '\n').replace(/\s*\[S\d+\]/g, '');
        return text.replace(/\n{3,}/g, '\n\n').trim();
    }

    function paragraphHtml(value) {
        const text = cleanAiText(value);
        if (!text) return '<p>No response was returned.</p>';
        return text.split(/\n{2,}/).map(block => `<p class="mb-3 last:mb-0">${esc(block).replace(/\n/g, '<br>')}</p>`).join('');
    }

    function appendUser(text) {
        thread.insertAdjacentHTML('beforeend', `<div class="ml-auto max-w-3xl rounded-3xl bg-slate-950 p-4 text-sm leading-6 text-white shadow-sm"><div class="mb-1 text-xs font-bold text-slate-300">You</div>${paragraphHtml(text)}</div>`);
    }

    function appendLoading() {
        const node = document.createElement('div');
        node.className = 'max-w-3xl rounded-3xl border border-indigo-100 bg-white p-5 text-sm leading-7 text-slate-700 shadow-sm';
        node.innerHTML = `<div class="mb-2 flex items-center gap-2 font-black text-slate-950"><span class="flex h-8 w-8 items-center justify-center rounded-2xl bg-indigo-600 text-white">AI</span>${esc(assistantExperience.loading_heading || 'Preparing a response…')}</div><div class="flex gap-1" aria-hidden="true"><span class="h-2 w-2 animate-bounce rounded-full bg-indigo-500"></span><span class="h-2 w-2 animate-bounce rounded-full bg-indigo-400 [animation-delay:120ms]"></span><span class="h-2 w-2 animate-bounce rounded-full bg-indigo-300 [animation-delay:240ms]"></span></div>`;
        thread.appendChild(node);
        return node;
    }

    function appendAnswer(data) {
        const meta = [data.provider || 'AcadSyntra AI', data.model, data.fallback_used ? 'fallback used' : null, data.cached ? 'cached' : null].filter(Boolean).map(esc).join(' · ');
        const sources = Array.isArray(data.sources) && data.sources.length
            ? `<div class="mt-4 rounded-2xl bg-white/70 p-3 text-[11px] leading-5 text-slate-500"><strong>Sources:</strong> ${data.sources.map(source => `${esc(source.label)} ${esc(source.title)}${source.locator ? ` (${esc(source.locator)})` : ''}`).join(' · ')}</div>`
            : '';
        thread.insertAdjacentHTML('beforeend', `<article class="max-w-3xl rounded-3xl border border-indigo-100 bg-indigo-50 p-5 text-sm leading-7 text-slate-700 shadow-sm"><div class="mb-2 flex items-center gap-2 font-black text-slate-950"><span class="flex h-8 w-8 items-center justify-center rounded-2xl bg-indigo-600 text-white">AI</span>AcadSyntra response</div><div class="prose prose-sm max-w-none prose-slate">${paragraphHtml(data.answer || data.message || 'No response was returned.')}</div>${sources}<div class="mt-3 flex flex-wrap items-center justify-between gap-2"><div class="text-[10px] font-bold uppercase tracking-wide text-slate-400">${meta}</div><button type="button" data-save-note class="rounded-lg border border-indigo-200 bg-white px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100">Save as note</button></div></article>`);
        const article = thread.lastElementChild;
        const saveButton = article?.querySelector('[data-save-note]');
        saveButton?.addEventListener('click', async () => {
            saveButton.disabled = true;
            saveButton.textContent = 'Saving…';
            try {
                const response = await fetch(noteEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        title: (lastQuestion || 'Study note').slice(0, 190),
                        content: String(data.answer || ''),
                        course_id: course?.value ? Number(course.value) : null,
                        source: 'ai_answer',
                    }),
                });
                saveButton.textContent = response.ok ? 'Saved' : 'Save failed';
            } catch (_) {
                saveButton.textContent = 'Save failed';
            }
        });
    }

    function appendFailure(detail, originalText) {
        const requestId = detail.requestId ? `<p class="mt-2 text-[11px] text-rose-500">Request ID: ${esc(detail.requestId)}</p>` : '';
        const retry = detail.retryable ? '<button type="button" data-assistant-retry class="mt-3 rounded-xl bg-rose-700 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60">Try again</button>' : '';
        const wrapper = document.createElement('div');
        wrapper.className = 'max-w-3xl rounded-3xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800 shadow-sm';
        wrapper.innerHTML = `<div class="font-black text-rose-950">AI request not completed</div><p class="mt-1 leading-6">${esc(detail.message)}</p>${requestId}${retry}`;
        wrapper.querySelector('[data-assistant-retry]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            button.disabled = true;
            button.textContent = 'Trying…';
            wrapper.remove();
            await sendRequest(originalText, true);
        });
        thread.appendChild(wrapper);
    }

    function normalizeFailure(error, response = null, data = {}) {
        if (error?.name === 'AbortError') {
            return { message: 'The AI request took longer than expected. Your prompt has been restored so you can try again.', retryable: true, requestId: response?.headers?.get?.('X-Request-Id') || null };
        }
        const normalized = feedback()?.normalize?.(Object.assign(error || {}, { data, status: error?.status || response?.status }), 'AI assistance is currently unavailable. Your work has not been lost. Please try again.');
        return normalized || { message: data.message || data.answer || 'AI assistance is currently unavailable. Your work has not been lost. Please try again.', retryable: true, requestId: data.request_id || response?.headers?.get?.('X-Request-Id') || null };
    }

    async function sendRequest(text, isRetry = false) {
        if (!isRetry) { appendUser(text); lastQuestion = text; }
        message.value = '';
        submit.disabled = true;
        setStatus('Generating…', 'busy');
        if (submitLabel) submitLabel.textContent = isRetry ? 'Retrying…' : 'Generating…';
        const loading = appendLoading();
        thread.scrollTop = thread.scrollHeight;

        const abortController = new AbortController();
        const timeoutId = window.setTimeout(() => abortController.abort(), requestTimeoutMs);
        let response = null;
        let data = {};

        try {
            response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-AcadFlow-Loader': 'silent',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: abortController.signal,
                body: JSON.stringify({ tool: tool.value, message: text, course_id: course?.value || null, style: style?.value || 'apa', assistant_feature: assistantFeature?.value || null, study_mode: (tool.value === 'ask' && document.getElementById('studyModeSocratic')?.checked) ? 'socratic' : 'answer' }),
            });

            const raw = await response.text();
            try { data = raw ? JSON.parse(raw) : {}; } catch (_) { data = { success: false, message: 'AcadSyntra received an unreadable AI response. Please try again.' }; }
            if (!response.ok || data.success === false) {
                const requestError = new Error(data.message || data.answer || 'AI assistance is currently unavailable.');
                requestError.data = data;
                requestError.status = response.status;
                throw requestError;
            }

            loading.remove();
            appendAnswer(data);
            setStatus('Response ready', 'ready');
        } catch (error) {
            loading.remove();
            const detail = normalizeFailure(error, response, data);
            if (!message.value.trim()) message.value = text;
            appendFailure(detail, text);
            setStatus('Needs attention', 'error');
            console.warn('[AcadSyntra AI] Assistant request did not complete successfully.', { status: error?.status || response?.status || null, requestId: detail.requestId || data.request_id || null });
        } finally {
            window.clearTimeout(timeoutId);
            submit.disabled = false;
            updateTool();
            window.AcadFlowLoader?.reset?.();
            thread.scrollTop = thread.scrollHeight;
        }
    }

    tool.addEventListener('change', updateTool);
    assistantFeature?.addEventListener('change', updateTool);
    updateTool();

    document.querySelectorAll('.quick-prompt').forEach(button => button.addEventListener('click', () => {
        if (button.dataset.tool) tool.value = button.dataset.tool;
        if (button.dataset.feature && assistantFeature?.querySelector(`option[value="${CSS.escape(button.dataset.feature)}"]`)) assistantFeature.value = button.dataset.feature;
        updateTool();
        message.value = button.dataset.prompt || '';
        message.focus();
    }));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = message.value.trim();
        if (!text || submit.disabled) return;
        await sendRequest(text, false);
    });
})();
</script>
@endsection
