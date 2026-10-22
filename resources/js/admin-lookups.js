export function initAdminLookups() {
    document.querySelectorAll('[data-admin-lookup]').forEach((root) => {
        const search = root.querySelector('[data-lookup-search]');
        const select = root.querySelector('[data-lookup-select]');
        const status = root.querySelector('[data-lookup-status]');
        const more = root.querySelector('[data-lookup-more]');
        const dependency = root.dataset.depends ? root.closest('form')?.elements.namedItem(root.dataset.depends) : null;
        let controller;
        let timer;
        let nextPage = 1;
        let loaded = false;
        let generation = 0;

        const reset = (clearSelection = false) => {
            generation++;
            controller?.abort();
            clearTimeout(timer);
            if (clearSelection) select.value = '';
            Array.from(select.options).forEach((option) => {
                if (option.value && (!option.selected || clearSelection)) option.remove();
            });
            loaded = false;
            nextPage = 1;
            more.hidden = true;
        };

        const load = async () => {
            if (dependency && !dependency.value) {
                status.textContent = 'Choose the field above first.';
                return;
            }
            controller?.abort();
            controller = new AbortController();
            const currentGeneration = ++generation;
            const url = new URL(root.dataset.url, location.origin);
            url.searchParams.set('q', search.value.trim());
            url.searchParams.set('page', String(nextPage || 1));
            if (dependency) url.searchParams.set(root.dataset.depends, dependency.value);
            status.textContent = 'Loading…';
            more.hidden = true;
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Lookup failed');
                const payload = await response.json();
                if (generation !== currentGeneration) return;
                const existing = new Set(Array.from(select.options, (option) => option.value));
                payload.data.forEach(({ id, label }) => {
                    if (!existing.has(String(id))) select.add(new Option(label, String(id)));
                });
                nextPage = payload.next_page;
                loaded = true;
                more.textContent = 'More results';
                more.hidden = !nextPage;
                status.textContent = payload.data.length ? 'Choose an option; search to narrow results.' : 'No matching options.';
            } catch (error) {
                if (error.name === 'AbortError' || generation !== currentGeneration) return;
                status.textContent = 'Could not load options.';
                more.textContent = 'Retry';
                more.hidden = false;
            } finally {
                if (generation === currentGeneration) controller = null;
            }
        };

        search.addEventListener('input', () => {
            reset();
            timer = setTimeout(load, 250);
        });
        [search, select].forEach((element) => element.addEventListener('focus', () => {
            if (!loaded && !controller) load();
        }));
        // A failed/cancelled request can always be retried through the button.
        more.addEventListener('click', load);
        dependency?.addEventListener('change', () => {
            reset(true);
            search.value = '';
            load();
        });
    });
}
