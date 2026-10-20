const backgroundRequestPatterns = [
    /\/sync(?:\/|\?|$)/i,
    /\/offline(?:\/|\?|$)/i,
    /\/attendance\/.*(?:qr|heartbeat|refresh)/i,
];

let tokenSequence = 0;
const activeTokens = new Map();
let revealTimer = null;

function loaderElement() {
    return document.getElementById('acadflow-global-loader');
}

function updateLabel(message) {
    const label = loaderElement()?.querySelector('[data-acadflow-loader-label]');
    if (label && message) label.textContent = message;
}

function reveal(message = 'Loading AcadSyntra…') {
    const loader = loaderElement();
    if (!loader) return;
    updateLabel(message);
    loader.dataset.state = 'visible';
    loader.setAttribute('aria-hidden', 'false');
    document.documentElement.dataset.acadflowLoading = '1';
}

function conceal() {
    const loader = loaderElement();
    if (!loader) return;
    loader.dataset.state = 'hidden';
    loader.setAttribute('aria-hidden', 'true');
    delete document.documentElement.dataset.acadflowLoading;
}

function begin(message = 'Loading AcadSyntra…', options = {}) {
    const token = `acadflow-loader-${++tokenSequence}`;
    const delay = Number.isFinite(options.delay) ? Math.max(0, options.delay) : 160;
    activeTokens.set(token, { message });

    clearTimeout(revealTimer);
    if (delay === 0) {
        reveal(message);
    } else {
        revealTimer = window.setTimeout(() => {
            if (activeTokens.has(token)) reveal(message);
        }, delay);
    }

    return token;
}

function end(token) {
    if (token) activeTokens.delete(token);
    if (activeTokens.size > 0) {
        const latest = Array.from(activeTokens.values()).at(-1);
        if (latest?.message) updateLabel(latest.message);
        return;
    }

    clearTimeout(revealTimer);
    revealTimer = null;
    conceal();
}

function reset() {
    activeTokens.clear();
    clearTimeout(revealTimer);
    revealTimer = null;
    conceal();
}

function requestUrl(input) {
    try {
        if (input instanceof Request) return new URL(input.url, window.location.href);
        return new URL(String(input), window.location.href);
    } catch {
        return null;
    }
}

function isBackgroundRequest(input, init = {}) {
    if (init?.acadflowSilent === true) return true;
    const headers = new Headers(init?.headers || (input instanceof Request ? input.headers : undefined));
    if (headers.get('X-AcadFlow-Loader') === 'silent') return true;
    const url = requestUrl(input);
    return url ? backgroundRequestPatterns.some((pattern) => pattern.test(url.pathname + url.search)) : false;
}

function fetchMethod(input, init = {}) {
    return String(init?.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
}

function installFetchLoader() {
    if (!window.fetch || window.fetch.__acadflowLoaderWrapped) return;
    const originalFetch = window.fetch.bind(window);

    const wrappedFetch = async (input, init = {}) => {
        const method = fetchMethod(input, init);
        // Page GETs use native navigation/form feedback. Background polling should
        // not cover the screen. Foreground mutations and AI/API actions do.
        const shouldShow = method !== 'GET' && method !== 'HEAD' && !isBackgroundRequest(input, init);
        const token = shouldShow ? begin('Processing request…', { delay: 180 }) : null;

        try {
            return await originalFetch(input, init);
        } finally {
            if (token) end(token);
        }
    };

    wrappedFetch.__acadflowLoaderWrapped = true;
    window.fetch = wrappedFetch;
}

function installAxiosLoader() {
    const axios = window.axios;
    if (!axios?.interceptors || axios.__acadflowLoaderInstalled) return;
    axios.__acadflowLoaderInstalled = true;

    axios.interceptors.request.use((config) => {
        const method = String(config.method || 'get').toUpperCase();
        const silent = config.headers?.['X-AcadFlow-Loader'] === 'silent';
        if (!silent && method !== 'GET' && method !== 'HEAD') {
            config.__acadflowLoaderToken = begin('Processing request…', { delay: 180 });
        }
        return config;
    });

    const finish = (value) => {
        const config = value?.config;
        if (config?.__acadflowLoaderToken) end(config.__acadflowLoaderToken);
        return value;
    };

    axios.interceptors.response.use(finish, (error) => {
        finish(error);
        return Promise.reject(error);
    });
}

function installNavigationLoader() {
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const anchor = event.target.closest?.('a[href]');
        if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download') || anchor.dataset.noLoader === '1') return;

        let url;
        try { url = new URL(anchor.href, window.location.href); } catch { return; }
        if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return;
        if (url.hash && url.pathname === location.pathname && url.search === location.search) return;

        begin('Loading page…', { delay: 180 });
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.noLoader === '1') return;

        queueMicrotask(() => {
            if (event.defaultPrevented) return;
            const method = String(form.method || 'get').toUpperCase();
            begin(method === 'GET' ? 'Searching…' : 'Saving changes…', { delay: 100 });
        });
    }, true);

    window.addEventListener('pageshow', reset);
    window.addEventListener('pagehide', () => reveal('Loading page…'));
}

export function initGlobalLoader() {
    window.AcadFlowLoader = { begin, end, show: reveal, hide: reset, reset };
    installFetchLoader();
    installAxiosLoader();
    installNavigationLoader();
}
