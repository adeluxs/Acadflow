function formatWait(seconds) {
    const safeSeconds = Math.max(0, Math.ceil(seconds));
    if (safeSeconds < 60) {
        return `${safeSeconds} ${safeSeconds === 1 ? 'second' : 'seconds'}`;
    }

    const minutes = Math.ceil(safeSeconds / 60);
    return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`;
}

function initCountdown(node) {
    let remaining = Math.max(0, Number(node.dataset.retryAfter || 0));
    const prefix = node.dataset.retryPrefix || 'You can try again in ';

    const render = () => {
        if (remaining <= 0) {
            node.textContent = 'You can try again now.';
            node.dataset.ready = '1';
            return false;
        }

        node.textContent = `${prefix}${formatWait(remaining)}.`;
        return true;
    };

    if (!render()) return;

    const timer = window.setInterval(() => {
        remaining -= 1;
        if (!render()) window.clearInterval(timer);
    }, 1000);
}

export function initRateLimitFeedback() {
    document.querySelectorAll('[data-rate-limit-countdown]').forEach(initCountdown);
}
