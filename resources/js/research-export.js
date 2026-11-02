export function initResearchExport() {
    const panel = document.querySelector('[data-export-progress]');
    if (!panel) return;
    const message = panel.querySelector('[data-export-message]');
    const download = panel.querySelector('[data-export-download]');
    const retry = panel.querySelector('[data-export-retry]');
    let stopped = ['completed', 'failed'].includes(panel.dataset.exportState), timer, busy = false;
    async function poll() {
        clearTimeout(timer);
        if (stopped || document.hidden || busy) return;
        busy = true; retry.disabled = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(panel.dataset.exportProgress, { signal: controller.signal, headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error();
            const result = await response.json();
            panel.dataset.exportState = result.status;
            stopped = ['completed', 'failed'].includes(result.status);
            message.textContent = result.status === 'completed' ? 'Your export is ready.' : result.status === 'failed' ? 'The export could not be completed. Return to your workspace to retry.' : 'Preparing your document in the background. You can continue working.';
            if (result.download) { download.href = result.download; download.hidden = false; }
            retry.hidden = true;
            if (!stopped) timer = setTimeout(poll, 3000);
        } catch { message.textContent = 'The status could not be checked. Your document is still queued; try again shortly.'; retry.hidden = false; }
        finally { clearTimeout(timeout); busy = false; retry.disabled = false; }
    }
    retry.addEventListener('click', poll);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) void poll(); });
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); });
    window.addEventListener('pageshow', event => { if (event.persisted) { stopped = ['completed', 'failed'].includes(panel.dataset.exportState); void poll(); } });
    void poll();
}
