(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Nur neu zeichnen, wenn sich etwas geändert hat – sonst starten Animationen bei jeder Abfrage neu
  const setHTML = (el, html) => { if (el._html !== html) { el._html = html; el.innerHTML = html; } };

  let state = null;
  let offset = 0;             // Serverzeit - lokale Zeit (Sekunden)
  let seenFlash = undefined;  // beim ersten Laden alte Einblendungen nicht nochmal zeigen
  let flashTimer = null;
  const shownScores = [null, null];

  const now = () => Date.now() / 1000 + offset;

  const fmt = (sec) => {
    sec = Math.max(0, Math.ceil(sec));
    const h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
    const mm = String(m).padStart(h ? 2 : 1, '0'), ss = String(s).padStart(2, '0');
    return h ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
  };

  // "HH:MM" → Zeitpunkt (Sekunden) innerhalb von ±12 h um jetzt – so klappt auch 01:00 nach Mitternacht
  function resolveTime(hhmm) {
    if (!hhmm) return null;
    const [h, m] = hhmm.split(':').map(Number);
    const d = new Date(now() * 1000);
    d.setHours(h, m, 0, 0);
    let t = d.getTime() / 1000;
    if (t - now() > 12 * 3600) t -= 86400;
    if (now() - t > 12 * 3600) t += 86400;
    return t;
  }

  // Nächstes Spiel = erstes noch nicht gespieltes in der Reihenfolge der Spielleitung
  const nextGame = (s) => s.games.find((g) => g.status === 'open') || null;

  const teamStyle = (t) => `style="--c:${esc(t.color)}"`;

  // ---------- Bildschirme ----------
  function renderTitle(s) {
    $('topTitle').textContent = s.title;
    setHTML($('logo'), renderLogo(s.title));
    $('subtitle').textContent = s.subtitle || '';
    setHTML($('titleTeams'), s.teams.map((t) => `
      <div class="versus__team" ${teamStyle(t)}>
        <div class="versus__name">${esc(t.name)}</div>
        <div class="versus__members">${t.members.map(esc).join(' · ')}</div>
      </div>`).join('<div class="versus__vs">vs</div>'));
  }

  // "LAN Night" → zweizeiliges Logo; andere Titel: erstes Wort oben, Rest unten
  function renderLogo(title) {
    const words = String(title).trim().split(/\s+/);
    const top = words.shift() || '';
    return `<span>${esc(top)}</span><span>${esc(words.join(' '))}</span>`;
  }

  function renderBoard(s) {
    const lead = s.scores[0] === s.scores[1] ? -1 : (s.scores[0] > s.scores[1] ? 0 : 1);
    const board = $('board');
    if (!board.children.length) {
      board.innerHTML = [0, 1].map((i) => `
        <div class="team" data-team="${i}">
          <div class="team__badge">Führt</div>
          <div class="team__name"></div>
          <div class="team__score">0</div>
          <div class="team__members"></div>
        </div>`).join('<div class="board__vs">vs</div>');
    }
    board.querySelectorAll('.team').forEach((el) => {
      const i = Number(el.dataset.team), t = s.teams[i];
      el.style.setProperty('--c', t.color);
      el.classList.toggle('is-leading', i === lead);
      el.querySelector('.team__name').textContent = t.name;
      el.querySelector('.team__members').textContent = t.members.join(' · ');
      countTo(el.querySelector('.team__score'), i, s.scores[i]);
    });

    setHTML($('track'), s.games.map((g, i) => {
      let cls = 'track__item', style = '', pts = '';
      if (g.status === 'running') cls += ' is-running';
      if (g.status === 'done') {
        cls += ' is-done';
        if (g.result === 'draw') {
          cls += ' is-draw';
          style = `style="--c:var(--lan-text-muted);--c1:${esc(s.teams[0].color)};--c2:${esc(s.teams[1].color)}"`;
          pts = '<span class="track__pts">=</span>';
        } else {
          style = teamStyle(s.teams[g.result]);
          pts = `<span class="track__pts">+${g.points}</span>`;
        }
      }
      const time = g.status === 'open' && g.time ? `<span class="track__time">${esc(g.time)}</span>` : '';
      return `<li class="${cls}" ${style} data-id="${g.id}"><span class="track__num">${i + 1}</span>${esc(g.name)}${time}${pts}</li>`;
    }).join(''));
  }

  // Punkte hochzählen statt springen
  function countTo(el, i, target) {
    const from = shownScores[i];
    shownScores[i] = target;
    if (from === null || from === target) { el.textContent = target; return; }
    el.classList.remove('is-bump'); void el.offsetWidth; el.classList.add('is-bump');
    const start = performance.now(), dur = 900;
    const step = (t) => {
      const p = Math.min(1, (t - start) / dur);
      el.textContent = Math.round(from + (target - from) * (1 - Math.pow(1 - p, 3)));
      if (p < 1 && shownScores[i] === target) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  function minibar(s) {
    return s.teams.map((t, i) => `
      <span class="minibar__team" ${teamStyle(t)}>${esc(t.name)} <span class="minibar__score">${s.scores[i]}</span></span>`)
      .join('<span class="minibar__sep">:</span>');
  }

  function renderGame(s) {
    const i = s.games.findIndex((g) => g.id === s.currentGame);
    const g = s.games[i];
    $('gameEyebrow').textContent = g ? `Spiel ${i + 1} von ${s.games.length} · um ${g.points} ${g.points === 1 ? 'Punkt' : 'Punkte'}` : 'Nächstes Spiel';
    $('gameName').textContent = g ? g.name : 'Gleich geht\'s los';
    $('gameDesc').textContent = g ? g.description : '';
    setHTML($('gameScores'), minibar(s));
  }

  function renderPause(s) {
    setHTML($('pauseScores'), minibar(s));
  }

  // ---------- Finale ----------
  let revealTimer = null, confettiRunning = false;
  function renderFinale(s) {
    const el = document.querySelector('.screen--finale');
    const [a, b] = s.scores;
    const winner = a === b ? null : (a > b ? 0 : 1);
    const w = $('finaleWinner');
    w.textContent = winner === null ? 'Unentschieden!' : s.teams[winner].name;
    w.style.setProperty('--c', winner === null ? 'var(--lan-yellow)' : s.teams[winner].color);
    $('finaleScore').textContent = `${s.teams[0].name} ${a} : ${b} ${s.teams[1].name}`;
    $('finaleEyebrow').textContent = winner === null ? 'Endstand' : `Und die ${s.title} gewinnt …`;

    // Spannungspause, dann Enthüllung (zeitgleich auf allen Bildschirmen über die Serverzeit)
    const revealAt = (s.finaleAt || 0) + 4;
    clearTimeout(revealTimer);
    const reveal = () => {
      el.classList.add('is-revealed');
      if (!confettiRunning && winner !== null) confetti([s.teams[winner].color, '#FFCA22', '#5BFEC4', '#ffffff']);
    };
    if (now() >= revealAt) reveal();
    else { el.classList.remove('is-revealed'); revealTimer = setTimeout(reveal, (revealAt - now()) * 1000); }
  }

  function confetti(colors) {
    const cv = $('confetti'), ctx = cv.getContext('2d');
    cv.width = innerWidth; cv.height = innerHeight;
    const parts = Array.from({ length: 220 }, () => ({
      x: Math.random() * cv.width, y: -Math.random() * cv.height,
      w: 6 + Math.random() * 8, h: 10 + Math.random() * 10,
      vy: 2 + Math.random() * 4, vx: -1.5 + Math.random() * 3,
      r: Math.random() * Math.PI, vr: -0.15 + Math.random() * 0.3,
      c: colors[Math.floor(Math.random() * colors.length)],
    }));
    confettiRunning = true;
    const frame = () => {
      if (state?.screen !== 'finale') { confettiRunning = false; ctx.clearRect(0, 0, cv.width, cv.height); return; }
      ctx.clearRect(0, 0, cv.width, cv.height);
      parts.forEach((p) => {
        p.x += p.vx; p.y += p.vy; p.r += p.vr;
        if (p.y > cv.height + 20) { p.y = -20; p.x = Math.random() * cv.width; }
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
        ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
      });
      requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  }

  // ---------- Glücksrad ----------
  // Der Server hat schon gezogen (picks) und den Startzeitpunkt festgelegt (at) –
  // so landen alle Bildschirme gleich, auch einer, der mitten im Drehen neu lädt.
  let wheelId = null;

  function renderWheel(s) {
    const w = s.wheel, overlay = $('wheels');
    overlay.hidden = !w;
    if (!w) { wheelId = null; return; }
    if (w.id === wheelId) return;
    wheelId = w.id;

    const cols = [...overlay.querySelectorAll('.wheel-col')];
    const finals = [], segs = [];
    cols.forEach((col, t) => {
      const team = s.teams[t], names = w.members[t], n = names.length, seg = 360 / n;
      col.style.setProperty('--c', team.color);
      col.classList.remove('is-done');
      col.querySelector('.wheel-col__team').textContent = team.name;
      col.querySelector('.wheel-col__pick').textContent = '';
      col.querySelector('.wheel__svg').innerHTML = wheelSvg(names, team.color);
      segs.push(seg);
      // Zielwinkel: gezogenes Feld unter den Zeiger (oben), plus ein paar volle Umdrehungen
      const turns = t === 0 ? 7 : 9;
      finals.push(turns * 360 - (w.picks[t] * seg + seg / 2 + w.jitter[t] * seg));
    });

    const lastSeg = [null, null];
    const id = w.id;
    const frame = () => {
      if (wheelId !== id) return;
      let running = false;
      cols.forEach((col, t) => {
        const dur = w.durations[t];
        const k = Math.min(1, Math.max(0, (now() - w.at) / dur));
        const angle = finals[t] * (1 - Math.pow(1 - k, 4));
        col.querySelector('.wheel__svg').style.transform = `rotate(${angle}deg)`;

        // Zeiger wackelt bei jedem Feld, an dem er vorbeikommt
        const under = Math.floor(((360 - (angle % 360)) % 360) / segs[t]);
        if (lastSeg[t] !== null && under !== lastSeg[t] && k < 1) {
          const ptr = col.querySelector('.wheel__pointer');
          ptr.classList.remove('is-tick'); void ptr.offsetWidth; ptr.classList.add('is-tick');
        }
        lastSeg[t] = under;

        if (k < 1) running = true;
        else if (!col.classList.contains('is-done')) {
          col.classList.add('is-done');
          col.querySelector('.wheel-col__pick').textContent = w.members[t][w.picks[t]];
          if (now() - w.at < dur + 2) burst(s.teams[t].color, overlay);
        }
      });
      if (running) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  }

  // Tortenstücke mit Namen; Farben abwechselnd hell/dunkel in der Teamfarbe
  function wheelSvg(names, color) {
    const n = names.length, seg = 360 / n, r = 96;
    const pt = (deg, rad) => {
      const a = (deg * Math.PI) / 180;
      return `${(rad * Math.sin(a)).toFixed(2)} ${(-rad * Math.cos(a)).toFixed(2)}`;
    };
    const font = Math.max(7, Math.min(13, 15 - n * 0.55));
    return names.map((name, i) => {
      const shade = n > 1 && i % 2 ? 52 : (n % 2 && i === n - 1 && n > 1 ? 76 : 100);
      const fill = shade === 100 ? color : `color-mix(in srgb, ${color} ${shade}%, #170e3d)`;
      const text = shade === 100 ? '#170e3d' : '#ffffff';
      const shape = n === 1
        ? `<circle r="${r}" style="fill:${esc(fill)}"/>`
        : `<path d="M0 0 L${pt(i * seg, r)} A${r} ${r} 0 ${seg > 180 ? 1 : 0} 1 ${pt((i + 1) * seg, r)} Z" style="fill:${esc(fill)}"/>`;
      const label = name.length > 14 ? name.slice(0, 13) + '…' : name;
      return `<g>${shape}
        <g transform="rotate(${i * seg + seg / 2})">
          <text x="0" y="-58" transform="rotate(90 0 -58)" text-anchor="middle" dominant-baseline="central"
            style="fill:${text};font:700 ${font}px 'Chakra Petch',sans-serif">${esc(label)}</text>
        </g></g>`;
    }).join('') + `<circle r="${r}" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1.5"/>`;
  }

  // Kurzer Konfetti-Regen in ein beliebiges Element
  function burst(color, host) {
    const cv = document.createElement('canvas');
    cv.className = 'confetti';
    host.appendChild(cv);
    const ctx = cv.getContext('2d');
    cv.width = innerWidth; cv.height = innerHeight;
    const colors = [color, '#FFCA22', '#5BFEC4', '#ffffff'];
    const parts = Array.from({ length: 110 }, () => ({
      x: cv.width / 2, y: cv.height * 0.6,
      vx: -9 + Math.random() * 18, vy: -6 - Math.random() * 12,
      w: 6 + Math.random() * 7, h: 9 + Math.random() * 9, r: Math.random() * 6, vr: -0.2 + Math.random() * 0.4,
      c: colors[Math.floor(Math.random() * colors.length)],
    }));
    const end = performance.now() + 3500;
    const frame = (t) => {
      ctx.clearRect(0, 0, cv.width, cv.height);
      if (t > end) { cv.remove(); return; }
      parts.forEach((p) => {
        p.vy += 0.35; p.x += p.vx; p.y += p.vy; p.r += p.vr;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
        ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
      });
      requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  }

  // ---------- Overlays ----------
  function renderFlash(s) {
    const f = s.flash;
    if (seenFlash === undefined) { seenFlash = f?.id ?? null; return; }
    if (!f || f.id === seenFlash) return;
    seenFlash = f.id;
    if (now() - f.at > 15) return;

    const el = $('flash'), card = $('flashCard');
    const draw = f.team === 'draw';
    const team = draw ? null : s.teams[f.team];
    card.style.setProperty('--c', draw ? 'var(--lan-yellow)' : team.color);
    if (f.kind === 'result') {
      $('flashLabel').textContent = draw ? `${f.text} · Unentschieden` : `${f.text} · Sieg`;
      $('flashDelta').textContent = `+${f.delta}`;
      $('flashTeam').textContent = draw ? 'für beide Teams' : team.name;
    } else {
      $('flashLabel').textContent = f.text || (f.delta > 0 ? 'Bonus' : 'Abzug');
      $('flashDelta').textContent = (f.delta > 0 ? '+' : '−') + Math.abs(f.delta);
      $('flashTeam').textContent = team.name;
    }

    el.hidden = false;
    el.classList.remove('is-leaving');
    card.style.animation = 'none'; void card.offsetWidth; card.style.animation = '';
    clearTimeout(flashTimer);
    flashTimer = setTimeout(() => {
      el.classList.add('is-leaving');
      flashTimer = setTimeout(() => { el.hidden = true; }, 500);
    }, f.kind === 'result' ? 6000 : 4000);
  }

  function renderAnnounce(s) {
    const a = s.announcement;
    $('announce').hidden = !a;
    document.body.classList.toggle('has-announce', !!a);
    if (a) $('announceText').textContent = a.text;
  }

  function renderTimers() {
    const s = state;
    const clock = new Date();
    $('clock').textContent = clock.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    if (!s) return;

    const t = s.timer;
    const left = t ? t.endsAt - now() : 0;
    const big = s.screen === 'game' ? $('gameTimer') : s.screen === 'pause' ? $('pauseTimer') : null;
    const text = left > 0 ? fmt(left) : 'Zeit!';

    [$('gameTimer'), $('pauseTimer')].forEach((el) => {
      const show = !!t && el === big;
      el.hidden = !show;
      if (show) {
        setHTML(el, (t.label ? `<span class="big-timer__label">${esc(t.label)}</span>` : '') + text);
        el.classList.toggle('is-over', left <= 0);
      }
    });

    renderNext(s);
    renderQuizTimer(s);
    renderGuessTimer(s);
    renderPriceTimer(s);

    const pill = $('timerPill');
    pill.hidden = !t || !!big || s.screen === 'finale';
    pill.textContent = (t?.label ? t.label + ' · ' : '') + text;
    pill.classList.toggle('is-over', left <= 0);
  }

  function renderNext(s) {
    const g = nextGame(s);
    const at = g ? resolveTime(g.time) : null;
    const left = at === null ? null : at - now();
    const overdue = left !== null && left <= 0;
    const timeText = left === null ? '' : (overdue ? 'sollte schon laufen' : `um ${g.time} · in ${fmt(left)}`);

    ['nextTitle', 'nextBoard', 'nextPause'].forEach((id) => {
      const el = $(id);
      // Läuft gerade ein Spiel, zeigt das Punktestand-Bild kein "Nächstes Spiel" – sonst verwirrend
      el.hidden = !g || (s.currentGame && id === 'nextBoard');
      if (el.hidden) return;
      el.classList.toggle('is-overdue', overdue);
      el.querySelector('.next__label').textContent = id === 'nextPause' ? 'Weiter mit' : 'Nächstes Spiel';
      el.querySelector('.next__name').textContent = g.name;
      el.querySelector('.next__time').textContent = timeText;
    });

    // Überfällige Spiele im Verlauf unten markieren
    $('track').querySelectorAll('.track__item').forEach((li) => {
      const game = s.games.find((x) => x.id === li.dataset.id);
      const t = game && game.status === 'open' ? resolveTime(game.time) : null;
      li.classList.toggle('is-overdue', t !== null && t <= now());
    });
  }

  // ---------- Quiz ----------
  const LETTERS = ['A', 'B', 'C', 'D'];
  const quizUrl = document.body.dataset.quizUrl;
  let qrCache = '';
  function qrSvg() {
    if (qrCache || !window.qrcode) return qrCache;
    qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
    const q = qrcode(0, 'M');
    q.addData(quizUrl);
    q.make();
    return (qrCache = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true }));
  }
  const shortUrl = quizUrl.replace(/^https?:\/\//, '');

  function renderQuiz(s) {
    const q = s.quiz;
    const main = $('quizMain'), foot = $('quizFoot');

    if (q.phase === 'intro') {
      setHTML(main, `
        <div class="quiz-intro">
          <div>
            <p class="eyebrow">Quiz-Runde</p>
            <h1 class="quiz-title">${esc(q.title)}</h1>
            ${q.rules ? `<p class="quiz-rules">${esc(q.rules)}</p>` : ''}
            <div class="quiz-meta">
              <span>${q.total} Fragen</span>
              <span>${q.seconds} Sekunden pro Frage</span>
              <span>Sieger-Team bekommt ${q.points} ${q.points === 1 ? 'Punkt' : 'Punkte'}</span>
            </div>
          </div>
          <div class="qr">
            <div class="qr__code">${qrSvg()}</div>
            <p class="qr__hint">Handy raus! Scannen &amp; Team wählen</p>
            <p class="qr__url">${esc(shortUrl)}</p>
          </div>
        </div>`);
      setHTML(foot, '');
      return;
    }

    if (q.phase === 'done') {
      const [a, b] = q.tally;
      const half = Math.ceil(q.points / 2);
      const verdict = a === b
        ? (a ? `Gleichstand im Quiz! Beide Teams +${half}` : 'Gleichstand im Quiz!')
        : `${esc(s.teams[a > b ? 0 : 1].name)} gewinnt das Quiz! +${q.points} ${q.points === 1 ? 'Punkt' : 'Punkte'}`;
      setHTML(main, `
        <p class="eyebrow">Quiz-Ergebnis</p>
        <h1 class="quiz-title" style="text-align:center">${esc(q.title)}</h1>
        <div class="quiz-done__teams">
          ${s.teams.map((t, i) => `
            <div class="quiz-done__team" style="--c:${esc(t.color)}">
              ${esc(t.name)}
              <span class="quiz-done__num">${q.tally[i]}</span>
              <span class="quiz-done__label">von ${q.index + 1} richtig</span>
            </div>`).join('')}
        </div>
        <p class="quiz-done__verdict">${verdict}</p>`);
      setHTML(foot, '');
      return;
    }

    // question / locked / reveal
    const shown = q.phase !== 'question';
    const revealed = q.phase === 'reveal';
    const answers = q.question.answers.map((text, i) => {
      const teams = shown ? s.teams.map((t, ti) => q.answers[ti] === i
        ? `<span class="answer__team" style="--c:${esc(t.color)}">${esc(t.name)}</span>` : '').join('') : '';
      const picked = q.answers.includes(i);
      const cls = ['answer', revealed && i === q.correct ? 'is-correct' : '', picked ? 'is-picked' : ''].join(' ');
      return `
        <div class="${cls}" style="--a:var(--ans-${i})">
          <span class="answer__letter">${LETTERS[i]}</span>
          <span class="answer__text">${esc(text)}</span>
          <span class="answer__teams">${teams}</span>
        </div>`;
    }).join('');

    setHTML(main, `
      <div class="quiz-q__head">
        <span class="eyebrow">Frage ${q.index + 1} / ${q.total}</span>
        <span class="quiz-timer" id="quizTimer"></span>
      </div>
      <div class="quiz-bar"><div class="quiz-bar__fill" id="quizBar"></div></div>
      <h1 class="quiz-q__text">${esc(q.question.q)}</h1>
      <div class="answers ${revealed ? 'is-revealed' : ''}">${answers}</div>`);

    const status = s.teams.map((t, i) => {
      let info, cls = 'qteam';
      if (q.phase === 'question') {
        // Nur WER schon drin ist – nicht was
        info = q.locked[i] ? '✓ eingeloggt' : 'überlegt noch …';
        if (q.locked[i]) cls += ' is-in';
      } else if (q.answers[i] === null) {
        info = 'keine Antwort';
        if (revealed) cls += ' is-wrong';
      } else if (!revealed) {
        info = `hat ${LETTERS[q.answers[i]]}`;
      } else if (q.answers[i] === q.correct) {
        info = 'richtig!'; cls += ' is-right';
      } else {
        info = 'leider falsch'; cls += ' is-wrong';
      }
      return `<span class="${cls}" style="--c:${esc(t.color)}"><span class="qteam__name">${esc(t.name)}</span><span class="qteam__info">${info}</span></span>`;
    }).join('');

    setHTML(foot, `
      <div class="quiz-foot__teams">${status}</div>
      ${q.phase === 'question' ? `<div class="qr qr--small"><p class="qr__hint">Mitraten:<br>${esc(shortUrl)}</p><div class="qr__code">${qrSvg()}</div></div>` : ''}`);
    renderQuizTimer(s);
  }

  function renderQuizTimer(s) {
    const el = $('quizTimer'), bar = $('quizBar');
    if (!el || s.screen !== 'quiz') return;
    const q = s.quiz;
    if (q.phase === 'question') {
      const left = Math.max(0, q.endsAt - now());
      el.textContent = Math.ceil(left);
      el.className = 'quiz-timer' + (left <= 5 ? ' is-low' : '');
      bar.style.transform = `scaleX(${Math.min(1, left / q.seconds)})`;
    } else if (q.phase === 'locked') {
      // Kurze Spannungspause bis zur Auflösung
      const left = Math.max(0, (q.revealAt ?? now()) - now());
      el.textContent = left > 0 ? `Auflösung in ${Math.ceil(left)} …` : 'Auflösung …';
      el.className = 'quiz-timer is-locked';
      bar.style.transform = 'scaleX(0)';
    } else {
      el.textContent = 'Auflösung';
      el.className = 'quiz-timer is-locked';
      bar.style.transform = 'scaleX(0)';
    }
  }

  // ---------- Guess the Game ----------
  const pts = (n) => `${n} ${n === 1 ? 'Ratepunkt' : 'Ratepunkte'}`;

  function renderGuess(s) {
    const g = s.guess;
    const playing = ['picture', 'shown', 'solved'].includes(g.phase);
    $('ggIntro').hidden = g.phase !== 'intro';
    $('ggPlay').hidden = !playing;
    $('ggDone').hidden = g.phase !== 'done';

    if (g.phase === 'intro') {
      setHTML($('ggIntro'), `
        <div class="quiz-intro">
          <div>
            <p class="eyebrow">Neue Runde</p>
            <h1 class="quiz-title">Guess the Game</h1>
            <p class="quiz-rules">Ihr seht ein Bild aus einem Spiel – welches ist es? Pro Team tippt ein Handy den Namen ein.
              Klappt es nicht, kommt das nächste Bild. Je früher ihr es erratet, desto mehr Ratepunkte.</p>
            <div class="quiz-meta">
              <span>${g.total} Spiele</span>
              <span>bis zu 5 Bilder pro Spiel</span>
              <span>${g.seconds} Sekunden pro Bild</span>
              <span>Bild 1 = 5 Ratepunkte … Bild 5 = 1</span>
              <span>Sieger-Team bekommt ${g.points} Punkte</span>
            </div>
          </div>
          <div class="qr">
            <div class="qr__code">${qrSvg()}</div>
            <p class="qr__hint">Handy raus! Scannen &amp; Team wählen</p>
            <p class="qr__url">${esc(shortUrl)}</p>
          </div>
        </div>`);
      return;
    }

    if (g.phase === 'done') {
      const [a, b] = g.score;
      const half = Math.ceil(g.points / 2);
      const verdict = a === b
        ? (a ? `Gleichstand! Beide Teams +${half}` : 'Gleichstand!')
        : `${esc(s.teams[a > b ? 0 : 1].name)} gewinnt! +${g.points} Punkte`;
      setHTML($('ggDone'), `
        <p class="eyebrow">Guess the Game – Ergebnis</p>
        <div class="quiz-done__teams">
          ${s.teams.map((t, i) => `
            <div class="quiz-done__team" style="--c:${esc(t.color)}">
              ${esc(t.name)}
              <span class="quiz-done__num">${g.score[i]}</span>
              <span class="quiz-done__label">Ratepunkte</span>
            </div>`).join('')}
        </div>
        <ul class="gg-list">
          ${(g.played || []).map((p) => `
            <li><span class="gg-list__name">${esc(p.name)}</span>
              ${s.teams.map((t, i) => `<span class="gg-list__team ${p.solvedBy[i] ? 'is-hit' : ''}" style="--c:${esc(t.color)}">${p.solvedBy[i] ? `Bild ${p.solvedBy[i]}` : '–'}</span>`).join('')}
            </li>`).join('')}
        </ul>
        <p class="quiz-done__verdict">${verdict}</p>`);
      return;
    }
    if (!playing) return;

    $('ggLabel').textContent = `Spiel ${g.game + 1} / ${g.total} · Bild ${g.picture + 1} / ${g.pictures}`;
    const img = $('ggImg');
    if (g.image && img.getAttribute('src') !== g.image) img.setAttribute('src', g.image);

    // Auflösung über dem Bild
    const ans = $('ggAnswer');
    ans.hidden = g.phase !== 'solved';
    if (g.phase === 'solved') {
      const by = s.teams.map((t, i) => g.solvedBy[i]
        ? `<span style="color:${esc(t.color)}">${esc(t.name)}</span> auf Bild ${g.solvedBy[i]} (+${6 - g.solvedBy[i]})` : '').filter(Boolean);
      setHTML(ans, `
        <span class="gg__answer-label">${by.length ? 'Erraten!' : 'Niemand hat\'s erraten'}</span>
        <span class="gg__answer-name">${esc(g.answer)}</span>
        ${by.length ? `<span class="gg__answer-by">${by.join(' · ')}</span>` : ''}`);
    }

    const side = s.teams.map((t, i) => {
      let body, cls = 'gg-team';
      const guess = g.guesses[i];
      if (g.phase === 'picture') {
        body = g.locked[i] ? '✓ hat getippt' : 'überlegt noch …';
        if (g.locked[i]) cls += ' is-in';
      } else if (g.phase === 'shown') {
        body = guess ? `„${esc(guess.text)}“ <b>${guess.correct ? '✓' : '✗'}</b>` : 'kein Tipp';
        cls += guess?.correct ? ' is-right' : ' is-wrong';
      } else {
        body = g.solvedBy[i] ? `erraten · +${6 - g.solvedBy[i]}` : 'nicht erraten';
        cls += g.solvedBy[i] ? ' is-right' : '';
      }
      return `
        <div class="${cls}" style="--c:${esc(t.color)}">
          <span class="gg-team__name">${esc(t.name)}</span>
          <span class="gg-team__body">${body}</span>
          <span class="gg-team__score">${pts(g.score[i])}</span>
        </div>`;
    }).join('');
    const qr = g.phase === 'picture' ? `<div class="qr qr--small"><p class="qr__hint">Mitraten:<br>${esc(shortUrl)}</p><div class="qr__code">${qrSvg()}</div></div>` : '';
    setHTML($('ggSide'), side + qr);
    renderGuessTimer(s);
  }

  function renderGuessTimer(s) {
    const g = s.guess, el = $('ggTimer'), bar = $('ggBar');
    if (s.screen !== 'guess' || !['picture', 'shown', 'solved'].includes(g.phase)) return;
    if (g.phase === 'picture') {
      const left = Math.max(0, g.endsAt - now());
      el.textContent = Math.ceil(left);
      el.className = 'quiz-timer' + (left <= 5 ? ' is-low' : '');
      bar.style.transform = `scaleX(${Math.min(1, left / g.seconds)})`;
    } else {
      el.textContent = g.phase === 'shown' ? 'Die Tipps …' : 'Auflösung';
      el.className = 'quiz-timer is-locked';
      bar.style.transform = 'scaleX(0)';
    }
  }

  // ---------- Guess the Price ----------
  const eur = (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
  const eurShort = (v) => (v >= 1e6 ? new Intl.NumberFormat('de-DE', { notation: 'compact', compactDisplay: 'long', maximumFractionDigits: 2 }).format(v) + ' Euro' : '');
  const COUNT_MS = 2600;   // so lange zählt der Preis hoch

  function renderPrice(s) {
    const p = s.price;
    const playing = ['item', 'locked', 'reveal'].includes(p.phase);
    $('prIntro').hidden = p.phase !== 'intro';
    $('prPlay').hidden = !playing;
    $('prDone').hidden = p.phase !== 'done';

    if (p.phase === 'intro') {
      setHTML($('prIntro'), `
        <div class="quiz-intro">
          <div>
            <p class="eyebrow">Neue Runde</p>
            <h1 class="quiz-title">Guess the Price</h1>
            <p class="quiz-rules">Was kostet das? Pro Team tippt ein Handy einen Preis ein – auch „430 Mio“ oder „7,4 Mrd“ geht.
              Das Team, das näher dran ist, gewinnt die Runde.</p>
            <div class="quiz-meta">
              <span>${p.total} Gegenstände</span>
              <span>${p.seconds} Sekunden pro Runde</span>
              <span>Sieger-Team bekommt ${p.points} Punkte</span>
            </div>
          </div>
          <div class="qr">
            <div class="qr__code">${qrSvg()}</div>
            <p class="qr__hint">Handy raus! Scannen &amp; Team wählen</p>
            <p class="qr__url">${esc(shortUrl)}</p>
          </div>
        </div>`);
      return;
    }

    if (p.phase === 'done') {
      const [a, b] = p.rounds;
      const half = Math.ceil(p.points / 2);
      const verdict = a === b
        ? (a ? `Gleichstand! Beide Teams +${half}` : 'Gleichstand!')
        : `${esc(s.teams[a > b ? 0 : 1].name)} gewinnt! +${p.points} Punkte`;
      setHTML($('prDone'), `
        <p class="eyebrow">Guess the Price – Ergebnis</p>
        <div class="quiz-done__teams">
          ${s.teams.map((t, i) => `
            <div class="quiz-done__team" style="--c:${esc(t.color)}">
              ${esc(t.name)}
              <span class="quiz-done__num">${p.rounds[i]}</span>
              <span class="quiz-done__label">Runden gewonnen</span>
            </div>`).join('')}
        </div>
        <ul class="pr-list">
          ${(p.played || []).map((r) => `
            <li><span class="pr-list__name">${esc(r.name)}<small>${eur(r.price)}</small></span>
              ${s.teams.map((t, i) => `<span class="pr-list__guess ${r.winner[i] ? 'is-win' : ''}" style="--c:${esc(t.color)}">${r.guesses[i] === null ? '–' : eur(r.guesses[i])}</span>`).join('')}
            </li>`).join('')}
        </ul>
        <p class="quiz-done__verdict">${verdict}</p>`);
      return;
    }
    if (!playing) return;

    $('prLabel').textContent = `Gegenstand ${p.item + 1} / ${p.total}`;
    $('prName').textContent = p.name;
    const img = $('prImg');
    img.hidden = !p.image;
    if (p.image && img.getAttribute('src') !== p.image) img.setAttribute('src', p.image);

    // Preis: "???" → beim Auflösen hochzählen
    const box = $('prPrice');
    if (p.phase !== 'reveal') {
      box._countFor = null;
      setHTML(box, `<span class="pr__price-label">Der Preis</span><span class="pr__price-value is-hidden">? ? ?</span>`);
    } else if (box._countFor !== p.item) {
      box._countFor = p.item;
      box._done = false;
      box.innerHTML = `<span class="pr__price-label">Der Preis</span><span class="pr__price-value" id="prCount"></span><span class="pr__price-short">${esc(eurShort(p.price))}</span>`;
      box._html = null;
      const el = $('prCount'), start = performance.now(), target = p.price;
      const step = (t) => {
        const k = Math.min(1, (t - start) / COUNT_MS);
        const eased = 1 - Math.pow(1 - k, 4);
        el.textContent = eur(k < 1 ? Math.round(target * eased) : target);
        if (k < 1 && box._countFor === p.item) requestAnimationFrame(step);
        else if (k >= 1) { box._done = true; box.classList.add('is-done'); renderPriceTeams(s); }
      };
      box.classList.remove('is-done');
      requestAnimationFrame(step);
    }
    renderPriceTeams(s);
    renderPriceTimer(s);
  }

  function renderPriceTeams(s) {
    const p = s.price, box = $('prPrice');
    const counted = p.phase === 'reveal' && box._done;   // Sieger erst zeigen, wenn der Preis fertig hochgezählt ist
    setHTML($('prTeams'), s.teams.map((t, i) => {
      let body = '', extra = '', cls = 'pr-team';
      if (p.phase === 'item') {
        body = p.locked[i] ? '✓ hat getippt' : 'überlegt noch …';
        if (p.locked[i]) cls += ' is-in';
      } else {
        const g = p.guesses[i];
        body = g === null ? 'kein Tipp' : `<span class="pr-team__guess">${eur(g)}</span>`;
        if (counted) {
          if (g !== null) {
            const diff = g - p.price;
            const pct = p.price ? Math.round((diff / p.price) * 100) : 0;
            extra = diff === 0 ? 'auf den Cent genau! 🎯'
              : `${eur(Math.abs(diff))} ${diff > 0 ? 'zu hoch' : 'zu niedrig'}${Math.abs(pct) >= 1 ? ` (${pct > 0 ? '+' : ''}${pct.toLocaleString('de-DE')} %)` : ''}`;
          }
          if (p.winner[i]) cls += ' is-win';
          else cls += ' is-lose';
        }
      }
      return `
        <div class="${cls}" style="--c:${esc(t.color)}">
          <span class="pr-team__name">${esc(t.name)}${counted && p.winner[i] ? '<span class="pr-team__badge">Runde!</span>' : ''}</span>
          <span class="pr-team__body">${body}</span>
          ${extra ? `<span class="pr-team__diff">${extra}</span>` : ''}
          <span class="pr-team__rounds">${p.rounds[i]} ${p.rounds[i] === 1 ? 'Runde' : 'Runden'}</span>
        </div>`;
    }).join('') + (p.phase === 'item' ? `<div class="qr qr--small"><p class="qr__hint">Mitraten:<br>${esc(shortUrl)}</p><div class="qr__code">${qrSvg()}</div></div>` : ''));
  }

  function renderPriceTimer(s) {
    const p = s.price, el = $('prTimer'), bar = $('prBar');
    if (s.screen !== 'price' || !['item', 'locked', 'reveal'].includes(p.phase)) return;
    if (p.phase === 'item') {
      const left = Math.max(0, p.endsAt - now());
      el.textContent = Math.ceil(left);
      el.className = 'quiz-timer' + (left <= 5 ? ' is-low' : '');
      bar.style.transform = `scaleX(${Math.min(1, left / p.seconds)})`;
    } else if (p.phase === 'locked') {
      const left = Math.max(0, (p.revealAt ?? now()) - now());
      el.textContent = left > 0 ? `Auflösung in ${Math.ceil(left)} …` : 'Auflösung …';
      el.className = 'quiz-timer is-locked';
      bar.style.transform = 'scaleX(0)';
    } else {
      el.textContent = 'Auflösung';
      el.className = 'quiz-timer is-locked';
      bar.style.transform = 'scaleX(0)';
    }
  }

  // ---------- Awards ----------
  function renderAwards(s) {
    const aw = s.awards, el = $('awardsMain');
    const teamOf = (name) => aw.players.find((p) => p.name === name)?.team;
    const winnerHtml = (r) => r.winners.length
      ? r.winners.map((n) => `<span class="award__winner" style="--c:${esc(s.teams[teamOf(n)]?.color || '#fff')}">${esc(n)}</span>`).join('<span class="award__and">&amp;</span>')
      : '<span class="award__winner award__winner--none">Keine Stimmen 🤷</span>';

    if (aw.phase === 'voting' || aw.phase === 'closed' || aw.phase === 'off') {
      const open = aw.phase === 'voting';
      setHTML(el, `
        <div class="awards-intro">
          <div>
            <p class="eyebrow">Award-Verleihung</p>
            <h1 class="quiz-title">Die Awards 🏆</h1>
            <ul class="award-list">
              ${aw.categories.map((c) => `<li><span class="award-list__emoji">${esc(c.emoji)}</span>${esc(c.title)}</li>`).join('')}
            </ul>
          </div>
          ${open ? `
          <div class="qr">
            <div class="qr__code">${qrSvg()}</div>
            <p class="qr__hint">Jetzt abstimmen!</p>
            <p class="qr__url">${esc(shortUrl)} · ${aw.voterCount} ${aw.voterCount === 1 ? 'Stimme' : 'Stimmen'}</p>
          </div>` : `
          <div class="awards-closed">
            <p class="awards-closed__num">${aw.voterCount}</p>
            <p class="qr__hint">Stimmen abgegeben</p>
            <p class="qr__url">Abstimmung beendet – gleich geht's los</p>
          </div>`}
        </div>`);
      return;
    }

    if (aw.phase === 'summary') {
      setHTML(el, `
        <p class="eyebrow">Award-Verleihung</p>
        <h1 class="quiz-title" style="text-align:center">Die Gewinner</h1>
        <div class="award-grid">
          ${aw.categories.map((c, i) => `
            <div class="award-card">
              <span class="award-card__emoji">${esc(c.emoji)}</span>
              <span class="award-card__title">${esc(c.title)}</span>
              <span class="award-card__winners">${aw.results[i] ? winnerHtml(aw.results[i]) : ''}</span>
            </div>`).join('')}
        </div>`);
      return;
    }

    // reveal: ein Award groß – erst Trommelwirbel, dann (wenn der Server ihn freigibt) der Gewinner
    const c = aw.categories[aw.index], r = aw.results[aw.index];
    setHTML(el, `
      <div class="award ${r ? 'is-revealed' : ''}">
        <p class="eyebrow">Award ${aw.index + 1} / ${aw.categories.length}</p>
        <div class="award__emoji">${esc(c.emoji)}</div>
        <h1 class="award__title">${esc(c.title)}</h1>
        ${r ? `
          <div class="award__winners">${winnerHtml(r)}</div>
          <p class="award__votes">${r.votes ? `mit ${r.votes} ${r.votes === 1 ? 'Stimme' : 'Stimmen'}` : ''}</p>`
        : '<p class="award__drum">Und der Award geht an …</p>'}
      </div>`);
    if (r && el._confettiFor !== aw.index && r.winners.length) {
      el._confettiFor = aw.index;
      awardBurst(s.teams[teamOf(r.winners[0])]?.color || '#FFCA22');
    }
  }

  // Kurzer Konfetti-Regen für einen Award (läuft ein paar Sekunden, eigene Leinwand)
  function awardBurst(color) {
    const cv = document.createElement('canvas');
    cv.className = 'confetti';
    document.querySelector('.screen--awards').appendChild(cv);
    const ctx = cv.getContext('2d');
    cv.width = innerWidth; cv.height = innerHeight;
    const colors = [color, '#FFCA22', '#5BFEC4', '#ffffff'];
    const parts = Array.from({ length: 140 }, () => ({
      x: cv.width / 2, y: cv.height * 0.55,
      vx: -9 + Math.random() * 18, vy: -6 - Math.random() * 12,
      w: 6 + Math.random() * 7, h: 9 + Math.random() * 9, r: Math.random() * 6, vr: -0.2 + Math.random() * 0.4,
      c: colors[Math.floor(Math.random() * colors.length)],
    }));
    const end = performance.now() + 4000;
    const frame = (t) => {
      ctx.clearRect(0, 0, cv.width, cv.height);
      if (t > end) { cv.remove(); return; }
      parts.forEach((p) => {
        p.vy += 0.35; p.x += p.vx; p.y += p.vy; p.r += p.vr;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r);
        ctx.fillStyle = p.c; ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
        ctx.restore();
      });
      requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  }

  function render(s) {
    document.querySelectorAll('.screen').forEach((el) => el.classList.toggle('is-active', el.dataset.screen === s.screen));
    $('topbar').classList.toggle('is-hidden', ['title', 'finale', 'quiz', 'guess', 'price', 'awards'].includes(s.screen));

    renderTitle(s);
    renderBoard(s);
    renderGame(s);
    renderPause(s);
    renderQuiz(s);
    renderGuess(s);
    renderPrice(s);
    renderAwards(s);
    if (s.screen === 'finale') renderFinale(s);
    else document.querySelector('.screen--finale').classList.remove('is-revealed');
    renderFlash(s);
    renderAnnounce(s);
    renderWheel(s);
    renderTimers();
  }

  // ---------- Abfrage ----------
  async function poll() {
    try {
      const res = await fetch('/lan-night/api/state?display=1', { cache: 'no-store' });
      if (res.ok) {
        const s = await res.json();
        offset = s.serverTime - Date.now() / 1000;
        if (!state || s.updatedAt !== state.updatedAt) { state = s; render(s); }
      }
    } catch (e) { /* kurz offline – nächster Versuch */ }
    setTimeout(poll, ['quiz', 'guess', 'price', 'awards'].includes(state?.screen) ? 400 : 1000);
  }

  setInterval(renderTimers, 250);
  poll();
})();
