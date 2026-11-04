// Portfolio-Assistent (ask_silas): Chatfenster ohne Framework.
// Der Gesprächsverlauf liegt nur im Browser (sessionStorage) und geht bei jeder Frage mit an den Server,
// damit das Modell den Zusammenhang kennt. Auf dem Server wird nichts gespeichert.
(() => {
    const root = document.querySelector('[data-chat]');
    if (!root) return;

    const panel = root.querySelector('[data-chat-panel]');
    const log = root.querySelector('[data-chat-log]');
    const form = root.querySelector('[data-chat-form]');
    const input = root.querySelector('[data-chat-input]');
    const send = root.querySelector('[data-chat-send]');
    const openButton = root.querySelector('[data-chat-open]');
    const STORAGE_KEY = 'askSilas';

    let history = [];
    let busy = false;

    // ---------- sessionStorage (kann in privaten Fenstern fehlen oder werfen) ----------
    const load = () => {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY)) || {};
        } catch {
            return {};
        }
    };
    const save = (open) => {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ history: history.slice(-20), open }));
        } catch {
            /* egal, dann eben ohne Gedächtnis */
        }
    };

    // ---------- Öffnen / Schließen ----------
    const setOpen = (open, focus = true) => {
        panel.hidden = !open;
        root.classList.toggle('is-open', open);
        openButton.setAttribute('aria-expanded', String(open));
        save(open);
        if (open && focus) input.focus();
        if (!open && focus) openButton.focus();
    };

    openButton.addEventListener('click', () => setOpen(true));
    // Andere Elemente auf der Seite (z. B. „Jetzt ausprobieren“ auf der Chatbot-Projektseite) öffnen den Chat ebenfalls
    document.querySelectorAll('[data-chat-launch]').forEach((button) => {
        button.addEventListener('click', () => setOpen(true));
    });
    root.querySelector('[data-chat-close]').addEventListener('click', () => setOpen(false));
    panel.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') setOpen(false);
    });

    // ---------- Antworttext sicher darstellen ----------
    // Das Modell antwortet in einfachem Markdown. Wir bauen DOM-Knoten statt innerHTML,
    // damit niemals HTML aus der Antwort ausgeführt wird. Erlaubt: Absätze, Listen, **fett**, [Link](url).
    const safeUrl = (url) => {
        if (url.startsWith('/') && !url.startsWith('//')) return url;
        try {
            const parsed = new URL(url);
            return ['https:', 'mailto:'].includes(parsed.protocol) ? parsed.href : null;
        } catch {
            return null;
        }
    };

    const renderInline = (parent, text) => {
        const pattern = /\*\*([^*]+)\*\*|\[([^\]]+)\]\(([^)\s]+)\)/g;
        let last = 0;
        for (const match of text.matchAll(pattern)) {
            parent.append(text.slice(last, match.index));
            if (match[1]) {
                const strong = document.createElement('strong');
                strong.textContent = match[1];
                parent.append(strong);
            } else {
                const url = safeUrl(match[3]);
                if (url) {
                    const a = document.createElement('a');
                    a.href = url;
                    a.textContent = match[2];
                    if (!url.startsWith('/')) {
                        a.target = '_blank';
                        a.rel = 'noopener';
                    }
                    parent.append(a);
                } else {
                    parent.append(match[2]);
                }
            }
            last = match.index + match[0].length;
        }
        parent.append(text.slice(last));
    };

    const renderMarkdown = (container, text) => {
        for (const block of text.trim().split(/\n{2,}/)) {
            const lines = block.split('\n');
            if (lines.every((line) => /^\s*[-*•]\s+/.test(line))) {
                const ul = document.createElement('ul');
                lines.forEach((line) => {
                    const li = document.createElement('li');
                    renderInline(li, line.replace(/^\s*[-*•]\s+/, ''));
                    ul.append(li);
                });
                container.append(ul);
            } else {
                const p = document.createElement('p');
                lines.forEach((line, i) => {
                    if (i > 0) p.append(document.createElement('br'));
                    renderInline(p, line.replace(/^#+\s*/, ''));
                });
                container.append(p);
            }
        }
    };

    const addMessage = (role, text) => {
        const div = document.createElement('div');
        div.className = `chat__msg chat__msg--${role}`;
        if (role === 'assistant') {
            renderMarkdown(div, text);
        } else {
            div.textContent = text;
        }
        log.append(div);
        log.scrollTop = log.scrollHeight;
        return div;
    };

    // ---------- Fragen stellen ----------
    const ask = async (question) => {
        question = question.trim();
        if (!question || busy) return;

        busy = true;
        send.disabled = true;
        addMessage('user', question);
        const typing = addMessage('assistant', '');
        typing.classList.add('chat__typing');
        typing.textContent = 'denkt nach';

        try {
            const response = await fetch(root.dataset.chatEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': root.dataset.chatToken,
                },
                body: JSON.stringify({ question, history }),
            });

            // Fehler (z. B. zu viele Fragen) kommen als JSON mit Statuscode, bevor gestreamt wird
            if (!response.ok || !response.body) {
                const data = await response.json().catch(() => ({}));
                typing.remove();
                addMessage('error', data.error || 'Da ist etwas schiefgelaufen. Bitte versuch es nochmal.');
                return;
            }

            // Antwort Stück für Stück lesen und sofort anzeigen
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let answer = '';
            const message = typing;
            message.classList.remove('chat__typing');

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                answer += decoder.decode(value, { stream: true });
                message.replaceChildren();
                renderMarkdown(message, answer);
                log.scrollTop = log.scrollHeight;
            }
            answer += decoder.decode();
            message.replaceChildren();
            renderMarkdown(message, answer);

            history.push({ role: 'user', content: question }, { role: 'assistant', content: answer });
            save(true);
        } catch {
            typing.remove();
            addMessage('error', 'Keine Verbindung. Bitte prüf deine Internetverbindung.');
        } finally {
            busy = false;
            send.disabled = false;
            input.focus();
        }
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const question = input.value;
        input.value = '';
        input.style.height = '';
        ask(question);
    });

    // Enter sendet, Shift+Enter macht eine neue Zeile
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    // Eingabefeld wächst mit
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
    });

    root.querySelectorAll('[data-chat-suggestion]').forEach((button) => {
        button.addEventListener('click', () => ask(button.textContent));
    });

    // ---------- Fenstergröße per Griff oben links ändern ----------
    // Die Größe merkt sich nur dieser Browser (localStorage), Doppelklick auf den Griff setzt sie zurück.
    const SIZE_KEY = 'askSilasSize';
    const handle = root.querySelector('[data-chat-resize]');
    const clampSize = (w, h) => [
        Math.round(Math.min(Math.max(w, 320), window.innerWidth - 48)),
        Math.round(Math.min(Math.max(h, 380), window.innerHeight - 48)),
    ];
    const applySize = (w, h) => {
        [w, h] = clampSize(w, h);
        panel.style.width = `${w}px`;
        panel.style.height = `${h}px`;
    };
    try {
        const size = JSON.parse(localStorage.getItem(SIZE_KEY));
        if (size && size.w && size.h) applySize(size.w, size.h);
    } catch { /* dann eben Standardgröße */ }

    handle.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        handle.setPointerCapture(e.pointerId);
        const start = { x: e.clientX, y: e.clientY, w: panel.offsetWidth, h: panel.offsetHeight };
        root.classList.add('is-resizing');
        const move = (ev) => applySize(start.w + start.x - ev.clientX, start.h + start.y - ev.clientY);
        const end = () => {
            root.classList.remove('is-resizing');
            handle.removeEventListener('pointermove', move);
            try {
                localStorage.setItem(SIZE_KEY, JSON.stringify({ w: panel.offsetWidth, h: panel.offsetHeight }));
            } catch { /* egal */ }
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('lostpointercapture', end, { once: true });
    });
    handle.addEventListener('dblclick', () => {
        panel.style.width = '';
        panel.style.height = '';
        try { localStorage.removeItem(SIZE_KEY); } catch { /* egal */ }
    });

    // ---------- Hinweis auf „Über mich“ nach der Hälfte der Seite ----------
    // Einmal pro Sitzung, nicht wenn der Chat schon offen ist oder schon genutzt wurde.
    const nudgeBox = root.querySelector('[data-chat-nudge-box]');
    const NUDGE_KEY = 'askSilasNudged';
    const alreadyNudged = () => {
        try { return sessionStorage.getItem(NUDGE_KEY) === '1'; } catch { return false; }
    };
    if (root.hasAttribute('data-chat-nudge') && !alreadyNudged()) {
        const hideNudge = () => {
            nudgeBox.hidden = true;
            root.classList.remove('is-nudging');
            window.removeEventListener('scroll', onScroll);
        };
        const onScroll = () => {
            const max = document.documentElement.scrollHeight - window.innerHeight;
            if (max <= 0 || window.scrollY / max < 0.5) return;
            window.removeEventListener('scroll', onScroll);
            if (root.classList.contains('is-open') || history.length > 0) return;
            try { sessionStorage.setItem(NUDGE_KEY, '1'); } catch { /* egal */ }
            nudgeBox.hidden = false;
            root.classList.add('is-nudging');
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        root.querySelector('[data-chat-nudge-open]').addEventListener('click', () => { hideNudge(); setOpen(true); });
        root.querySelector('[data-chat-nudge-close]').addEventListener('click', hideNudge);
        openButton.addEventListener('click', hideNudge);
    }

    // ---------- Verlauf beim Seitenwechsel wiederherstellen ----------
    const stored = load();
    if (Array.isArray(stored.history)) {
        history = stored.history.filter((m) => m && typeof m.content === 'string');
        history.forEach((m) => addMessage(m.role === 'assistant' ? 'assistant' : 'user', m.content));
    }
    if (stored.open) setOpen(true, false);
})();
