function initSetup(form) {
    initInstitutionOptions(form);
    const steps = [...form.querySelectorAll('[data-setup-step]')];
    const progress = form.querySelector('[data-setup-progress]');
    const controls = form.querySelector('[data-setup-controls]');
    const previous = form.querySelector('[data-setup-back]');
    const next = form.querySelector('[data-setup-next]');
    const submit = form.querySelector('[data-setup-submit]');
    let active = 0;
    function render() {
        steps.forEach((step, i) => { step.hidden = i !== active; });
        progress.replaceChildren(...steps.map((step, i) => {
            const item = document.createElement('span');
            item.textContent = `${String(i + 1).padStart(2, '0')}  ${step.dataset.setupStep}`;
            if (i === active) item.setAttribute('aria-current', 'step');
            return item;
        }));
        controls.hidden = false; previous.disabled = active === 0; next.hidden = active === steps.length - 1;
        submit.hidden = active !== steps.length - 1;
        form.querySelector('[data-setup-position]').textContent = `Step ${active + 1} of ${steps.length}`;
    }
    const valid = () => [...steps[active].querySelectorAll('input, select, textarea')].every((input) => input.disabled || input.reportValidity());
    next.addEventListener('click', () => { if (valid()) { active = Math.min(steps.length - 1, active + 1); render(); steps[active].scrollIntoView({ block: 'start' }); steps[active].querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true }); } });
    previous.addEventListener('click', () => { active = Math.max(0, active - 1); render(); steps[active].querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true }); });
    // Reveal a hidden invalid field before the browser attempts to focus it.
    form.addEventListener('invalid', (event) => { const index = steps.findIndex((step) => step.contains(event.target)); if (index >= 0 && index !== active) { active = index; render(); } }, true);
    form.addEventListener('submit', (event) => {
        if (active < steps.length - 1) { event.preventDefault(); next.click(); return; }
        if (form.checkValidity()) { submit.disabled = true; submit.textContent = 'Creating workspace…'; }
    });
    window.addEventListener('pageshow', () => { submit.disabled = false; submit.textContent = 'Create research workspace'; });
    const catalogue = JSON.parse(form.querySelector('[data-methodology-catalogue]').textContent);
    const select = form.querySelector('[data-methodology-select]');
    const preview = form.querySelector('[data-methodology-preview]');
    const updatePreview = () => {
        const method = catalogue[select.value]; preview.replaceChildren();
        const heading = document.createElement('strong'); heading.textContent = method?.label || 'Institutional template';
        const text = document.createElement('p'); text.textContent = method ? `${method.decision} Suggested instruments: ${method.instruments.join('; ')}.` : 'Your administrator’s template and workflow will be used.';
        preview.append(heading, text);
    };
    select.addEventListener('change', updatePreview); updatePreview(); render();
}

function initInstitutionOptions(form) {
    const fields = form.querySelector('[data-institution-options]');
    const toggle = form.querySelector('#institutional-project-toggle');
    if (!fields || !toggle) return;
    for (const kind of ['departments', 'supervisors']) {
        const select = fields.querySelector(kind === 'departments' ? '#institutional-department' : '#supervisor-invitee');
        const search = fields.querySelector(`[data-option-search="${kind}"]`);
        const more = fields.querySelector(`[data-option-more="${kind}"]`);
        const status = fields.querySelector(`[data-option-status="${kind}"]`);
        let initialized = false, nextPage = null, controller, timer, generation = 0;
        async function load(page = 1) {
            const current = ++generation;
            controller?.abort();
            controller = new AbortController();
            const signal = controller.signal;
            const timeout = setTimeout(() => controller.abort(), 10000);
            const url = new URL(fields.dataset.institutionOptions, location.origin);
            url.search = new URLSearchParams({ kind, q: search.value.trim(), page });
            status.textContent = 'Loading options…'; more.disabled = true;
            try {
                const response = await fetch(url, { signal, credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok || response.redirected) throw new Error('Options unavailable');
                const result = await response.json();
                if (current !== generation) return;
                // Keep the user's selection, including after a failed submit.
                if (page === 1) [...select.options].forEach(option => { if (option.value && !option.selected) option.remove(); });
                const existing = new Set([...select.options].map(option => option.value));
                for (const option of result.options) if (!existing.has(String(option.id))) select.add(new Option(option.label, option.id));
                nextPage = result.next_page; initialized = true;
                more.hidden = !nextPage;
                more.textContent = 'Load more options';
                status.textContent = result.options.length ? 'Options ready. Search to narrow the list.' : 'No matching options.';
            } catch {
                if (current !== generation) return;
                status.textContent = 'Could not load options. Your form is preserved.';
                nextPage = page; more.hidden = false; more.textContent = 'Retry loading options';
            } finally {
                clearTimeout(timeout);
                if (current === generation) more.disabled = false;
            }
        }
        toggle.addEventListener('change', () => { if (toggle.checked && !initialized) void load(); });
        search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => void load(), 250); });
        more.addEventListener('click', () => void load(nextPage || 1));
        if (toggle.checked) void load();
    }
}

function initAutosave(form) {
    const source = form.querySelector('textarea[name="body"]');
    const expected = form.querySelector('[name="expected_version"]');
    const status = form.querySelector('[data-autosave-status]');
    if (!source || !expected || !status) return;
    let saved = source.value, timer, saving = false, stopped = false;
    const dirty = () => source.value !== saved;
    const setStatus = (message, state) => { status.textContent = message; status.dataset.state = state; };
    async function save() {
        clearTimeout(timer);
        if (saving || stopped || !dirty()) return;
        if (!navigator.onLine) { setStatus('Offline. Keep this tab open; your unsaved draft is held here.', 'error'); return; }
        saving = true;
        const body = source.value;
        const data = new FormData(form); data.set('body', body);
        setStatus('Saving changes…', 'saving');
        const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-AcadFlow-Loader': 'silent' }, signal: controller.signal });
            if ([409, 423, 401, 403, 419].includes(response.status) || response.redirected) {
                stopped = true;
                setStatus(response.status === 409 ? 'Another session saved a newer version. Copy your draft before reloading to compare.' : 'Saving paused. Copy your draft, then check your session or section permissions.', 'error');
                return;
            }
            if (!response.ok) { setStatus('Save failed. Your draft remains in this tab. Try Save version again.', 'error'); return; }
            const result = await response.json();
            if (!result.saved || !result.version) throw new Error('Invalid save response');
            expected.value = String(result.version); saved = body;
            setStatus(`Saved · Version ${result.version} · ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`, 'saved');
        } catch { setStatus('Could not confirm the save. Keep this tab open and retry; a conflict will protect newer work.', 'error'); }
        finally { clearTimeout(timeout); saving = false; if (dirty() && !stopped && status.dataset.state === 'saved') timer = setTimeout(save, 1600); }
    }
    source.addEventListener('input', () => { clearTimeout(timer); if (stopped) return; setStatus('Unsaved changes', 'saving'); timer = setTimeout(save, 1600); });
    form.addEventListener('submit', (event) => { event.preventDefault(); save(); });
    window.addEventListener('online', save);
    window.addEventListener('beforeunload', (event) => { if (dirty()) { event.preventDefault(); event.returnValue = ''; } });
}

document.querySelectorAll('[data-research-setup]').forEach(initSetup);
document.querySelectorAll('[data-research-autosave]').forEach(initAutosave);

document.querySelectorAll('[data-writing-workspace]').forEach((workspace) => {
    const focusButton = workspace.querySelector('[data-writing-focus-toggle]');
    if (!focusButton) return;
    focusButton.hidden = false;
    const setFocus = (focused) => {
        workspace.dataset.writingFocus = String(focused);
        focusButton.setAttribute('aria-pressed', String(focused));
        focusButton.textContent = focused ? 'Exit focus' : 'Focus writing';
    };
    focusButton.addEventListener('click', () => {
        setFocus(workspace.dataset.writingFocus !== 'true');
        // Keep the same editor mounted so draft text, selection and autosave survive.
        workspace.querySelector('.studio-writing-actions')?.scrollIntoView({ block: 'start' });
    });
    workspace.addEventListener('click', (event) => {
        if (!event.target.closest('[data-writing-open-tools]')) return;
        event.preventDefault();
        setFocus(false);
        const tools = workspace.querySelector('.studio-writing-tools');
        tools.open = true;
        tools.querySelector('summary').focus({ preventScroll: true });
        tools.scrollIntoView({ block: 'start' });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && workspace.dataset.writingFocus === 'true') {
            setFocus(false);
            focusButton.focus({ preventScroll: true });
        }
    });
});
