const TECHNICAL_MARKERS = [
    'stack trace', 'exception trace', 'guzzlehttp\\', 'vendor/guzzlehttp', 'curlfactory.php',
    'symfony\\component', 'illuminate\\', 'sqlstate[', 'uncaught exception', 'fatal error',
    '/vendor/', '\\vendor\\', ' on line ', '#0 ', '#1 ', 'requestexception', 'connectexception',
    'connectionexception', 'traceback', '.php:'
];

function containsTechnicalDetails(value) {
    const text = String(value || '').toLowerCase();
    return text.length > 500 || TECHNICAL_MARKERS.some(marker => text.includes(marker));
}

export function safeErrorMessage(value, fallback = "We couldn't complete your request right now. Please try again.") {
    const text = String(value || '').trim();
    if (!text || containsTechnicalDetails(text)) return fallback;
    return text;
}

export function normalizeError(error, fallback) {
    const data = error?.response?.data || error?.data || {};
    const status = error?.response?.status || error?.status || null;
    const networkFailure = !error?.response && (error?.request || error instanceof TypeError);
    const message = networkFailure
        ? "We couldn't connect to the service. Check your connection and try again."
        : safeErrorMessage(data?.message || error?.message, fallback);

    return {
        message,
        code: data?.code || null,
        requestId: data?.request_id || error?.response?.headers?.['x-request-id'] || null,
        retryable: data?.retryable === true || networkFailure || status === 408 || status === 429 || status >= 500,
        status,
    };
}

function feedbackContainer() {
    let container = document.getElementById('acadflow-feedback-container');
    if (container) return container;

    container = document.createElement('div');
    container.id = 'acadflow-feedback-container';
    container.className = 'fixed right-4 top-4 z-[100] flex w-[min(92vw,24rem)] flex-col gap-3 pointer-events-none';
    container.setAttribute('aria-live', 'polite');
    document.body.appendChild(container);
    return container;
}

function stylesFor(type) {
    return {
        success: ['border-emerald-200', 'bg-emerald-50', 'text-emerald-950'],
        warning: ['border-amber-200', 'bg-amber-50', 'text-amber-950'],
        info: ['border-sky-200', 'bg-sky-50', 'text-sky-950'],
        error: ['border-rose-200', 'bg-white', 'text-slate-900'],
    }[type] || ['border-slate-200', 'bg-white', 'text-slate-900'];
}

export function showFeedback({
    type = 'error',
    title,
    message,
    requestId = null,
    onRetry = null,
    retryLabel = 'Try Again',
    duration = type === 'success' ? 4500 : 8000,
} = {}) {
    if (!document?.body) return null;

    const container = feedbackContainer();
    const card = document.createElement('div');
    card.className = `pointer-events-auto rounded-2xl border p-4 shadow-xl shadow-slate-900/10 ${stylesFor(type).join(' ')}`;
    card.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const header = document.createElement('div');
    header.className = 'flex items-start justify-between gap-3';

    const copy = document.createElement('div');
    copy.className = 'min-w-0 flex-1';

    const heading = document.createElement('p');
    heading.className = 'text-sm font-bold';
    heading.textContent = title || (type === 'error' ? 'Request not completed' : 'Update');
    copy.appendChild(heading);

    const body = document.createElement('p');
    body.className = 'mt-1 text-sm leading-6 text-slate-600';
    body.textContent = safeErrorMessage(message, type === 'error' ? "We couldn't complete your request right now. Please try again." : '');
    copy.appendChild(body);

    if (requestId) {
        const ref = document.createElement('p');
        ref.className = 'mt-1 text-[11px] text-slate-400';
        ref.dataset.requestRef = '1';
        ref.textContent = `Request ID: ${requestId}`;
        copy.appendChild(ref);
    }

    header.appendChild(copy);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'rounded-lg px-2 py-1 text-sm text-slate-400 hover:bg-slate-100 hover:text-slate-700';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.textContent = '×';
    close.addEventListener('click', () => card.remove());
    header.appendChild(close);
    card.appendChild(header);

    if (typeof onRetry === 'function') {
        const actions = document.createElement('div');
        actions.className = 'mt-3 flex items-center gap-2';
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-60';
        retry.textContent = retryLabel;
        retry.addEventListener('click', async () => {
            if (retry.disabled) return;
            retry.disabled = true;
            const original = retry.textContent;
            retry.textContent = 'Trying…';
            try {
                await onRetry();
                card.remove();
            } catch (retryError) {
                const detail = normalizeError(retryError);
                body.textContent = detail.message;
                if (detail.requestId) {
                    const existingRef = copy.querySelector('[data-request-ref]');
                    if (existingRef) existingRef.textContent = `Request ID: ${detail.requestId}`;
                }
            } finally {
                retry.disabled = false;
                retry.textContent = original;
            }
        });
        actions.appendChild(retry);
        card.appendChild(actions);
    }

    container.appendChild(card);
    if (duration > 0 && typeof onRetry !== 'function') {
        window.setTimeout(() => card.remove(), duration);
    }
    return card;
}

export function showError(error, options = {}) {
    const detail = normalizeError(error, options.fallback);
    return showFeedback({
        type: 'error',
        title: options.title || 'Request not completed',
        message: detail.message,
        requestId: detail.requestId,
        onRetry: detail.retryable ? options.onRetry : null,
        retryLabel: options.retryLabel || 'Try Again',
        duration: options.duration ?? 0,
    });
}

export function initErrorFeedback() {
    window.AcadFlowFeedback = {
        safeMessage: safeErrorMessage,
        normalize: normalizeError,
        show: showFeedback,
        error: showError,
    };

    if (window.axios?.interceptors?.response) {
        window.axios.interceptors.response.use(
            response => response,
            error => {
                const detail = normalizeError(error);
                error.userMessage = detail.message;
                error.retryable = detail.retryable;
                error.requestId = detail.requestId;
                return Promise.reject(error);
            }
        );
    }
}
