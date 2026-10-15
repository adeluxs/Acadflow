/**
 * Lecturer AI Studio client.
 *
 * Powers the material create/edit AI panel. It requests a draft from the
 * central AI runtime and lets the lecturer explicitly insert that draft into
 * the rich editor. Nothing is saved automatically.
 */
export function initLecturerAi() {
    const panels = [...document.querySelectorAll('[data-lecturer-ai]')];
    if (!panels.length) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&', '<': '<', '>': '>', '"': '"', "'": '&#039;',
    }[ch]));

    panels.forEach((panel) => {
        if (panel.dataset.bound === '1') return;
        panel.dataset.bound = '1';

        const endpoint = panel.dataset.endpoint;
        const targetId = panel.dataset.target;
        const instruction = panel.querySelector('[data-ai-instruction]');
        const result = panel.querySelector('[data-ai-result]');
        const answer = panel.querySelector('[data-ai-answer]');
        const status = panel.querySelector('[data-ai-status]');
        const insertButton = panel.querySelector('[data-ai-insert]');
        const copyButton = panel.querySelector('[data-ai-copy]');
        const retryButton = panel.querySelector('[data-ai-retry]');
        const actionButtons = [...panel.querySelectorAll('[data-ai-action]')];

        let currentDraft = '';
        let lastAction = 'draft';

        const editorText = () => {
            const el = document.getElementById(targetId);
            if (!el) return '';
            if (typeof el.value === 'string') return el.value;
            return '';
        };

        const setBusy = (busy) => {
            actionButtons.forEach((button) => { button.disabled = busy; });
        };

        const hideResult = () => {
            result?.classList.add('hidden');
            insertButton?.classList.add('hidden');
            copyButton?.classList.add('hidden');
            retryButton?.classList.add('hidden');
            if (answer) answer.textContent = '';
        };

        const insertIntoEditor = (text) => {
            if (!text) return;
            const api = window.AcadFlowRichEditor;
            if (api && typeof api.insert === 'function' && api.insert(targetId, text)) {
                return;
            }
            const el = document.getElementById(targetId);
            if (!el) return;
            const existing = String(el.value || '');
            el.value = existing.trim() ? `${existing}\n\n${text}` : text;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        const request = async (action) => {
            if (!endpoint) return;
            lastAction = action;
            const prompt = (instruction?.value || '').trim();
            if (prompt.length < 3) {
                instruction?.focus();
                return;
            }

            result?.classList.remove('hidden');
            if (status) status.textContent = 'Generating a draft…';
            if (answer) answer.textContent = '';
            insertButton?.classList.add('hidden');
            copyButton?.classList.add('hidden');
            retryButton?.classList.add('hidden');
            setBusy(true);

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        action,
                        instruction: prompt,
                        current_content: editorText().slice(0, 190000),
                    }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok || data.success === false) {
                    throw Object.assign(
                        new Error(data.answer || data.message || 'AI assistance is currently unavailable.'),
                        { data }
                    );
                }

                currentDraft = String(data.answer || '').trim();
                if (status) status.textContent = 'Draft ready — review before inserting';
                if (answer) answer.textContent = currentDraft || 'No draft was returned.';
                if (currentDraft) {
                    insertButton?.classList.remove('hidden');
                    copyButton?.classList.remove('hidden');
                }
            } catch (error) {
                if (status) status.textContent = 'Draft not generated';
                if (answer) answer.textContent = error?.message || 'AI assistance is currently unavailable. Your work has not been lost.';
                retryButton?.classList.remove('hidden');
            } finally {
                setBusy(false);
            }
        };

        actionButtons.forEach((button) => {
            button.addEventListener('click', () => request(button.dataset.aiAction || 'draft'));
        });

        insertButton?.addEventListener('click', () => {
            insertIntoEditor(currentDraft);
            if (status) status.textContent = 'Draft inserted — remember to save';
        });

        copyButton?.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(currentDraft);
                if (status) status.textContent = 'Draft copied';
            } catch (_) {
                if (status) status.textContent = 'Copy failed — select the text manually';
            }
        });

        retryButton?.addEventListener('click', () => {
            hideResult();
            request(lastAction);
        });

        hideResult();
    });
}

// Expose a tiny helper so Blade could trigger the panel if needed.
window.AcadFlowLecturerAi = window.AcadFlowLecturerAi || { initialized: false };
