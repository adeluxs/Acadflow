function unwrapJsonAnswer(value) {
    let text = String(value ?? '').trim();
    if (!text) return '';

    text = text.replace(/^```(?:json|JSON)?\s*/, '').replace(/\s*```$/, '').trim();

    try {
        const decoded = JSON.parse(text);
        if (decoded && typeof decoded === 'object') {
            for (const key of ['answer', 'response', 'content', 'text']) {
                if (typeof decoded[key] === 'string' && decoded[key].trim()) {
                    return unwrapJsonAnswer(decoded[key]);
                }
            }
        }
    } catch (_) {
        // Not JSON; render as plain assistant text.
    }

    // Handle JSON-like provider output that is not valid JSON because the
    // assistant placed raw newlines inside the quoted answer. This avoids
    // rendering `{ "answer": "..." }` to users.
    if (text.startsWith('{')) {
        const jsonish = text.match(/["'](?:answer|response|content|text)["']\s*:\s*(["'])([\s\S]*)/i);
        if (jsonish?.[1]) {
            let answer = extractJsonLikeStringValue(jsonish[2], jsonish[1]);

            if (answer) {
                return answer;
            }
        }
    }

    return text;
}

function extractJsonLikeStringValue(tail, quote) {
    let answer = String(tail || '').trim();
    if (!answer) return '';

    const finalClose = new RegExp(`${quote}\\s*}\\s*$`, 'u');
    const nextKey = new RegExp(`${quote}\\s*,\\s*["'][A-Za-z0-9_ -]+["']\\s*:`, 'u');
    const finalCloseMatch = [...answer.matchAll(new RegExp(finalClose, 'gu'))].at(-1);
    const nextKeyMatch = answer.match(nextKey);
    const cutPoints = [finalCloseMatch?.index, nextKeyMatch?.index].filter(Number.isInteger);

    if (cutPoints.length) {
        answer = answer.slice(0, Math.min(...cutPoints)).trim();
    }

    return answer
        .replace(/\\n/g, '\n')
        .replace(/\\r/g, '\r')
        .replace(/\\t/g, '\t')
        .replace(/\\"/g, '"')
        .trim();
}

function textOf(value, fallback = '') {
    return cleanAiText(unwrapJsonAnswer(value)) || fallback;
}

function cleanAiText(value) {
    let text = String(value ?? '').trim();
    if (!text) return '';

    text = text.replace(/^```(?:json|JSON|markdown|md)?\s*/, '').replace(/\s*```$/, '').trim();
    text = text.replace(/\r\n?/g, '\n');
    text = text.replace(/\s*\[S\d+\]/g, '');

    text = text.split('\n').map((line) => {
        let clean = line.trim();
        if (/^[-*_]{3,}$/.test(clean)) return '';
        clean = clean
            .replace(/^#{1,6}\s*/, '')
            .replace(/^>\s?/, '')
            .replace(/^[-*+]\s+/, '')
            .replace(/^\d+[.)]\s+/, '')
            .replace(/\*\*([^*]+)\*\*/g, '$1')
            .replace(/__([^_]+)__/g, '$1')
            .replace(/\*([^*]+)\*/g, '$1')
            .replace(/_([^_]+)_/g, '$1')
            .replace(/`([^`]+)`/g, '$1')
            .replace(/\[([^\]]+)]\([^)]+\)/g, '$1')
            .replace(/\s*\[S\d+\]/g, '');
        return clean.trim();
    }).join('\n');

    return text.replace(/\n{3,}/g, '\n\n').trim();
}

function setHidden(element, hidden = true) {
    if (!element) return;
    element.classList.toggle('hidden', hidden);
}

function clearChildren(element) {
    if (!element) return;
    while (element.firstChild) element.removeChild(element.firstChild);
}

function normalizeAssistantError(error) {
    if (error?.name === 'AbortError') {
        return {
            message: 'The AI response took too long to reach the page. Please try again.',
            retryable: true,
            requestId: null,
        };
    }

    return window.AcadFlowFeedback?.normalize?.(error, 'AI assistance is currently unavailable. Please try again.') || {
        message: 'AI assistance is currently unavailable. Please try again.',
        retryable: true,
        requestId: error?.data?.request_id || null,
    };
}

function extractRequestId(response, data, error) {
    return data?.request_id
        || error?.requestId
        || response?.headers?.get?.('X-Request-Id')
        || response?.headers?.get?.('X-Correlation-Id')
        || null;
}

function bindContextualAssistant(root) {
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const form = root.querySelector('[data-ai-form]');
    const question = root.querySelector('[data-ai-question]');
    const submit = root.querySelector('[data-ai-submit]');
    const result = root.querySelector('[data-ai-result]');
    const answer = root.querySelector('[data-ai-answer]');
    const provider = root.querySelector('[data-ai-provider]');
    const model = root.querySelector('[data-ai-model]');
    const fallback = root.querySelector('[data-ai-fallback]');
    const sourcesWrap = root.querySelector('[data-ai-sources-wrap]');
    const sources = root.querySelector('[data-ai-sources]');
    const request = root.querySelector('[data-ai-request]');
    const retry = root.querySelector('[data-ai-retry]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    if (!form || !question || !submit || !result || !answer) return;

    const releaseBusyState = () => {
        root.dataset.aiBusy = '0';
        submit.disabled = false;
        submit.textContent = 'Ask AI';
        window.AcadFlowLoader?.reset?.();
    };

    const resetResult = () => {
        result.classList.remove('hidden');
        answer.textContent = 'Processing your request…';
        setHidden(provider, true);
        setHidden(model, true);
        setHidden(fallback, true);
        setHidden(sourcesWrap, true);
        setHidden(request, true);
        setHidden(retry, true);
        clearChildren(sources);
    };

    const showMetadata = (data = {}) => {
        if (data.provider && provider) {
            provider.textContent = `Provider: ${data.provider}`;
            setHidden(provider, false);
        }
        if (data.model && model) {
            model.textContent = `Model: ${data.model}`;
            setHidden(model, false);
        }
        setHidden(fallback, !data.fallback_used);
    };

    const showRequestId = (requestId) => {
        if (!request || !requestId) return;
        request.textContent = `Request ID: ${requestId}`;
        setHidden(request, false);
    };

    const showSources = (sourceRows) => {
        if (!Array.isArray(sourceRows) || sourceRows.length === 0 || !sources || !sourcesWrap) return;
        clearChildren(sources);
        sourceRows.forEach((source) => {
            const row = document.createElement('div');
            row.textContent = `${source?.label || 'S'} · ${source?.title || 'AcadSyntra source'}${source?.locator ? ` · ${source.locator}` : ''}`;
            sources.appendChild(row);
        });
        setHidden(sourcesWrap, false);
    };

    const showFailure = (error, response = null) => {
        const detail = normalizeAssistantError(error);
        const errorData = error?.data || {};
        answer.textContent = detail.message;
        showMetadata(errorData);
        showRequestId(extractRequestId(response, errorData, detail));
        if (detail.retryable && retry) setHidden(retry, false);
        console.warn('[AcadSyntra AI] Contextual assistant request did not render a successful answer.', {
            status: error?.status || response?.status || null,
            requestId: extractRequestId(response, errorData, detail),
            error,
        });
    };

    root.querySelectorAll('[data-ai-suggestion]').forEach((button) => button.addEventListener('click', () => {
        question.value = button.dataset.aiSuggestion || '';
        question.focus();
    }));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const value = question.value.trim();
        if (value.length < 3 || root.dataset.aiBusy === '1') return;

        submit.disabled = true;
        submit.textContent = 'Thinking…';
        root.dataset.aiBusy = '1';
        resetResult();

        const requestTimeoutMs = Math.max(5000, Number(root.dataset.aiTimeout || 28000));
        const abortController = new AbortController();
        const timeoutId = window.setTimeout(() => abortController.abort(), requestTimeoutMs);
        let response = null;

        try {
            response = await fetch(form.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-AcadFlow-Loader': 'silent',
                },
                body: JSON.stringify({ question: value }),
                cache: 'no-store',
                credentials: 'same-origin',
                signal: abortController.signal,
            });

            const rawText = await response.text();
            let data = {};
            try {
                data = rawText ? JSON.parse(rawText) : {};
            } catch (_) {
                data = {
                    success: false,
                    message: 'AcadSyntra received an unreadable AI response. Please try again.',
                    raw: rawText,
                };
            }

            if (!response.ok || data.success === false) {
                const requestError = new Error(data.message || data.answer || 'AI assistance is currently unavailable.');
                requestError.data = data;
                requestError.status = response.status;
                throw requestError;
            }

            answer.textContent = textOf(data.answer, 'AcadSyntra received a successful AI response, but it contained no displayable answer.');
            showMetadata(data);
            // Source labels and request IDs are intentionally hidden from the
            // user-facing assistant card. They remain available in logs/network
            // responses for diagnostics without cluttering the answer.
            console.info('[AcadSyntra AI] Contextual assistant response rendered.', {
                requestId: data.request_id || null,
                provider: data.provider || null,
                model: data.model || null,
            });
        } catch (error) {
            showFailure(error, response);
        } finally {
            window.clearTimeout(timeoutId);
            releaseBusyState();
        }
    });

    window.setInterval(() => {
        if (root.dataset.aiBusy === '1' && answer.textContent === 'Processing your request…') {
            answer.textContent = 'The AI request did not complete in the page. Please try again; if it keeps happening, refresh the page to load the latest assistant script.';
            setHidden(retry, false);
            releaseBusyState();
            console.warn('[AcadSyntra AI] Recovered stale contextual assistant busy state.');
        }
    }, Math.max(8000, Number(root.dataset.aiTimeout || 28000) + 1500));

    retry?.addEventListener('click', () => {
        if (!submit.disabled) form.requestSubmit();
    });
}

export function initContextualAssistants() {
    document.querySelectorAll('[data-contextual-ai]').forEach(bindContextualAssistant);
}
