(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  let state = null;
  let offset = 0;
  const openEdits = new Set();   // Spiele, deren Bearbeiten-Formular gerade offen ist

  // ---------- Server ----------
  async function load() {
    try {
      const res = await fetch('/lan-night/api/state?admin=1', { cache: 'no-store' });
      if (res.ok) apply(await res.json());
    } catch (e) { /* nächster Versuch */ }
  }

  async function send(action, data = {}) {
    try {
      const res = await fetch('/lan-night/api/action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data }),
      });
      const body = await res.json().catch(() => ({}));
      if (res.status === 404) { location.reload(); return null; } // Login abgelaufen
      if (!res.ok) { toast(body.error || 'Fehler'); return null; }
      apply(body, true);
      return body;
    } catch (e) {
      toast('Keine Verbindung');
      return null;
    }
  }

  let toastTimer = null;
  function toast(msg, ok = false) {
    const el = $('toast');
    el.textContent = msg;
    el.classList.toggle('is-ok', ok);
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.hidden = true; }, 2500);
  }

  function apply(s, own = false) {
    offset = s.serverTime - Date.now() / 1000;
    const changed = !state || s.updatedAt !== state.updatedAt;
    const first = !state;
    state = s;
    renderStatus();
    if (!changed) return;
    followScreen();
    renderScores();
    renderScreens();
    renderGames();
    renderLog();
    renderWheel();
    renderQuiz();
    renderGuess();
    renderPrice();
    renderAwards();
    renderLive();
    if (first || own) renderTeams();
    if (first) { fillSettings(); fillQuizForm(); fillAwardsForm(); fillGuessForm(); fillPriceForm(); }
  }

  // Liegt der Fokus in diesem Bereich, wird er nicht neu gezeichnet (sonst ist die Eingabe weg)
  const busy = (el) => el.contains(document.activeElement) && document.activeElement !== document.body
    && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName);

  // ---------- Status ----------
  function renderStatus() {
    const seen = state?.displaySeenAt;
    const ok = seen && (Date.now() / 1000 + offset) - seen < 6;
    $('displayStatus').classList.toggle('is-ok', !!ok);
    $('displayStatusText').textContent = ok ? 'Bildschirm verbunden' : 'Bildschirm offline';
  }

  // ---------- Punkte ----------
  function renderScores() {
    const wrap = $('scores');
    if (!wrap.children.length) {
      wrap.innerHTML = [0, 1].map((i) => `
        <div class="score" data-team="${i}">
          <div class="score__head"><span class="score__name"></span><span class="score__value"></span></div>
          <div class="score__btns">
            <button class="lbtn" data-points="-1">−1</button>
            <button class="lbtn" data-points="1">+1</button>
            <button class="lbtn" data-points="3">+3</button>
            <button class="lbtn" data-points="5">+5</button>
          </div>
          <form class="score__custom">
            <input class="linput" name="delta" type="number" placeholder="±" required aria-label="Punkte">
            <input class="linput" name="reason" placeholder="Grund (optional)" maxlength="80">
            <button class="lbtn lbtn--primary" type="submit">OK</button>
          </form>
        </div>`).join('');
    }
    wrap.querySelectorAll('.score').forEach((el) => {
      const i = Number(el.dataset.team), t = state.teams[i];
      el.style.setProperty('--c', t.color);
      el.querySelector('.score__name').textContent = t.name;
      el.querySelector('.score__value').textContent = state.scores[i];
    });
  }

  $('scores').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-points]');
    if (!btn) return;
    const team = Number(btn.closest('.score').dataset.team);
    send('points', { team, delta: Number(btn.dataset.points), reason: '' });
  });

  $('scores').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target, team = Number(form.closest('.score').dataset.team);
    const ok = await send('points', { team, delta: Number(form.delta.value), reason: form.reason.value });
    if (ok) form.reset();
  });

  // ---------- Glücksrad ----------
  try { $('wheelFresh').checked = localStorage.getItem('lan_wheel_fresh') === '1'; } catch (e) { /* egal */ }
  $('wheelFresh').addEventListener('change', (e) => {
    try { localStorage.setItem('lan_wheel_fresh', e.target.checked ? '1' : '0'); } catch (err) { /* egal */ }
  });

  function renderWheel() {
    const w = state.wheel;
    $('wheelLive').textContent = w ? 'wird angezeigt' : '';
    $('wheelHide').disabled = !w;
    $('wheelResult').innerHTML = w
      ? `Gezogen: ${state.teams.map((t, i) => `<b style="color:${esc(t.color)}">${esc(w.members[i][w.picks[i]])}</b>`).join(' · ')}`
        + ' <span>(auf dem Bildschirm nach ein paar Sekunden)</span>'
      : 'Zieht aus jedem Team zufällig einen Spieler – zwei Räder groß auf dem Bildschirm.';
  }

  $('wheelSpin').addEventListener('click', () => send('wheel.spin', { fresh: $('wheelFresh').checked }));
  $('wheelHide').addEventListener('click', () => send('wheel.hide'));

  // ---------- Segment-Reiter ----------
  // Immer nur ein Segment sichtbar. Wechselt der Bildschirm zu einem Segment, springt der Reiter mit;
  // danach darf man trotzdem in andere Reiter schauen (z. B. das nächste Segment vorbereiten).
  const SEGMENTS = ['quiz', 'guess', 'price', 'awards'];
  let tab = (() => { try { return localStorage.getItem('lan_admin_tab'); } catch (e) { return null; } })();
  if (!SEGMENTS.includes(tab)) tab = 'quiz';
  let lastScreen = null;

  function showTab(name) {
    tab = name;
    try { localStorage.setItem('lan_admin_tab', name); } catch (e) { /* egal */ }
    document.querySelectorAll('[data-segment]').forEach((el) => { el.hidden = el.dataset.segment !== name; });
    document.querySelectorAll('#segTabs [data-tab]').forEach((b) => {
      b.classList.toggle('is-on', b.dataset.tab === name);
      b.setAttribute('aria-selected', String(b.dataset.tab === name));
    });
  }

  function followScreen() {
    const screen = state.screen;
    if (screen !== lastScreen && SEGMENTS.includes(screen)) showTab(screen);
    lastScreen = screen;
    document.querySelectorAll('#segTabs [data-tab]').forEach((b) => b.classList.toggle('is-live', b.dataset.tab === screen));
  }

  $('segTabs').addEventListener('click', (e) => {
    const b = e.target.closest('[data-tab]');
    if (b) showTab(b.dataset.tab);
  });
  showTab(tab);

  // ---------- Quiz ----------
  const LETTERS = ['A', 'B', 'C', 'D'];
  const PHASES = { intro: 'Startbild', question: 'Frage läuft', locked: 'beide drin – Auflösung gleich', reveal: 'aufgelöst', done: 'Ergebnis' };

  function renderQuiz() {
    const q = state.quiz, onScreen = state.screen === 'quiz';
    const hasNext = q.index + 1 < q.total;
    const btn = (act, label, cls = '') => `<button class="lbtn ${cls}" data-quiz="${act}">${label}</button>`;
    const btns = [];

    if (!onScreen) btns.push(btn('quiz.open', 'Quiz auf den Bildschirm', 'lbtn--primary'));
    if (q.phase === 'question') {
      btns.push(btn('quiz.lock', 'Zeit beenden'), btn('quiz.reveal', 'Sofort auflösen'));
    } else if (q.phase === 'locked') {
      btns.push(btn('quiz.reveal', 'Sofort auflösen'));
    } else {
      if (hasNext && q.phase !== 'done') btns.push(btn('quiz.next', `▶ Frage ${q.index + 2} starten`, onScreen ? 'lbtn--mint' : ''));
      if (q.index >= 0 && q.phase !== 'done') btns.push(btn('quiz.done', 'Quiz-Ergebnis zeigen', !hasNext ? 'lbtn--primary' : ''));
    }
    if (onScreen) btns.push(btn('quiz.close', 'Zurück zum Punktestand', 'lbtn--ghost'));
    $('quizCtl').innerHTML = btns.join('');

    const cur = q.questions[q.index];
    if (!cur || !['question', 'locked', 'reveal'].includes(q.phase)) {
      $('quizNow').innerHTML = q.index >= 0
        ? `<p class="quiz-now__tally">Stand im Quiz: ${tallyText(q)}</p>` : '';
      return;
    }

    // Die Spielleitung sieht die Team-Antworten sofort (der Bildschirm erst, wenn beide drin sind)
    const answers = cur.answers.map((text, i) => {
      const votes = state.teams.map((t, ti) => q.answers[ti] === i
        ? `<span style="--c:${esc(t.color)}" class="is-locked">${esc(t.name)}</span>` : '').join('');
      return `
        <div class="qa ${i === cur.correct ? 'is-correct' : ''}" style="--a:var(--ans-${i})">
          <span class="qa__letter">${LETTERS[i]}</span>
          <span class="qa__text">${esc(text)}</span>
          ${i === cur.correct ? '<span class="qa__check" title="richtige Antwort">✓</span>' : ''}
          <span class="qa__votes">${votes}</span>
        </div>`;
    }).join('');

    // Von Hand einloggen (z. B. wenn ein Team die Antwort ruft) oder ein versehentliches Einloggen freigeben
    const manual = state.teams.map((t, ti) => `
      <div class="qset" style="--c:${esc(t.color)}">
        <span class="qset__name">${esc(t.name)}</span>
        ${LETTERS.map((L, i) => `<button class="lbtn ${q.answers[ti] === i ? 'is-on' : ''}" data-set="${ti}" data-answer="${i}">${L}</button>`).join('')}
        <button class="lbtn lbtn--ghost" data-set="${ti}" data-answer="" ${q.answers[ti] === null ? 'disabled' : ''}>Freigeben</button>
      </div>`).join('');

    $('quizNow').innerHTML = `
      <p class="quiz-now__q"><span>Frage ${q.index + 1}/${q.total}:</span> ${esc(cur.q)}</p>
      ${answers}
      ${manual}
      <p class="quiz-now__tally">Eingeloggt: ${state.teams.map((t, i) => `${esc(t.name)} ${q.locked[i] ? '✓' : '–'}`).join(' · ')} — Stand im Quiz: ${tallyText(q)}</p>`;
  }

  const tallyText = (q) => state.teams.map((t, i) => `${esc(t.name)} ${q.tally[i]}`).join(' · ');

  $('quizCtl').addEventListener('click', (e) => {
    const b = e.target.closest('[data-quiz]');
    if (b) send(b.dataset.quiz);
  });

  $('quizNow').addEventListener('click', (e) => {
    const b = e.target.closest('[data-set]');
    if (!b) return;
    send('quiz.set', { team: Number(b.dataset.set), answer: b.dataset.answer === '' ? null : Number(b.dataset.answer) });
  });

  function fillQuizForm() {
    const f = $('quizForm'), q = state.quiz;
    f.source.value = q.source;
    f.seconds.value = q.seconds;
    f.points.value = q.points;
  }

  $('quizForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const ok = await send('quiz.config', { source: f.source.value, seconds: Number(f.seconds.value), points: Number(f.points.value) });
    if (ok) { fillQuizForm(); toast(`Quiz gespeichert · ${ok.quiz.total} Fragen · Sieger bekommt ${ok.quiz.points}`, true); }
  });

  $('quizReset').addEventListener('click', () => {
    if (confirm('Quiz zurücksetzen? Alle Quiz-Punkte werden wieder abgezogen, es geht bei Frage 1 los.')) send('quiz.reset');
  });

  // ---------- Guess the Game ----------
  const GG_PHASES = { intro: 'Startbild', picture: 'Bild läuft', shown: 'Tipps werden gezeigt', solved: 'aufgelöst', done: 'Ergebnis' };

  function renderGuess() {
    const g = state.guess, onScreen = state.screen === 'guess';
    const running = ['picture', 'shown'].includes(g.phase);
    const hasNext = g.game + 1 < g.games.length;
    const btn = (act, label, cls = '') => `<button class="lbtn ${cls}" data-gg="${act}">${label}</button>`;
    const btns = [];

    if (!onScreen) btns.push(btn('guess.open', 'Auf den Bildschirm', 'lbtn--primary'));
    if (g.phase === 'picture') btns.push(btn('guess.stop', 'Zeit für dieses Bild beenden'));
    if (!running && hasNext && g.phase !== 'done') {
      btns.push(btn('guess.next', `▶ Spiel ${g.game + 2} starten`, onScreen ? 'lbtn--mint' : ''));
    }
    if (!running && g.game >= 0 && g.phase !== 'done') btns.push(btn('guess.done', 'Ergebnis zeigen', !hasNext ? 'lbtn--primary' : ''));
    if (onScreen) btns.push(btn('guess.close', 'Zurück zum Punktestand', 'lbtn--ghost'));
    $('ggCtl').innerHTML = btns.join('');

    // Aktuelles Bild + Tipps (die Spielleitung sieht sie sofort)
    if (running || g.phase === 'solved') {
      const cur = g.games[g.game];
      const guesses = state.teams.map((t, i) => {
        const x = g.guesses[i];
        return `<p class="gg-now__guess" style="--c:${esc(t.color)}"><b>${esc(t.name)}:</b> ${x ? `„${esc(x.text)}“ ${x.correct ? '✓' : '✗'}` : '–'}</p>`;
      }).join('');
      $('ggNow').innerHTML = `
        <img src="${esc(g.image)}" alt="">
        <div>
          <p class="gg-now__answer">${esc(cur.name)} · Bild ${g.picture + 1}/${g.counts[g.game]}</p>
          ${guesses}
        </div>`;
    } else {
      $('ggNow').innerHTML = '';
    }

    // Spiele mit Bildanzahl + Korrektur (wer hat auf welchem Bild erraten)
    if (busy($('ggGames'))) return;
    $('ggGames').innerHTML = g.games.map((game, i) => {
      const n = g.counts[i] ?? 0;
      const played = i < g.game || (i === g.game && !running);
      const res = g.results[i] || [null, null];
      const selects = played ? state.teams.map((t, ti) => `
        <select data-gg-set="${i}" data-team="${ti}" style="--c:${esc(t.color)}" title="${esc(t.name)}">
          <option value="">${esc(t.name)}: –</option>
          ${[1, 2, 3, 4, 5].map((p) => `<option value="${p}" ${res[ti] === p ? 'selected' : ''}>${esc(t.name)}: Bild ${p}</option>`).join('')}
        </select>`).join('') : '<span></span><span></span>';
      return `
        <div class="gg-row ${i === g.game && running ? 'is-current' : ''}">
          <span class="gg-row__name">${i + 1}. ${esc(game.name)}
            <span class="gg-row__count ${n ? '' : 'is-missing'}">${n ? `${n} ${n === 1 ? 'Bild' : 'Bilder'} im Ordner ${i + 1}/` : `keine Bilder in Ordner ${i + 1}/`}</span></span>
          ${selects}
        </div>`;
    }).join('') + `<p class="quiz-now__tally">Ratepunkte: ${state.teams.map((t, i) => `${esc(t.name)} ${g.score[i]}`).join(' · ')}</p>`;
  }

  $('ggCtl').addEventListener('click', (e) => {
    const b = e.target.closest('[data-gg]');
    if (b) send(b.dataset.gg);
  });

  $('ggGames').addEventListener('change', (e) => {
    const sel = e.target.closest('[data-gg-set]');
    if (!sel) return;
    sel.blur();
    send('guess.set', { game: Number(sel.dataset.ggSet), team: Number(sel.dataset.team), picture: sel.value === '' ? null : Number(sel.value) });
  });

  function fillGuessForm() {
    const f = $('ggForm'), g = state.guess;
    f.source.value = g.source;
    f.seconds.value = g.seconds;
    f.points.value = g.points;
  }

  $('ggForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const ok = await send('guess.config', { source: f.source.value, seconds: Number(f.seconds.value), points: Number(f.points.value) });
    if (ok) { fillGuessForm(); toast(`${ok.guess.games.length} Spiele gespeichert`, true); }
  });

  $('ggReset').addEventListener('click', () => {
    if (confirm('Guess the Game zurücksetzen? Ergebnisse und Sieg-Punkte werden gelöscht.')) send('guess.reset');
  });

  // ---------- Guess the Price ----------
  const PR_PHASES = { intro: 'Startbild', item: 'Runde läuft', locked: 'Tipps werden gezeigt', reveal: 'aufgelöst', done: 'Ergebnis' };
  const eur = (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);

  function renderPrice() {
    const p = state.price, onScreen = state.screen === 'price';
    const running = ['item', 'locked'].includes(p.phase);
    const hasNext = p.item + 1 < p.items.length;
    const btn = (act, label, cls = '') => `<button class="lbtn ${cls}" data-pr="${act}">${label}</button>`;
    const btns = [];

    if (!onScreen) btns.push(btn('price.open', 'Auf den Bildschirm', 'lbtn--primary'));
    if (p.phase === 'item') btns.push(btn('price.stop', 'Zeit beenden'));
    if (running) btns.push(btn('price.reveal', 'Sofort auflösen'));
    if (!running && hasNext && p.phase !== 'done') btns.push(btn('price.next', `▶ Gegenstand ${p.item + 2} starten`, onScreen ? 'lbtn--mint' : ''));
    if (!running && p.item >= 0 && p.phase !== 'done') btns.push(btn('price.done', 'Ergebnis zeigen', !hasNext ? 'lbtn--primary' : ''));
    if (onScreen) btns.push(btn('price.close', 'Zurück zum Punktestand', 'lbtn--ghost'));
    $('prCtl').innerHTML = btns.join('');

    // Aktueller Gegenstand: Preis + Tipps; Tipp von Hand eintragen (z. B. wenn ein Team ruft)
    const nowBox = $('prNow');
    if (!busy(nowBox)) {
      if (p.item >= 0 && p.phase !== 'intro') {
        const cur = p.items[p.item];
        nowBox.innerHTML = `
          ${p.image ? `<img src="${esc(p.image)}" alt="">` : '<span></span>'}
          <div>
            <p class="gg-now__answer">${esc(cur.name)} · ${eur(cur.price)}</p>
            ${state.teams.map((t, i) => {
              const g = p.guesses[i];
              return `<form class="pr-now__guess" data-pr-team="${i}" style="--c:${esc(t.color)}">
                <b>${esc(t.name)}:</b> <span>${g ? eur(g.value) : '–'}</span>
                <input class="linput" name="text" placeholder="Tipp eintragen" inputmode="decimal">
                <button class="lbtn" type="submit">OK</button>
              </form>`;
            }).join('')}
          </div>`;
      } else {
        nowBox.innerHTML = '';
      }
    }

    // Gegenstände: Bild vorhanden? Ergebnis + Korrektur
    if (busy($('prItems'))) return;
    $('prItems').innerHTML = p.items.map((it, i) => {
      const r = p.results[i];
      const w = p.winners[i];
      const auto = r && r.override === null;
      const sel = r ? `
        <select data-pr-set="${i}" style="--c:var(--lan-yellow)" title="Wer hat die Runde gewonnen?">
          <option value="auto" ${auto ? 'selected' : ''}>automatisch${auto ? ` (${w[0] && w[1] ? 'beide' : w[0] ? esc(state.teams[0].name) : w[1] ? esc(state.teams[1].name) : 'keiner'})` : ''}</option>
          <option value="0" ${r.override === '0' ? 'selected' : ''}>${esc(state.teams[0].name)}</option>
          <option value="1" ${r.override === '1' ? 'selected' : ''}>${esc(state.teams[1].name)}</option>
          <option value="both" ${r.override === 'both' ? 'selected' : ''}>beide</option>
          <option value="none" ${r.override === 'none' ? 'selected' : ''}>keiner</option>
        </select>` : '<span></span>';
      return `
        <div class="gg-row ${i === p.item && running ? 'is-current' : ''}" style="grid-template-columns: 1fr auto">
          <span class="gg-row__name">${i + 1}. ${esc(it.name)}
            <span class="gg-row__count ${p.images[i] ? '' : 'is-missing'}">${eur(it.price)} · ${p.images[i] ? `Bild ${i + 1}.png ✓` : `kein Bild (${i + 1}.png)`}</span></span>
          ${sel}
        </div>`;
    }).join('') + `<p class="quiz-now__tally">Runden: ${state.teams.map((t, i) => `${esc(t.name)} ${p.rounds[i]}`).join(' · ')}</p>`;
  }

  $('prCtl').addEventListener('click', (e) => {
    const b = e.target.closest('[data-pr]');
    if (b) send(b.dataset.pr);
  });

  $('prNow').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target.closest('[data-pr-team]');
    if (!f || !f.text.value.trim()) return;
    document.activeElement?.blur();
    if (await send('price.guess', { team: Number(f.dataset.prTeam), text: f.text.value })) toast('Tipp eingetragen', true);
  });

  $('prItems').addEventListener('change', (e) => {
    const sel = e.target.closest('[data-pr-set]');
    if (!sel) return;
    sel.blur();
    send('price.set', { item: Number(sel.dataset.prSet), winner: sel.value });
  });

  function fillPriceForm() {
    const f = $('prForm'), p = state.price;
    f.source.value = p.source;
    f.seconds.value = p.seconds;
    f.points.value = p.points;
  }

  $('prForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const ok = await send('price.config', { source: f.source.value, seconds: Number(f.seconds.value), points: Number(f.points.value) });
    if (ok) { fillPriceForm(); toast(`${ok.price.items.length} Gegenstände gespeichert`, true); }
  });

  $('prReset').addEventListener('click', () => {
    if (confirm('Guess the Price zurücksetzen? Ergebnisse und Sieg-Punkte werden gelöscht.')) send('price.reset');
  });

  // ---------- Awards ----------
  const AW_PHASES = { off: 'noch nicht gestartet', voting: 'Abstimmung läuft', closed: 'Abstimmung beendet', reveal: 'Verleihung', summary: 'alle Gewinner' };

  function renderAwards() {
    const aw = state.awards, onScreen = state.screen === 'awards';
    const n = aw.categories.length, hasNext = aw.index + 1 < n;
    const btn = (act, label, cls = '') => `<button class="lbtn ${cls}" data-aw="${act}">${label}</button>`;
    const btns = [];

    if (aw.phase === 'off') btns.push(btn('awards.open', 'Abstimmung starten', 'lbtn--primary'));
    if (aw.phase === 'voting') btns.push(btn('awards.close', 'Abstimmung beenden'));
    if (['voting', 'closed', 'reveal'].includes(aw.phase) && hasNext) {
      btns.push(btn('awards.next', aw.index < 0 ? '▶ Ersten Award aufdecken' : `▶ Nächster Award (${aw.index + 2}/${n})`, 'lbtn--mint'));
    }
    if (aw.phase === 'reveal' && !hasNext) btns.push(btn('awards.summary', 'Alle Gewinner zeigen', 'lbtn--primary'));
    if (!onScreen && aw.phase !== 'off') btns.push(btn('show', 'Auf den Bildschirm'));
    if (onScreen) btns.push(btn('awards.hide', 'Zurück zum Punktestand', 'lbtn--ghost'));
    $('awCtl').innerHTML = btns.join('');

    // Zwischenstand – nur hier sichtbar, der Bildschirm erfährt die Gewinner erst beim Aufdecken
    $('awNow').innerHTML = aw.phase === 'off' ? '' : aw.categories.map((c, i) => {
      const r = aw.results[i];
      const tally = r.tally.length
        ? r.tally.slice(0, 3).map((t, k) => (k === 0 || t.votes === r.votes ? `<b>${esc(t.name)} ${t.votes}</b>` : `${esc(t.name)} ${t.votes}`)).join(' · ')
        : 'noch keine Stimmen';
      const cls = i === aw.index && aw.phase === 'reveal' ? 'is-current' : (i < aw.index ? 'is-shown' : '');
      return `<div class="aw-row ${cls}"><span class="aw-row__emoji">${esc(c.emoji)}</span><span class="aw-row__title">${esc(c.title)}</span><span class="aw-row__tally">${tally}</span></div>`;
    }).join('');
  }

  $('awCtl').addEventListener('click', (e) => {
    const b = e.target.closest('[data-aw]');
    if (!b) return;
    if (b.dataset.aw === 'show') send('screen', { screen: 'awards' });
    else send(b.dataset.aw);
  });

  function fillAwardsForm() {
    $('awForm').source.value = state.awards.source;
  }

  $('awForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const ok = await send('awards.config', { source: e.target.source.value });
    if (ok) { fillAwardsForm(); toast(`${ok.awards.categories.length} Kategorien gespeichert`, true); }
  });

  $('awReset').addEventListener('click', () => {
    if (confirm('Alle Award-Stimmen löschen und von vorne anfangen?')) send('awards.reset');
  });

  // ---------- Bildschirm ----------
  function renderScreens() {
    document.querySelectorAll('#screens [data-screen]').forEach((b) => b.classList.toggle('is-on', b.dataset.screen === state.screen));
  }

  $('screens').addEventListener('click', (e) => {
    const b = e.target.closest('[data-screen]');
    if (!b) return;
    if (b.dataset.screen === 'finale' && !confirm('Finale zeigen? Der Gewinner wird auf dem Bildschirm enthüllt.')) return;
    send('screen', { screen: b.dataset.screen });
  });

  // ---------- Spiele ----------
  function renderGames() {
    const list = $('games');
    if (busy(list) || drag) return;
    const [A, B] = state.teams;
    list.innerHTML = state.games.length ? state.games.map((g, i) => {
      let info = 'Noch nicht gespielt';
      if (g.status === 'running') info = '<b style="--c:var(--lan-yellow)">Läuft gerade</b>';
      if (g.status === 'done') {
        info = g.result === 'draw'
          ? `Unentschieden · je +${Math.ceil(g.points / 2)}`
          : `Sieger: <b style="--c:${esc(state.teams[g.result].color)}">${esc(state.teams[g.result].name)}</b> · +${g.points}`;
      }
      const winBtns = `
        <button class="lbtn lbtn--team" style="--c:${esc(A.color)}" data-game="finish" data-result="0">${esc(A.name)} gewinnt</button>
        <button class="lbtn lbtn--team" style="--c:${esc(B.color)}" data-game="finish" data-result="1">${esc(B.name)} gewinnt</button>
        <button class="lbtn" data-game="finish" data-result="draw">Unentschieden</button>`;
      const btns = g.status === 'done'
        ? '<button class="lbtn lbtn--ghost" data-game="reopen">Ergebnis zurücknehmen</button>'
        : (g.status === 'running' ? winBtns : '<button class="lbtn lbtn--mint" data-game="start">▶ Starten</button>' + winBtns);
      const open = openEdits.has(g.id);
      return `
        <li class="game is-${g.status}" data-id="${g.id}">
          <div class="game__head">
            <span class="game__handle" aria-label="Ziehen zum Sortieren">⠿</span>
            <span class="game__num">${i + 1}</span>
            <span class="game__title">${esc(g.name)}</span>
            ${g.time ? `<span class="game__time" data-time="${esc(g.time)}">${esc(g.time)}</span>` : ''}
            <span class="game__pts">${g.points} P</span>
          </div>
          <div class="game__state">${info}</div>
          <div class="game__btns">${btns}</div>
          <div class="game__tools">
            <button class="lbtn lbtn--ghost" data-game="edit">${open ? 'Schließen' : 'Bearbeiten'}</button>
          </div>
          <form class="game__edit" ${open ? '' : 'hidden'}>
            <input class="linput" name="name" value="${esc(g.name)}" maxlength="50" required>
            <textarea class="linput" name="description" maxlength="300" placeholder="Regeln / Beschreibung">${esc(g.description)}</textarea>
            <div class="form__pair">
              <label class="field">Startzeit <input class="linput" name="time" type="time" value="${esc(g.time || '')}"></label>
              <label class="field">Punkte für den Sieg <input class="linput" name="points" type="number" min="1" value="${g.points}"></label>
            </div>
            <div class="seg">
              <button class="lbtn lbtn--primary" type="submit">Speichern</button>
              <button class="lbtn lbtn--danger" type="button" data-game="delete">Löschen</button>
            </div>
          </form>
        </li>`;
    }).join('') : '<p class="empty">Noch keine Spiele – unten hinzufügen.</p>';
  }

  $('games').addEventListener('click', (e) => {
    const b = e.target.closest('[data-game]');
    if (!b) return;
    const li = b.closest('.game'), id = li.dataset.id;
    const g = state.games.find((x) => x.id === id);
    switch (b.dataset.game) {
      case 'start': send('game.start', { id }); break;
      case 'finish': {
        const r = b.dataset.result === 'draw' ? 'draw' : Number(b.dataset.result);
        const who = r === 'draw' ? 'Unentschieden' : `${state.teams[r].name} gewinnt`;
        if (confirm(`${g.name}: ${who}?`)) send('game.finish', { id, result: r });
        break;
      }
      case 'reopen':
        if (confirm(`Ergebnis von „${g.name}“ zurücknehmen? Die Punkte werden wieder abgezogen.`)) send('game.reopen', { id });
        break;
      case 'edit':
        openEdits.has(id) ? openEdits.delete(id) : openEdits.add(id);
        li.querySelector('.game__edit').hidden = !openEdits.has(id);
        b.textContent = openEdits.has(id) ? 'Schließen' : 'Bearbeiten';
        break;
      case 'delete':
        if (confirm(`„${g.name}“ löschen?${g.status === 'done' ? ' Die Punkte dafür werden abgezogen.' : ''}`)) {
          openEdits.delete(id);
          send('game.delete', { id });
        }
        break;
    }
  });

  $('games').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target, id = f.closest('.game').dataset.id;
    document.activeElement?.blur();
    const ok = await send('game.update', { id, name: f.name.value, description: f.description.value, points: Number(f.points.value), time: f.time.value });
    if (ok) { openEdits.delete(id); renderGames(); }
  });

  // ---------- Drag & Drop (Pointer Events: Maus und Finger) ----------
  let drag = null;
  const ids = () => [...$('games').querySelectorAll('.game')].map((li) => li.dataset.id);

  $('games').addEventListener('pointerdown', (e) => {
    const handle = e.target.closest('.game__handle');
    if (!handle || e.button > 0) return;
    e.preventDefault();
    const li = handle.closest('.game');
    handle.setPointerCapture(e.pointerId);
    drag = { li, startY: e.clientY, order: ids() };
    li.classList.add('is-dragging');
  });

  $('games').addEventListener('pointermove', (e) => {
    if (!drag) return;
    const { li } = drag;
    const list = $('games');

    // Mitte des gezogenen Spiels mit den Nachbarn vergleichen und im DOM daran vorbeischieben
    const mid = li.offsetTop + li.offsetHeight / 2 + (e.clientY - drag.startY);
    const prev = li.previousElementSibling, next = li.nextElementSibling;
    const before = li.offsetTop;
    if (prev && mid < prev.offsetTop + prev.offsetHeight / 2) list.insertBefore(li, prev);
    else if (next && mid > next.offsetTop + next.offsetHeight / 2) list.insertBefore(next, li);
    drag.startY += li.offsetTop - before;

    li.style.transform = `translateY(${e.clientY - drag.startY}px)`;

    // Am Bildschirmrand mitscrollen (Handy)
    if (e.clientY < 70) window.scrollBy(0, -12);
    else if (e.clientY > innerHeight - 70) window.scrollBy(0, 12);
  });

  const endDrag = () => {
    if (!drag) return;
    const { li, order } = drag;
    drag = null;
    li.style.transform = '';
    li.classList.remove('is-dragging');
    const now = ids();
    if (now.join() !== order.join()) send('game.reorder', { ids: now }).then((ok) => { if (!ok) renderGames(); });
    else renderGames();
  };
  $('games').addEventListener('pointerup', endDrag);
  $('games').addEventListener('pointercancel', endDrag);

  $('addGame').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    document.activeElement?.blur();
    const ok = await send('game.add', { name: f.name.value, description: f.description.value, points: Number(f.points.value), time: f.time.value });
    if (ok) { f.reset(); f.closest('details').open = false; toast('Spiel hinzugefügt', true); }
  });

  // ---------- Timer & Durchsage ----------
  const fmt = (sec) => {
    sec = Math.max(0, Math.ceil(sec));
    const m = Math.floor(sec / 60), s = sec % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
  };

  function renderLive() {
    const t = state?.timer;
    if (t) {
      const left = t.endsAt - (Date.now() / 1000 + offset);
      $('timerLive').textContent = left > 0 ? `läuft · ${fmt(left)}` : 'abgelaufen';
    } else {
      $('timerLive').textContent = '';
    }
    $('announceLive').textContent = state?.announcement ? 'wird angezeigt' : '';

    const gg = state?.guess;
    if (gg) {
      let live = gg.game >= 0 ? `${GG_PHASES[gg.phase]} · Spiel ${gg.game + 1}/${gg.games.length}` : GG_PHASES[gg.phase];
      if (gg.phase === 'picture') live += ` · Bild ${gg.picture + 1} · ${Math.max(0, Math.ceil(gg.endsAt - (Date.now() / 1000 + offset)))} s`;
      $('ggLive').textContent = live;
    }

    const pr = state?.price;
    if (pr) {
      let live = pr.item >= 0 ? `${PR_PHASES[pr.phase]} · ${pr.item + 1}/${pr.items.length}` : PR_PHASES[pr.phase];
      if (pr.phase === 'item') live += ` · ${Math.max(0, Math.ceil(pr.endsAt - (Date.now() / 1000 + offset)))} s`;
      $('prLive').textContent = live;
    }

    const aw = state?.awards;
    if (aw) {
      $('awLive').textContent = AW_PHASES[aw.phase] + (aw.phase === 'off' ? '' : ` · ${aw.voterCount} ${aw.voterCount === 1 ? 'Handy' : 'Handys'}`);
    }

    const q = state?.quiz;
    if (q) {
      let live = q.index >= 0 ? `${PHASES[q.phase]} · Frage ${q.index + 1}/${q.total}` : PHASES[q.phase];
      if (q.phase === 'question') live += ` · ${Math.max(0, Math.ceil(q.endsAt - (Date.now() / 1000 + offset)))} s`;      $('quizLive').textContent = live;
    }

    // Geplante Startzeit verpasst? Zeit-Chip gelb (gleiche ±12-h-Logik wie der Bildschirm)
    const nowS = Date.now() / 1000 + offset;
    document.querySelectorAll('.game.is-open .game__time').forEach((el) => {
      const [h, m] = el.dataset.time.split(':').map(Number);
      const d = new Date(nowS * 1000);
      d.setHours(h, m, 0, 0);
      let t = d.getTime() / 1000;
      if (t - nowS > 12 * 3600) t -= 86400;
      if (nowS - t > 12 * 3600) t += 86400;
      el.classList.toggle('is-overdue', t <= nowS);
      el.title = t <= nowS ? 'Sollte schon laufen' : '';
    });
  }

  const startTimer = (minutes) => {
    const label = $('timerForm').label.value;
    send('timer.start', { seconds: Math.round(minutes * 60), label });
  };

  $('timerForm').addEventListener('submit', (e) => {
    e.preventDefault();
    startTimer(Number(e.target.minutes.value));
  });
  document.querySelectorAll('[data-timer]').forEach((b) => b.addEventListener('click', () => startTimer(Number(b.dataset.timer))));
  $('timerStop').addEventListener('click', () => send('timer.stop'));

  $('announceForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (e.target.text.value.trim()) send('announce', { text: e.target.text.value });
  });
  $('announceClear').addEventListener('click', () => send('announce', { text: '' }));

  // ---------- Teams ----------
  function renderTeams() {
    const wrap = $('teamsEdit');
    if (busy(wrap)) return;
    wrap.innerHTML = state.teams.map((t, i) => `
      <form class="team-form" data-team="${i}" style="--c:${esc(t.color)}">
        <div class="team-form__row">
          <input class="linput" name="name" value="${esc(t.name)}" maxlength="30" required aria-label="Teamname">
          <input class="linput" name="color" type="color" value="${esc(t.color)}" aria-label="Farbe">
        </div>
        <textarea class="linput" name="members" placeholder="Spieler (einer pro Zeile)">${esc(t.members.join('\n'))}</textarea>
        <button class="lbtn" type="submit">Speichern</button>
      </form>`).join('');
  }

  $('teamsEdit').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    document.activeElement?.blur();
    const ok = await send('team', { team: Number(f.dataset.team), name: f.name.value, color: f.color.value, members: f.members.value });
    if (ok) toast('Team gespeichert', true);
  });

  // ---------- Verlauf ----------
  function renderLog() {
    const items = [...state.log].reverse();
    $('log').innerHTML = items.length ? items.map((e) => {
      const t = state.teams[e.team];
      const time = new Date(e.at * 1000).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
      const undo = e.game ? '' : `<button class="lbtn lbtn--ghost log__undo" data-undo="${e.id}">Rückgängig</button>`;
      return `
        <li class="log__item" style="--c:${esc(t.color)}">
          <span class="log__time">${time}</span>
          <span class="log__delta">${e.delta > 0 ? '+' : '−'}${Math.abs(e.delta)}</span>
          <span class="log__reason">${esc(t.name)}${e.reason ? ' · ' + esc(e.reason) : ''}</span>
          ${undo}
        </li>`;
    }).join('') : '<p class="empty">Noch keine Punkte vergeben.</p>';
  }

  $('log').addEventListener('click', (e) => {
    const b = e.target.closest('[data-undo]');
    if (b) send('undo', { id: b.dataset.undo });
  });

  // ---------- Sonstiges ----------
  function fillSettings() {
    const f = $('settingsForm');
    f.title.value = state.title;
    f.subtitle.value = state.subtitle || '';
  }

  $('settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    if (await send('settings', { title: f.title.value, subtitle: f.subtitle.value })) toast('Gespeichert', true);
  });

  $('resetScores').addEventListener('click', () => {
    if (confirm('Neuen Abend starten? Alle Punkte und Spielergebnisse werden gelöscht. Teams und Spiele bleiben.')) send('reset');
  });
  $('resetAll').addEventListener('click', () => {
    if (confirm('Wirklich ALLES zurücksetzen – auch Teams und Spiele?')) send('reset', { everything: true }).then((s) => s && fillSettings());
  });

  // ---------- Los ----------
  load();
  // Während des Quiz schneller nachfragen, damit die Stimmen live mitlaufen
  (function loop() {
    setTimeout(() => load().then(loop), ['quiz', 'guess', 'price', 'awards'].includes(state?.screen) || state?.awards?.phase === 'voting' ? 1000 : 3000);
  })();
  setInterval(() => { renderLive(); renderStatus(); }, 1000);
})();
