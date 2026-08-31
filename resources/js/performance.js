/**
 * AcadFlow interaction performance helpers.
 *
 * Goals:
 * - one click should always start navigation immediately;
 * - warm likely same-origin GET pages without hijacking normal browser links;
 * - prevent accidental duplicate POST/PUT/PATCH/DELETE form submissions;
 * - give immediate visual feedback while a server-rendered navigation loads.
 *
 * This intentionally does NOT replace Laravel routing with a client router.
 */

const prefetched = new Set();
let prefetchCount = 0;
const MAX_PREFETCHES_PER_PAGE = 8;

function connectionAllowsPrefetch() {
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (!connection) return true;
    if (connection.saveData) return false;
    return !['slow-2g', '2g'].includes(connection.effectiveType);
}

function eligibleLink(anchor) {
    if (!(anchor instanceof HTMLAnchorElement)) return null;
    if (anchor.target && anchor.target !== '_self') return null;
    if (anchor.hasAttribute('download') || anchor.dataset.noPrefetch === '1') return null;
    if (anchor.getAttribute('rel')?.split(/\s+/).includes('external')) return null;

    let url;
    try { url = new URL(anchor.href, window.location.href); } catch { return null; }
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return null;
    if (url.hash && url.pathname === location.pathname && url.search === location.search) return null;
    if (url.href === window.location.href) return null;
    if (/\/(logout|download|destroy|delete|remove)(\/|$)/i.test(url.pathname)) return null;
    return url;
}

function prefetch(anchor) {
    if (!connectionAllowsPrefetch() || document.visibilityState !== 'visible') return;
    const url = eligibleLink(anchor);
    if (!url || prefetched.has(url.href) || prefetchCount >= MAX_PREFETCHES_PER_PAGE) return;

    prefetched.add(url.href);
    prefetchCount += 1;

    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.as = 'document';
    link.href = url.href;
    link.fetchPriority = 'low';
    document.head.appendChild(link);
}

function ensureProgressBar() {
    let bar = document.getElementById('acadflow-navigation-progress');
    if (bar) return bar;

    bar = document.createElement('div');
    bar.id = 'acadflow-navigation-progress';
    bar.setAttribute('aria-hidden', 'true');
    Object.assign(bar.style, {
        position: 'fixed',
        inset: '0 auto auto 0',
        width: '0',
        height: '3px',
        zIndex: '99999',
        background: 'var(--acad-primary, #4f46e5)',
        opacity: '0',
        transition: 'width 180ms ease, opacity 120ms ease',
        pointerEvents: 'none',
    });
    document.body.appendChild(bar);
    return bar;
}

function startNavigationFeedback(anchor) {
    const url = eligibleLink(anchor);
    if (!url) return;
    const bar = ensureProgressBar();
    bar.style.opacity = '1';
    bar.style.width = '68%';
    document.documentElement.dataset.navigating = '1';
    anchor.setAttribute('aria-busy', 'true');
}

function finishNavigationFeedback() {
    const bar = document.getElementById('acadflow-navigation-progress');
    if (bar) {
        bar.style.width = '100%';
        setTimeout(() => {
            bar.style.opacity = '0';
            bar.style.width = '0';
        }, 120);
    }
    delete document.documentElement.dataset.navigating;
    document.querySelectorAll('a[aria-busy="true"]').forEach((anchor) => anchor.removeAttribute('aria-busy'));
}

function initializeLinkAcceleration() {
    let hoverTimer = null;

    document.addEventListener('pointerover', (event) => {
        const anchor = event.target.closest?.('a[href]');
        if (!anchor) return;
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(() => prefetch(anchor), 120);
    }, { passive: true });

    document.addEventListener('focusin', (event) => {
        const anchor = event.target.closest?.('a[href]');
        if (anchor) prefetch(anchor);
    });

    document.addEventListener('touchstart', (event) => {
        const anchor = event.target.closest?.('a[href]');
        if (anchor) prefetch(anchor);
    }, { passive: true });

    // Never prevent the click. The native browser navigation remains the source
    // of truth; this only gives immediate feedback on the first click.
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const anchor = event.target.closest?.('a[href]');
        if (anchor) startNavigationFeedback(anchor);
    }, true);
}

function initializeDuplicateSubmitProtection() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        // JS/AJAX forms own their own pending state and normally call
        // preventDefault(). Wait until the current event stack completes before
        // deciding whether this is a native navigation form submission.
        queueMicrotask(() => {
            if (event.defaultPrevented || form.dataset.allowMultipleSubmit === '1') return;
            const method = (form.method || 'get').toLowerCase();
            if (method === 'get') return;
            if (form.dataset.submitting === '1') return;

            form.dataset.submitting = '1';
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.disabled = true;
                control.setAttribute('aria-disabled', 'true');
                if (control instanceof HTMLButtonElement && control.dataset.loadingText) {
                    control.dataset.originalText = control.textContent || '';
                    control.textContent = control.dataset.loadingText;
                }
            });
        });
    }, true);
}

export function initPerformanceUX() {
    initializeLinkAcceleration();
    initializeDuplicateSubmitProtection();
    window.addEventListener('pageshow', finishNavigationFeedback);
}
