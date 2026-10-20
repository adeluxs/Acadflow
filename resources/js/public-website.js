const menu = document.querySelector('#mobile-navigation');
const opener = document.querySelector('[data-menu-open]');
if (menu && opener) {
    const close = () => menu.close();
    opener.addEventListener('click', () => {
        menu.showModal();
        opener.setAttribute('aria-expanded', 'true');
        document.body.classList.add('menu-is-open');
    });
    menu.querySelector('[data-menu-close]').addEventListener('click', close);
    menu.addEventListener('click', (event) => { if (event.target.closest('a')) close(); });
    menu.addEventListener('click', (event) => {
        const rect = menu.getBoundingClientRect();
        if (event.target === menu && (event.clientX < rect.left || event.clientX > rect.right)) close();
    });
    menu.addEventListener('close', () => {
        opener.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('menu-is-open');
        opener.focus({ preventScroll: true });
    });
    matchMedia('(min-width: 961px)').addEventListener('change', ({ matches }) => { if (matches && menu.open) close(); });
}

document.querySelectorAll('[data-searchable]').forEach((section) => {
    const input = section.querySelector('[data-public-search]');
    const filters = [...section.querySelectorAll('[data-filter]')];
    const items = [...section.querySelectorAll('[data-search-item]')];
    const count = section.querySelector('[data-search-count]');
    const empty = section.querySelector('[data-search-empty]');
    let category = 'all';
    const refresh = () => {
        const words = input.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
        let visible = 0;
        items.forEach((item) => {
            const matches = (category === 'all' || item.dataset.category === category) && words.every((word) => item.textContent.toLocaleLowerCase().includes(word));
            item.hidden = !matches;
            if (matches) { visible++; item.classList.remove('is-pending'); }
            if (item.tagName === 'DETAILS' && words.length) item.open = matches;
        });
        count.textContent = `${visible} of ${items.length} ${items[0]?.tagName === 'DETAILS' ? 'questions' : 'capabilities'}`;
        empty.hidden = visible !== 0;
    };
    input.addEventListener('input', refresh);
    filters.forEach((button) => button.addEventListener('click', () => {
        category = button.dataset.filter;
        filters.forEach((filter) => {
            const selected = filter === button;
            filter.classList.toggle('active', selected);
            filter.setAttribute('aria-pressed', String(selected));
        });
        refresh();
    }));
    refresh();
});

document.querySelectorAll('[data-showcase]').forEach((showcase) => {
    const tabs = [...showcase.querySelectorAll('[data-preview-tab]')];
    const activate = (tab, focus = false) => {
        tabs.forEach((item) => {
            const active = item === tab;
            item.setAttribute('aria-selected', String(active));
            item.tabIndex = active ? 0 : -1;
            showcase.querySelector(`[data-preview-panel="${item.dataset.previewTab}"]`).hidden = !active;
        });
        if (focus) tab.focus();
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', (event) => {
            const key = event.key;
            let next;
            if (key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            if (key === 'Home') next = 0;
            if (key === 'End') next = tabs.length - 1;
            if (next !== undefined) { event.preventDefault(); activate(tabs[next], true); }
        });
    });
});

if ('IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('js-reveal');
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.remove('is-pending'); observer.unobserve(entry.target); }
    }), { rootMargin: '0px 0px 20px 0px', threshold: 0.03 });
    document.querySelectorAll('[data-reveal]').forEach((item) => { item.classList.add('is-pending'); observer.observe(item); });
}

const contactForm = document.querySelector('[data-contact-form]');
if (contactForm) {
    contactForm.addEventListener('submit', () => {
        const button = contactForm.querySelector('button[type="submit"]');
        button.disabled = true;
        contactForm.setAttribute('aria-busy', 'true');
        contactForm.querySelector('[data-submit-label]').textContent = 'Sending your message…';
        contactForm.querySelector('[data-submit-status]').textContent = 'Please wait while your enquiry is saved.';
    });
    window.addEventListener('pageshow', () => {
        contactForm.querySelector('button[type="submit"]').disabled = false;
        contactForm.removeAttribute('aria-busy');
        contactForm.querySelector('[data-submit-label]').textContent = 'Send message';
        contactForm.querySelector('[data-submit-status]').textContent = '';
    });
}
document.querySelector('[data-form-errors], [data-contact-success]')?.focus({ preventScroll: false });
