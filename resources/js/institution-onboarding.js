export function initInstitutionOnboardingSearch() {
    const root = document.querySelector('[data-institution-search]');
    const form = document.querySelector('[data-institution-claim-form]');
    if (!root || !form) return;

    const input = root.querySelector('[data-institution-search-input]');
    const button = root.querySelector('[data-institution-search-button]');
    const status = root.querySelector('[data-institution-search-status]');
    const results = root.querySelector('[data-institution-search-results]');
    const endpoint = root.dataset.searchUrl;
    const accessUrl = root.dataset.accessUrl;

    const modeInput = form.querySelector('[name="request_type"]');
    const universityInput = form.querySelector('[name="university_id"]');
    const selectedWrap = form.querySelector('[data-selected-institution]');
    const selectedName = form.querySelector('[data-selected-institution-name]');
    const createFields = form.querySelector('[data-create-institution-fields]');
    const resetButton = form.querySelector('[data-reset-institution-selection]');
    const heading = form.querySelector('[data-institution-form-heading]');
    const institutionNameInput = form.querySelector('[name="institution_name"]');

    let controller = null;
    let debounceTimer = null;
    let sequence = 0;

    const setStatus = (message, kind = 'muted') => {
        if (!status) return;
        status.textContent = message;
        status.className = 'mt-3 text-sm ' + ({
            error: 'text-rose-700',
            success: 'text-emerald-700',
            loading: 'text-indigo-700',
            muted: 'text-slate-500',
        }[kind] || 'text-slate-500');
    };

    const selectInstitution = (item) => {
        if (item.adoption_status === 'active') {
            window.location.assign(accessUrl + '?institution=' + encodeURIComponent(item.id));
            return;
        }

        modeInput.value = 'claim_existing';
        universityInput.value = String(item.id);
        if (selectedName) selectedName.textContent = item.name;
        if (selectedWrap) selectedWrap.classList.remove('hidden');
        if (createFields) createFields.classList.add('hidden');
        if (resetButton) resetButton.classList.remove('hidden');
        if (heading) heading.textContent = 'Claim existing institution';
        if (institutionNameInput) institutionNameInput.required = false;
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const renderResults = (items) => {
        results.replaceChildren();
        if (!items.length) {
            setStatus('No catalogue match found. You can request a new institution below.', 'muted');
            return;
        }

        items.forEach((item) => {
            const card = document.createElement('div');
            card.className = 'rounded-2xl border p-4 sm:flex sm:items-center sm:justify-between';

            const info = document.createElement('div');
            const name = document.createElement('p');
            name.className = 'font-bold text-slate-900';
            name.textContent = item.name;
            const meta = document.createElement('p');
            meta.className = 'mt-1 text-xs text-slate-500';
            const adoption = String(item.adoption_status || 'unclaimed').replaceAll('_', ' ');
            meta.textContent = `${item.type || 'Institution'} · ${item.state || 'Location not listed'} · ${adoption}`;
            info.append(name, meta);

            const action = document.createElement('button');
            action.type = 'button';
            action.className = 'mt-3 acad-primary-button sm:mt-0';
            action.textContent = item.adoption_status === 'active' ? 'Request access instead' : 'Claim this institution';
            action.addEventListener('click', () => selectInstitution(item));

            card.append(info, action);
            results.append(card);
        });
        setStatus(`${items.length} matching institution${items.length === 1 ? '' : 's'} found.`, 'success');
    };

    const runSearch = async () => {
        const q = (input?.value || '').trim();
        const currentSequence = ++sequence;

        if (q.length < 2) {
            if (controller) controller.abort();
            results.replaceChildren();
            setStatus('Type at least 2 characters to search the institution catalogue.', 'muted');
            return;
        }

        if (controller) controller.abort();
        controller = new AbortController();
        button.disabled = true;
        setStatus('Searching institutions…', 'loading');

        try {
            const response = await fetch(`${endpoint}?q=${encodeURIComponent(q)}`, {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) {
                throw new Error(payload.message || 'Institution search could not be completed.');
            }
            if (currentSequence !== sequence) return;
            renderResults(Array.isArray(payload.data) ? payload.data : []);
        } catch (error) {
            if (error?.name === 'AbortError') return;
            if (currentSequence !== sequence) return;
            results.replaceChildren();
            setStatus('We could not search institutions right now. Check your connection and try again.', 'error');
        } finally {
            if (currentSequence === sequence) button.disabled = false;
        }
    };

    input?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runSearch, 350);
    });
    button?.addEventListener('click', runSearch);
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            clearTimeout(debounceTimer);
            runSearch();
        }
    });

    resetButton?.addEventListener('click', () => {
        modeInput.value = 'create_new';
        universityInput.value = '';
        if (selectedName) selectedName.textContent = '';
        if (selectedWrap) selectedWrap.classList.add('hidden');
        if (createFields) createFields.classList.remove('hidden');
        resetButton.classList.add('hidden');
        if (heading) heading.textContent = 'Request new institution';
        if (institutionNameInput) institutionNameInput.required = true;
    });

    // Restore server-side old input after validation errors.
    if (modeInput.value === 'claim_existing' && universityInput.value) {
        if (selectedWrap) selectedWrap.classList.remove('hidden');
        if (createFields) createFields.classList.add('hidden');
        if (resetButton) resetButton.classList.remove('hidden');
        if (heading) heading.textContent = 'Claim existing institution';
        if (institutionNameInput) institutionNameInput.required = false;
    }
}

