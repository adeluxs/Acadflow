const QUILL_VERSION = '2.0.3';
const QUILL_JS = `https://cdn.jsdelivr.net/npm/quill@${QUILL_VERSION}/dist/quill.js`;
const QUILL_CSS = `https://cdn.jsdelivr.net/npm/quill@${QUILL_VERSION}/dist/quill.snow.css`;

let quillPromise;

function ensureQuill() {
    if (window.Quill) return Promise.resolve(window.Quill);
    if (quillPromise) return quillPromise;

    quillPromise = new Promise((resolve, reject) => {
        if (!document.querySelector(`link[href="${QUILL_CSS}"]`)) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = QUILL_CSS;
            document.head.appendChild(css);
        }
        const script = document.createElement('script');
        script.src = QUILL_JS;
        script.async = true;
        script.onload = () => resolve(window.Quill);
        script.onerror = reject;
        document.head.appendChild(script);
    });
    return quillPromise;
}

function plainText(html) {
    const div = document.createElement('div');
    div.innerHTML = html || '';
    return (div.textContent || '').replace(/\s+/g, ' ').trim();
}

function debounce(fn, wait) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
}

function suggestionFrom(payload) {
    if (!payload || payload.enabled === false || payload.success === false) return null;
    const data = payload.data || {};
    const direct = data.completion || data.suggestion || data.continuation || data.rewrite;
    if (typeof direct === 'string' && direct.trim()) return { text: direct.trim(), insertable: true };
    if (Array.isArray(payload.suggested_actions) && payload.suggested_actions[0]) {
        return { text: String(payload.suggested_actions[0]), insertable: false };
    }
    if (typeof payload.summary === 'string' && payload.summary.trim()) {
        return { text: payload.summary.trim(), insertable: false };
    }
    return null;
}

async function requestSuggestion(source, editorApi, box) {
    const endpoint = source.dataset.aiUrl;
    if (!endpoint || source.dataset.aiEnabled === '0') return;

    const text = plainText(editorApi.getHtml());
    const minChars = Number(source.dataset.aiMinChars || 60);
    if (text.length < minChars) {
        box.dataset.visible = 'false';
        return;
    }

    const status = box.querySelector('[data-ai-status]');
    const content = box.querySelector('[data-ai-content]');
    const insertButton = box.querySelector('[data-ai-insert]');
    status.textContent = 'AI is reviewing your latest text…';
    box.dataset.visible = 'true';
    insertButton.classList.add('hidden');

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ text: text.slice(-12000), type: 'autocomplete' }),
        });
        if (!response.ok) throw new Error(`Suggestion request failed (${response.status})`);
        const payload = await response.json();
        const suggestion = suggestionFrom(payload);
        if (!suggestion) {
            box.dataset.visible = 'false';
            return;
        }

        status.textContent = 'AI writing suggestion';
        content.textContent = suggestion.text;
        if (suggestion.insertable) {
            insertButton.classList.remove('hidden');
            insertButton.onclick = () => editorApi.insertText(suggestion.text);
        }
    } catch (error) {
        status.textContent = 'AI suggestions are temporarily unavailable.';
        content.textContent = '';
        setTimeout(() => { box.dataset.visible = 'false'; }, 2500);
    }
}

function buildSuggestionBox(source, editorApi) {
    const box = document.createElement('div');
    box.className = 'ai-writing-suggestion';
    box.dataset.visible = 'false';
    box.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white">AI</div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold text-slate-900" data-ai-status>AI writing suggestion</p>
                    <button type="button" class="text-xs font-medium text-slate-500 hover:text-slate-800" data-ai-close>Dismiss</button>
                </div>
                <p class="mt-1 leading-6" data-ai-content></p>
                <button type="button" class="acad-primary-button mt-2 hidden rounded-lg px-3 py-1.5 text-xs font-semibold text-white" data-ai-insert>Insert suggestion</button>
            </div>
        </div>`;
    const aiBadge = box.querySelector('.h-8.w-8');
    if (aiBadge) aiBadge.style.backgroundColor = 'var(--acad-primary)';
    box.querySelector('[data-ai-close]').addEventListener('click', () => { box.dataset.visible = 'false'; });
    const editorShell = source.nextElementSibling;
    if (editorShell?.classList.contains('rich-editor-shell')) {
        editorShell.insertAdjacentElement('afterend', box);
    } else {
        source.insertAdjacentElement('afterend', box);
    }

    const delay = Math.max(700, Number(source.dataset.aiDelay || 1600));
    return debounce(() => requestSuggestion(source, editorApi, box), delay);
}

function initFallback(source) {
    const shell = document.createElement('div');
    shell.className = 'rich-editor-shell';
    const toolbar = document.createElement('div');
    toolbar.className = 'rich-editor-toolbar';
    toolbar.innerHTML = `
        <select data-command="formatBlock" aria-label="Paragraph style"><option value="p">Paragraph</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option></select>
        <button type="button" data-command="bold"><strong>B</strong></button>
        <button type="button" data-command="italic"><em>I</em></button>
        <button type="button" data-command="underline"><u>U</u></button>
        <button type="button" data-command="insertUnorderedList">• List</button>
        <button type="button" data-command="insertOrderedList">1. List</button>
        <button type="button" data-command="justifyLeft">Left</button>
        <button type="button" data-command="justifyCenter">Center</button>
        <button type="button" data-command="createLink">Link</button>`;
    const surface = document.createElement('div');
    surface.className = 'rich-editor-surface';
    surface.contentEditable = 'true';
    surface.dataset.placeholder = source.placeholder || 'Start writing…';
    surface.innerHTML = source.value || '';
    const status = document.createElement('div');
    status.className = 'rich-editor-status';
    status.innerHTML = '<span>Rich text editor</span><span data-word-count>0 words</span>';
    shell.append(toolbar, surface, status);
    source.classList.add('hidden');
    source.insertAdjacentElement('afterend', shell);

    toolbar.addEventListener('click', (event) => {
        const button = event.target.closest('[data-command]');
        if (!button) return;
        let value = null;
        if (button.dataset.command === 'createLink') value = window.prompt('Link URL');
        document.execCommand(button.dataset.command, false, value);
        surface.focus();
    });
    toolbar.addEventListener('change', (event) => {
        const select = event.target.closest('[data-command]');
        if (select) document.execCommand(select.dataset.command, false, select.value);
        surface.focus();
    });

    const api = {
        getHtml: () => surface.innerHTML,
        insertText: (text) => { surface.focus(); document.execCommand('insertText', false, text); },
    };
    const suggest = buildSuggestionBox(source, api);
    const sync = () => {
        source.value = surface.innerHTML;
        status.querySelector('[data-word-count]').textContent = `${plainText(surface.innerHTML).split(/\s+/).filter(Boolean).length} words`;
        suggest();
    };
    surface.addEventListener('input', sync);
    source.form?.addEventListener('submit', sync);
    sync();
}

function initQuill(source, Quill) {
    const container = document.createElement('div');
    container.className = 'rich-editor-shell';
    const editor = document.createElement('div');
    container.appendChild(editor);
    source.classList.add('hidden');
    source.insertAdjacentElement('afterend', container);

    const quill = new Quill(editor, {
        theme: 'snow',
        placeholder: source.placeholder || 'Start writing…',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                [{ font: [] }, { size: ['small', false, 'large', 'huge'] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['blockquote', 'code-block', 'link'],
                ['clean'],
            ],
        },
    });
    quill.root.innerHTML = source.value || '';
    quill.root.style.minHeight = source.dataset.editorHeight || '300px';

    const status = document.createElement('div');
    status.className = 'rich-editor-status';
    status.innerHTML = '<span>Word-style rich editor · versions remain recoverable</span><span data-word-count>0 words</span>';
    container.appendChild(status);

    const api = {
        getHtml: () => quill.root.innerHTML,
        insertText: (text) => {
            const range = quill.getSelection(true) || { index: quill.getLength() - 1, length: 0 };
            quill.insertText(range.index, text, 'user');
            quill.setSelection(range.index + text.length, 0, 'silent');
        },
    };
    const suggest = buildSuggestionBox(source, api);
    const sync = () => {
        source.value = quill.root.innerHTML;
        status.querySelector('[data-word-count]').textContent = `${plainText(source.value).split(/\s+/).filter(Boolean).length} words`;
    };
    const syncAndSuggest = debounce(() => { sync(); suggest(); }, 250);
    quill.on('text-change', syncAndSuggest);
    source.form?.addEventListener('submit', sync);
    sync();
}

export function initRichEditors() {
    const sources = [...document.querySelectorAll('textarea[data-rich-editor]')].filter((el) => !el.dataset.editorInitialized);
    if (!sources.length) return;
    sources.forEach((source) => { source.dataset.editorInitialized = '1'; });

    ensureQuill()
        .then((Quill) => sources.forEach((source) => initQuill(source, Quill)))
        .catch(() => sources.forEach(initFallback));
}
