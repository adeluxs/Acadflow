@extends('layouts.app')

@section('title', 'AI Academic Assistant')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">✦ AcadFlow AI</div>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">AI Academic Assistant</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500">Ask questions, explain course topics, review writing, or check citations. Course answers use only material you are authorized to access.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs">
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-600">Mode: <strong id="assistantModeBadge">{{ str_replace('_', ' ', ucfirst($mode)) }}</strong></span>
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-600">Provider: <strong id="assistantProviderBadge">{{ $provider }}</strong></span>
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-slate-600">Model: <strong id="assistantModelBadge">{{ $model }}</strong></span>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div id="assistantThread" class="min-h-[430px] space-y-4 p-5 sm:p-7" aria-live="polite">
                <div class="max-w-2xl rounded-2xl bg-indigo-50 p-4 text-sm leading-6 text-slate-700">
                    <div class="mb-1 font-bold text-slate-950">AcadFlow Assistant</div>
                    I can explain academic topics, answer from your authorized course and Knowledge Hub material, review writing, and check citation structure. Choose a tool and send a request below.
                </div>
            </div>

            <form id="assistantForm" class="border-t border-slate-200 bg-slate-50/70 p-4 sm:p-6">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs font-semibold text-slate-600">Tool
                        <select id="assistantTool" name="tool" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            <option value="ask" @selected($selectedTool === 'ask')>Ask / Explain</option>
                            <option value="writing" @selected($selectedTool === 'writing')>Improve Writing</option>
                            <option value="citation" @selected($selectedTool === 'citation')>Citation Review</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Course context (optional)
                        <select id="assistantCourse" name="course_id" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            <option value="">All authorized knowledge</option>
                            @foreach($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div id="citationStyleWrap" class="mt-3 hidden">
                    <label class="text-xs font-semibold text-slate-600">Citation style
                        <select id="citationStyle" name="style" class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            @foreach(['apa','mla','chicago','harvard','ieee','vancouver'] as $style)
                                <option value="{{ $style }}">{{ strtoupper($style) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <label class="mt-3 block text-xs font-semibold text-slate-600">Your request
                    <textarea id="assistantMessage" name="message" rows="5" maxlength="50000" required class="mt-1.5 w-full resize-y rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm leading-6 outline-none transition hover:border-indigo-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20" placeholder="Ask me to explain a topic, paste writing to improve, or paste a citation to review..."></textarea>
                </label>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-[11px] text-slate-500">AI can make mistakes. Verify important academic requirements and source claims.</p>
                    <button id="assistantSubmit" type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">Ask AI Assistant →</button>
                </div>
            </form>
        </section>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-slate-950">Quick actions</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <button type="button" data-prompt="Explain this topic in simple terms and give me a short example: " class="quick-prompt w-full rounded-xl border border-slate-200 p-3 text-left hover:border-indigo-300 hover:bg-indigo-50">Explain a topic</button>
                    <button type="button" data-tool="writing" data-prompt="Improve the clarity, academic tone, grammar, and structure of this text: " class="quick-prompt w-full rounded-xl border border-slate-200 p-3 text-left hover:border-indigo-300 hover:bg-indigo-50">Improve my writing</button>
                    <button type="button" data-tool="citation" data-prompt="Review this citation/reference and tell me what needs correction: " class="quick-prompt w-full rounded-xl border border-slate-200 p-3 text-left hover:border-indigo-300 hover:bg-indigo-50">Check a citation</button>
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                <h2 class="font-bold text-slate-950">How it works</h2>
                <p class="mt-2 leading-6">AcadFlow resolves the current AI Mode, feature route, provider and model from centralized AI Settings on every request. Course-aware questions retrieve only material your account may access. Rule-Based Only uses deterministic assistance; Provider AI never silently pretends a rule response came from the configured provider; Hybrid may use an explicitly enabled deterministic fallback when providers fail.</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-xs leading-5 text-amber-900">
                Private course material is not exposed to students outside that course. Selecting a course only narrows the authorized material further.
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
    const submit = document.getElementById('assistantSubmit');
    const modeBadge = document.getElementById('assistantModeBadge');
    const providerBadge = document.getElementById('assistantProviderBadge');
    const modelBadge = document.getElementById('assistantModelBadge');
    const toolRoutes = @json($toolRoutes);

    const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));
    const textToHtml = (value) => esc(value).replace(/\n/g, '<br>');
    const modeLabel = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    const feedback = () => window.AcadFlowFeedback;
    const updateTool = () => {
        styleWrap.classList.toggle('hidden', tool.value !== 'citation');
        const route = toolRoutes[tool.value] || toolRoutes.ask;
        if (!route) return;
        modeBadge.textContent = modeLabel(route.mode);
        providerBadge.textContent = route.provider || 'Unavailable';
        modelBadge.textContent = route.model || 'Provider default';
    };

    tool.addEventListener('change', updateTool);
    updateTool();

    document.querySelectorAll('.quick-prompt').forEach(button => button.addEventListener('click', () => {
        if (button.dataset.tool) tool.value = button.dataset.tool;
        updateTool();
        message.value = button.dataset.prompt || '';
        message.focus();
    }));

    const appendFailure = (detail, text) => {
        const card = document.createElement('div');
        card.className = 'max-w-2xl rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800';

        const title = document.createElement('div');
        title.className = 'font-bold text-rose-900';
        title.textContent = 'AI request not completed';
        card.appendChild(title);

        const body = document.createElement('p');
        body.className = 'mt-1 leading-6';
        body.textContent = detail.message;
        card.appendChild(body);

        if (detail.requestId) {
            const ref = document.createElement('p');
            ref.className = 'mt-2 text-[11px] text-rose-500';
            ref.textContent = `Request ID: ${detail.requestId}`;
            card.appendChild(ref);
        }

        if (detail.retryable) {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'mt-3 rounded-xl bg-rose-700 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60';
            retry.textContent = 'Try Again';
            retry.addEventListener('click', async () => {
                if (retry.disabled) return;
                retry.disabled = true;
                retry.textContent = 'Trying…';
                card.remove();
                await sendRequest(text, true);
            });
            card.appendChild(retry);
        }

        thread.appendChild(card);
    };

    async function sendRequest(text, isRetry = false) {
        if (!isRetry) {
            thread.insertAdjacentHTML('beforeend', `<div class="ml-auto max-w-2xl rounded-2xl bg-slate-950 p-4 text-sm leading-6 text-white"><div class="mb-1 text-xs font-bold text-slate-300">You</div>${textToHtml(text)}</div>`);
        }

        message.value = '';
        submit.disabled = true;
        submit.textContent = isRetry ? 'Retrying…' : 'Working…';
        thread.scrollTop = thread.scrollHeight;

        try {
            const response = await fetch(@json(route('ai.assistant.ask')), {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
                body: JSON.stringify({tool: tool.value, message: text, course_id: course.value || null, style: style.value})
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                const requestError = new Error(data.message || data.answer || 'AI assistance is currently unavailable.');
                requestError.data = data;
                requestError.status = response.status;
                throw requestError;
            }

            const sourceList = Array.isArray(data.sources) && data.sources.length
                ? `<div class="mt-3 border-t border-indigo-100 pt-3 text-[11px] text-slate-500"><strong>Sources:</strong> ${data.sources.map(s => `${esc(s.label)} ${esc(s.title)}${s.locator ? ` (${esc(s.locator)})` : ''}`).join(' · ')}</div>`
                : '';
            const meta = `<div class="mt-2 text-[10px] uppercase tracking-wide text-slate-400">${esc(data.provider || 'AcadFlow AI')}${data.model ? ` · ${esc(data.model)}` : ''}${data.fallback_used ? ' · fallback used' : ''}${data.cached ? ' · cached' : ''}</div>`;
            thread.insertAdjacentHTML('beforeend', `<div class="max-w-2xl rounded-2xl bg-indigo-50 p-4 text-sm leading-6 text-slate-700"><div class="mb-1 font-bold text-slate-950">AcadFlow Assistant</div>${textToHtml(data.answer || 'No response was returned.')}${sourceList}${meta}</div>`);
        } catch (error) {
            const detail = feedback()?.normalize(error, 'AI assistance is currently unavailable. Please try again.') || {
                message: 'AI assistance is currently unavailable. Please try again.', retryable: true, requestId: null
            };
            if (!message.value.trim()) message.value = text;
            appendFailure(detail, text);
        } finally {
            submit.disabled = false;
            submit.textContent = 'Ask AI Assistant →';
            thread.scrollTop = thread.scrollHeight;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = message.value.trim();
        if (!text || submit.disabled) return;
        await sendRequest(text, false);
    });
})();
</script>
@endsection
