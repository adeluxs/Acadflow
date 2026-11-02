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

export function installAxiosLoader() {
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

// A document navigation normally replaces this page, which clears the overlay.
// When it does not (an unsaved-work prompt answered "Stay", a 204/attachment
// response, Esc/Stop, a slow server), the overlay must never trap the user.
const NAVIGATION_WATCHDOG_MS = 10000;
const SUBMIT_WATCHDOG_MS = 45000;
let navigationToken = null;
let navigationWatchdog = null;

function endNavigation() {
    clearTimeout(navigationWatchdog);
    navigationWatchdog = null;
    if (navigationToken) end(navigationToken);
    navigationToken = null;
}

function beginNavigation(message, delay, watchdogMs) {
    endNavigation();
    navigationToken = begin(message, { delay });
    // Release the full-screen overlay; the browser's own progress indicator
    // and the thin navigation bar still show that the request is running.
    navigationWatchdog = window.setTimeout(endNavigation, watchdogMs);
}

/**
 * Listen once for this navigation's beforeunload, after every page handler
 * (listeners added now run last). A cancelled beforeunload means the browser
 * showed a "Leave site?" prompt; if the user stayed, no navigation follows.
 */
function watchUnloadPrompt() {
    const state = { prompted: false };
    const listener = (event) => { state.prompted = event.defaultPrevented || (typeof event.returnValue === 'string' && event.returnValue !== ''); };
    window.addEventListener('beforeunload', listener, { once: true });
    state.done = () => window.removeEventListener('beforeunload', listener);
    return state;
}

function installNavigationLoader() {
    document.addEventListener('click', (event) => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
        if (!link || link.dataset.noLoader === '1' || link.hasAttribute('download')) return;
        const target = link.target || document.querySelector('base[target]')?.target;
        if (target && target.toLowerCase() !== '_self') return;
        const url = new URL(link.href, window.location.href);
        if (!/^https?:$/.test(url.protocol) || url.origin !== window.location.origin) return;
        if (url.pathname === location.pathname && url.search === location.search && (url.hash || url.href.endsWith('#'))) return;
        // Attachments leave the current document open, so no navigation will hide an overlay.
        if (/\/(?:download|export)(?:[/.]|$)/i.test(url.pathname)) return;
        const prompt = watchUnloadPrompt();
        setTimeout(() => {
            prompt.done();
            if (prompt.prompted) window.dispatchEvent(new CustomEvent('acadflow:navigation-cancelled'));
            if (event.defaultPrevented || prompt.prompted) return;
            beginNavigation('Loading page…', 400, NAVIGATION_WATCHDOG_MS);
        }, 0);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.noLoader === '1') return;
        const prompt = watchUnloadPrompt();

        setTimeout(() => {
            prompt.done();
            if (prompt.prompted) window.dispatchEvent(new CustomEvent('acadflow:navigation-cancelled'));
            if (event.defaultPrevented || prompt.prompted) return;
            const target = event.submitter?.formTarget || form.target || document.querySelector('base[target]')?.target;
            if (target && target.toLowerCase() !== '_self') return;
            const method = String(event.submitter?.getAttribute('formmethod') || form.method || 'get').toUpperCase();
            if (method === 'DIALOG') return;
            const label = new URL(form.action).pathname === '/logout' ? 'Signing out…' : method === 'GET' ? 'Searching…' : 'Saving changes…';
            beginNavigation(label, method === 'GET' ? 400 : 100, method === 'GET' ? NAVIGATION_WATCHDOG_MS : SUBMIT_WATCHDOG_MS);
        }, 0);
    }, true);

    // bfcache restores, history traversal and returning to the tab all mean
    // this document is still (or again) the one in front of the user.
    window.addEventListener('pageshow', () => { endNavigation(); reset(); });
    window.addEventListener('popstate', endNavigation);
}

export function initGlobalLoader() {
    window.AcadFlowLoader = { begin, end, show: reveal, hide: reset, reset };
    installFetchLoader();
    installAxiosLoader();
    installNavigationLoader();
}
