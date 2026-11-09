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
    // Gezählt wird in Spielreihenfolge (wie viele schon durch sind), nicht nach Platz in der Liste
    const played = s.games.filter((x) => x.status === 'done').length;
    // Schnell gestartet → schnelle Regeln statt der normalen
    const fast = g && g.mode === 'fast' && g.fastRules;
    $('gameEyebrow').textContent = g ? `Spiel ${played + 1} von ${s.games.length} · um ${g.points} ${g.points === 1 ? 'Punkt' : 'Punkte'}${fast ? ' · ⚡ Schnelle Runde' : ''}` : 'Nächstes Spiel';
    $('gameName').textContent = g ? g.name : 'Gleich geht\'s los';
    $('gameDesc').textContent = g ? (fast ? g.fastRules : g.description) : '';

    // Ausgeloste Aufstellung (Spielautomat / Glücksrad) für dieses Spiel
    const lu = g && s.lineup && s.lineup.gameId === g.id ? s.lineup : null;
    $('gameLineup').hidden = !lu;
    setHTML($('gameLineup'), lu ? lu.teams.map((names, t) => `
      <div class="lineup__team" ${teamStyle(s.teams[t])}>${names.map((n) => `<span class="lineup__name">${esc(n)}</span>`).join('')}</div>`).join('') : '');
    setHTML($('gameScores'), minibar(s));
  }

  function renderPause(s) {
    setHTML($('pauseScores'), minibar(s));
  }

  // ---------- Spielauswahl ----------
  // Alle Spiele als Kacheln: offene neutral, gewonnene in Teamfarbe. Der Verlierer des letzten Spiels wählt.
  const pickStatus = new Map();   // Spiel → Status beim letzten Zeichnen (für die "gerade entschieden"-Animation)

  function renderPick(s) {
    const p = s.picker || {}, team = p.team === null || p.team === undefined ? null : s.teams[p.team];
    const why = {
      lost: p.game ? `hat ${p.game} verloren` : 'hat das letzte Spiel verloren',
      behind: 'liegt hinten',
      manual: 'darf wählen',
      tie: 'Gleichstand',
      first: 'Los geht\'s',
    }[p.why] || '';
    const target = s.target || 40;
    const winner = [0, 1].find((t) => s.scores[t] >= target);
    if (winner !== undefined) {
      // Ziel erreicht → kein "wer wählt", sondern der Sieger
      $('pickEyebrow').textContent = `Ziel erreicht · ${target} Punkte`;
      setHTML($('pickTitle'), `🏆 <span class="pick__team" ${teamStyle(s.teams[winner])}>${esc(s.teams[winner].name)}</span> gewinnt das Turnier`);
    } else {
      $('pickEyebrow').textContent = team ? `${team.name} ${why}` : why;
      setHTML($('pickTitle'), team
        ? `<span class="pick__team" ${teamStyle(team)}>${esc(team.name)}</span> wählt das nächste Spiel`
        : 'Welches Spiel kommt als Nächstes?');
    }
    const mb = matchballs(s);

    const grid = $('pickGrid'), n = s.games.length;
    const cols = Math.max(3, Math.ceil(Math.sqrt(n * 1.5))), rows = Math.max(1, Math.ceil(n / cols));
    grid.style.setProperty('--cols', cols);
    grid.style.setProperty('--name-size', `${Math.min(3, 12 / rows).toFixed(2)}vh`);

    // Nach Punkten sortiert (wertvollste zuerst), bei gleichen Punkten in der Reihenfolge der Spielleitung
    const sorted = s.games.map((g, i) => [g, i]).sort((a, b) => b[0].points - a[0].points || a[1] - b[1]).map(([g]) => g);
    grid.classList.toggle('has-mb', mb[0].length + mb[1].length > 0);
    setHTML(grid, sorted.map((g, i) => {
      let cls = `pick-tile is-${g.status} is-p${Math.min(5, Math.max(2, g.points))}`, style = `--i:${i}`, state = '';
      if (g.status === 'running') state = 'läuft';
      if (g.status === 'done') {
        if (g.result === 'draw') {
          cls += ' is-draw';
          style += `;--c1:${esc(s.teams[0].color)};--c2:${esc(s.teams[1].color)}`;
          state = 'Unentschieden';
        } else {
          style += `;--c:${esc(s.teams[g.result].color)}`;
          state = `✓ ${esc(s.teams[g.result].name)}`;
        }
      }
      // Matchball: mit diesem Spiel kann ein Team (oder beide) das Turnier gewinnen
      const mbTeams = [0, 1].filter((t) => mb[t].includes(g.id));
      let mbLabel = '';
      if (mbTeams.length) {
        const c = mbTeams.map((t) => esc(s.teams[t].color));
        cls += mbTeams.length === 2 ? ' is-mb is-mb-both' : ' is-mb';
        style += `;--mb1:${c[0]};--mb2:${c[c.length - 1]}`;
        mbLabel = `<span class="pick-tile__mb">🎯 Matchball${mbTeams.length === 2 ? ' für beide' : ''}</span>`;
      }
      return `<li class="${cls}" style="${style}" data-id="${esc(g.id)}">
        <span class="pick-tile__pts">${g.points}<small>P</small></span>
        <span class="pick-tile__name">${esc(g.name)}</span>
        ${state ? `<span class="pick-tile__state">${state}</span>` : mbLabel}
      </li>`;
    }).join(''));

    // Gerade entschieden → Kachel blitzt einmal in Teamfarbe auf
    s.games.forEach((g) => {
      const before = pickStatus.get(g.id);
      if (before && before !== 'done' && g.status === 'done') {
        const el = grid.querySelector(`[data-id="${CSS.escape(g.id)}"]`);
        if (el) { el.classList.remove('is-fresh'); void el.offsetWidth; el.classList.add('is-fresh'); }
      }
      pickStatus.set(g.id, g.status);
    });

    const open = s.games.filter((g) => g.status !== 'done');
    const left = open.reduce((sum, g) => sum + g.points, 0);
    $('pickFoot').textContent = `${open.length} von ${n} Spielen offen · noch ${left} Punkte zu holen`;
    renderRace(s, mb, open);
  }

  // Beide Balken wachsen zur Mitte, wo die Ziellinie liegt. Gestrichelt: so weit käme das Team mit dem wertvollsten offenen Spiel.
  function renderRace(s, mb, open) {
    const race = $('pickRace'), target = s.target || 40;
    const best = open.reduce((m, g) => Math.max(m, g.points), 0);
    race.style.setProperty('--target', target);
    race.querySelector('.race__goal-label').textContent = `🏁 ${target}`;
    [0, 1].forEach((t) => {
      const c = s.teams[t].color, score = s.scores[t];
      const side = race.querySelector(`.race__side--${t}`), bar = race.querySelector(`.race__bar--${t}`), ghost = race.querySelector(`.race__ghost--${t}`);
      [side, bar, ghost].forEach((el) => el.style.setProperty('--c', c));
      side.querySelector('.race__name').textContent = s.teams[t].name;
      side.querySelector('.race__score').textContent = score;
      bar.style.width = `${Math.min(1, Math.max(0, score) / target) * 50}%`;
      bar.classList.toggle('is-win', score >= target);
      ghost.style.width = `${Math.min(1, Math.max(0, score + best) / target) * 50}%`;
      ghost.hidden = !best || score >= target;
      ghost.classList.toggle('is-mb', mb[t].length > 0);
    });
  }

  // Pro Team: offene Spiele, deren Punkte reichen, um die Siegpunkte zu erreichen (hat schon jemand gewonnen: keine)
  function matchballs(s) {
    const target = s.target || 40;
    if (s.scores.some((x) => x >= target)) return [[], []];
    const open = s.games.filter((g) => g.status !== 'done');
    return [0, 1].map((t) => open.filter((g) => s.scores[t] + g.points >= target).map((g) => g.id));
  }

  // Beim Betreten der Spielauswahl: erst groß "Matchball!", danach leuchten die Spiele, mit denen ein Team gewinnen kann.
  // Läuft gerade die Punkte-Einblendung vom letzten Spiel, wartet der Matchball, bis sie weg ist.
  let mbTimers = [];
  function stopMatchball() {
    mbTimers.forEach(clearTimeout);
    mbTimers = [];
    $('matchball').hidden = true;
  }

  function playMatchball(s) {
    stopMatchball();
    const grid = $('pickGrid'), el = $('matchball'), mb = matchballs(s);
    const teams = [0, 1].filter((t) => mb[t].length);
    if (!teams.length) { grid.classList.add('mb-on'); return; }
    grid.classList.remove('mb-on');

    const target = s.target || 40, c = teams.map((t) => s.teams[t].color);
    el.style.setProperty('--c1', c[0]);
    el.style.setProperty('--c2', c[c.length - 1]);
    setHTML($('matchballText'), teams.length === 2
      ? 'Beide Teams können mit dem nächsten Spiel das Turnier gewinnen'
      : `<b ${teamStyle(s.teams[teams[0]])}>${esc(s.teams[teams[0]].name)}</b> kann mit dem nächsten Spiel das Turnier gewinnen`);
    const count = new Set([...mb[0], ...mb[1]]).size;
    $('matchballSub').textContent = `${count} ${count === 1 ? 'Spiel reicht' : 'Spiele reichen'} dafür · Ziel: ${target} Punkte`;

    const f = s.flash;
    const flashEnd = f ? f.at + (f.kind === 'result' ? 6.6 : 4.6) : 0;
    const wait = Math.max(0.6, flashEnd - now());
    mbTimers.push(setTimeout(() => {
      el.classList.remove('is-out');
      el.hidden = false;
      el.querySelectorAll('p').forEach((p) => { p.style.animation = 'none'; void p.offsetWidth; p.style.animation = ''; });
    }, wait * 1000));
    mbTimers.push(setTimeout(() => el.classList.add('is-out'), (wait + 3.8) * 1000));
    mbTimers.push(setTimeout(() => { el.hidden = true; grid.classList.add('mb-on'); }, (wait + 4.4) * 1000));
  }

  // Bildschirm wird gezeigt → Kacheln fallen nacheinander rein
  function enterPick() {
    const grid = $('pickGrid');
    grid.classList.remove('is-entering', 'is-choosing');
    grid.querySelectorAll('.is-chosen').forEach((el) => el.classList.remove('is-chosen'));
    void grid.offsetWidth;
    grid.classList.add('is-entering');
  }

  // Spiel gewählt → die Kachel feiern, erst danach auf das Spiel umschalten
  function choosePick(id) {
    const grid = $('pickGrid'), tile = grid.querySelector(`[data-id="${CSS.escape(id)}"]`);
    grid.classList.remove('is-entering', 'mb-on');
    grid.classList.add('is-choosing');
    tile?.classList.add('is-chosen');
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
    if (!w) { overlay.hidden = true; wheelId = null; return; }
    if (w.id === wheelId) return;
    wheelId = w.id;
    overlay.hidden = false;
    // Für ein bestimmtes Spiel gedreht: Ergebnis kurz stehen lassen, dann weiter zum Spiel-Bildschirm (mit Aufstellung)
    if (w.game) {
      const endAt = w.at + Math.max(...w.durations) + SLOT_HOLD;
      if (now() >= endAt) { overlay.hidden = true; return; }
      setTimeout(() => { if (wheelId === w.id) overlay.hidden = true; }, (endAt - now()) * 1000);
    }
    overlay.querySelector('.wheel-overlay__title').textContent = w.game ? `🎡 Wer spielt ${w.game}?` : '🎡 Das Glücksrad entscheidet …';

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

  // ---------- Soundeffekte ----------
  // Web Audio statt <audio>: Tonhöhe pro Ding frei wählbar, Töne überlappen sauber, nichts muss "laden", wenn's losgeht.
  // Fehlt eine Datei, läuft alles trotzdem – nur ohne diesen Ton.
  const SFX_URL = { ding: '/assets/lan-night/sounds/ding.mp3', hit: '/assets/lan-night/sounds/hit.mp3', spin: '/assets/lan-night/sounds/spin.mp3' };
  const sfxBuf = {};
  let sfxCtx = null;

  function sfx() {
    if (!sfxCtx) {
      try { sfxCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { return null; }
      Object.entries(SFX_URL).forEach(([k, url]) => {
        fetch(url).then((r) => (r.ok ? r.arrayBuffer() : Promise.reject()))
          .then((b) => sfxCtx.decodeAudioData(b)).then((buf) => { sfxBuf[k] = buf; }).catch(() => {});
      });
    }
    if (sfxCtx.state === 'suspended') sfxCtx.resume().catch(() => {});
    return sfxCtx;
  }

  function playSfx(name, { rate = 1, gain = 1, offset = 0 } = {}) {
    const ctx = sfx(), buf = sfxBuf[name];
    if (!ctx || !buf || offset >= buf.duration) return null;
    const src = ctx.createBufferSource(), g = ctx.createGain();
    src.buffer = buf;
    src.playbackRate.value = rate;   // höher = höherer Ton (und kürzer)
    g.gain.value = gain;
    src.connect(g).connect(ctx.destination);
    src.start(0, Math.max(0, offset));
    return { src, g };
  }

  function fadeSfx(h, seconds = 0.35) {
    if (!h || !sfxCtx) return;
    const t = sfxCtx.currentTime;
    h.g.gain.setValueAtTime(h.g.gain.value, t);
    h.g.gain.linearRampToValueAtTime(0, t + seconds);
    try { h.src.stop(t + seconds + 0.05); } catch (e) { /* schon aus */ }
  }

  // ---------- Spielautomat ----------
  // Pro Team ein Automat, eine Walze pro Spieler. Hebel runter → alle Walzen drehen gleich schnell (kein Abbremsen),
  // dann rastet eine nach der anderen ein (oben → unten) mit Ding + Metall-Schlag + Ruckeln. Jedes Ding einen Ton höher.
  // Spätestens nach SLOT_LAST_MAX Sekunden steht die letzte Walze; das Drehgeräusch läuft vom Hebel bis dahin.
  // Gezogen hat der Server; Start (at) ist Serverzeit → alle Bildschirme laufen gleich.
  const SLOT_LEVER = 0.4, SLOT_FIRST = 1.6, SLOT_LAST_MAX = 6.4, SLOT_STEP_MAX = 0.65;
  const SLOT_SPEED = 26;    // Namen pro Sekunde
  const SLOT_HOLD = 3.5;    // so lange bleibt das Ergebnis stehen, dann geht's auf den Spiel-Bildschirm
  const SCALE = [0, 2, 4, 5, 7, 9, 11, 12, 14, 16, 17, 19];   // Dur-Tonleiter in Halbtönen: Ding 1, 2, 3 …
  // Drehgeräusch: startet etwas höher und lauter, wird mit jedem Ding ein Stück langsamer und leiser (p = Anteil gestoppter Reihen)
  const spinRate = (p) => 1.15 - 0.25 * p;
  const spinGain = (p) => 1.5 - 0.8 * p;
  let slotsId = null, slotsTimer = null, spinSound = null;

  // Zeitpunkt (Sekunden nach at), an dem Walze i einrastet – beide Automaten im Gleichschritt
  function slotTimes(n) {
    const step = n > 1 ? Math.min(SLOT_STEP_MAX, (SLOT_LAST_MAX - SLOT_LEVER - SLOT_FIRST) / (n - 1)) : 0;
    return Array.from({ length: n }, (_, i) => SLOT_LEVER + SLOT_FIRST + i * step);
  }

  function renderSlots(s) {
    const sl = s.slots, overlay = $('slots');
    if (!sl) {
      overlay.hidden = true;
      slotsId = null;
      clearTimeout(slotsTimer);
      fadeSfx(spinSound); spinSound = null;
      return;
    }
    if (sl.id === slotsId) return;   // läuft schon (oder ist schon von selbst ausgeblendet)
    slotsId = sl.id;
    const id = sl.id;

    const n = Math.max(sl.picks[0].length, sl.picks[1].length);
    const times = slotTimes(n);
    const endAt = sl.at + times[n - 1] + SLOT_HOLD;
    clearTimeout(slotsTimer);
    if (now() >= endAt) { overlay.hidden = true; return; }
    overlay.hidden = false;
    slotsTimer = setTimeout(() => { if (slotsId === id) overlay.hidden = true; }, (endAt - now()) * 1000);

    $('slotsTitle').innerHTML = `🎰 Wer spielt ${esc(sl.game)}?<small>${sl.perTeam} pro Team</small>`;
    const machines = [...overlay.querySelectorAll('.slot-machine')].map((m, t) => {
      const team = s.teams[t], names = sl.members[t], picks = sl.picks[t], len = names.length;
      m.style.setProperty('--c', team.color);
      m.style.setProperty('--rh', `${Math.min(9, 56 / n - 1.2).toFixed(2)}vh`);
      m.classList.remove('is-done', 'is-pulling', 'is-rumble');
      m.querySelector('.slot-machine__team').textContent = team.name;

      // Streifen läuft der Reihe nach durch alle Namen; genau beim Einrasten steht der gezogene im Fenster
      const reels = picks.map((pick, i) => {
        const target = Math.round((times[i] - SLOT_LEVER) * SLOT_SPEED);
        const strip = Array.from({ length: target + 2 }, (_, k) => names[(((pick - target + k) % len) + len) % len]);
        return { strip, target, stopAt: sl.at + times[i], i };
      });
      const box = m.querySelector('.slot-machine__reels');
      box.innerHTML = reels.map((r) => `<div class="reel"><div class="reel__strip">${r.strip.map((x) => `<div class="reel__cell">${esc(x)}</div>`).join('')}</div></div>`).join('');
      if (now() - sl.at < SLOT_LEVER) { void m.offsetWidth; m.classList.add('is-pulling'); }
      return { m, t, reels, els: [...box.querySelectorAll('.reel')], h: 0 };
    });

    // Drehgeräusch ab dem Hebel, aus kurz nach der letzten Walze
    const t0 = now() - sl.at;
    fadeSfx(spinSound); spinSound = null;
    if (t0 < times[n - 1]) {
      const startSpin = () => {
        if (slotsId !== id) return;
        const p = times.filter((x) => x <= now() - sl.at).length / n;   // neu geladen mitten im Drehen → passend einsteigen
        spinSound = playSfx('spin', { rate: spinRate(p), gain: spinGain(p), offset: Math.max(0, now() - sl.at - SLOT_LEVER) });
      };
      if (t0 < SLOT_LEVER) setTimeout(startSpin, (SLOT_LEVER - t0) * 1000); else startSpin();
    }

    const dinged = new Set();
    const frame = () => {
      if (slotsId !== id) return;
      const t = now() - sl.at;
      let running = false;
      machines.forEach((mc) => {
        if (!mc.h) mc.h = mc.els[0]?.getBoundingClientRect().height || 0;
        mc.reels.forEach((r, i) => {
          const el = mc.els[i], since = now() - r.stopAt;
          let pos;
          if (t < SLOT_LEVER) pos = 0;
          else if (since < 0) pos = (t - SLOT_LEVER) * SLOT_SPEED;                       // volle Fahrt
          else { const k = Math.min(1, since / 0.28); pos = r.target + 0.35 * Math.sin(k * Math.PI) * (1 - k); }  // rastet ein
          el.firstElementChild.style.transform = `translateY(${-pos * mc.h}px)`;
          el.classList.toggle('is-spinning', t >= SLOT_LEVER && since < 0);
          if (since < 0.3) running = true;
          if (since >= 0 && !el.classList.contains('is-stopped')) {
            el.classList.add('is-stopped');
            if (since < 0.35) {
              mc.m.classList.remove('is-rumble'); void mc.m.offsetWidth; mc.m.classList.add('is-rumble');
              if (!dinged.has(i)) {   // beide Automaten rasten gleichzeitig ein → ein Ding pro Reihe
                dinged.add(i);
                playSfx('ding', { rate: Math.pow(2, SCALE[Math.min(i, SCALE.length - 1)] / 12), gain: 0.9 });
                playSfx('hit', { gain: 0.55 });
                if (spinSound && sfxCtx) {
                  const p = (i + 1) / n, at = sfxCtx.currentTime;
                  spinSound.src.playbackRate.setTargetAtTime(spinRate(p), at, 0.08);
                  spinSound.g.gain.setTargetAtTime(spinGain(p), at, 0.08);
                }
              }
            }
          }
        });
        if (!mc.m.classList.contains('is-done') && mc.reels.every((r) => now() >= r.stopAt)) {
          mc.m.classList.add('is-done');
          if (now() - mc.reels[mc.reels.length - 1].stopAt < 1.5) burst(s.teams[mc.t].color, overlay);
        }
      });
      if (t > times[n - 1] && spinSound) { fadeSfx(spinSound, 0.4); spinSound = null; }
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

  // ---------- Gastauftritt ----------
  // Figur fährt unten hoch (20 % bleiben verdeckt), spricht ihren Satz, die Blase tippt mit – danach wieder runter.
  let cameoSeen = undefined;   // beim ersten Laden keinen alten Auftritt nachholen
  let cameoAudio = null, cameoTimers = [], typeFrame = null, pendingCameo = null;

  function clearCameoTimers() {
    cameoTimers.forEach(clearTimeout);
    cameoTimers = [];
    cancelAnimationFrame(typeFrame);
  }

  function renderCameo(s) {
    const c = s.cameo;
    if (cameoSeen === undefined) { cameoSeen = c?.id ?? null; return; }
    if (!c) {
      if (cameoSeen) leaveCameo(true);
      cameoSeen = null;
      return;
    }
    if (c.id === cameoSeen) return;
    cameoSeen = c.id;
    if (now() - c.at > 15) return;
    const fig = (s.cameoCast || []).find((f) => f.key === c.who);
    if (fig) playCameo(c, fig);
  }

  function playCameo(c, fig) {
    const el = $('cameo'), img = $('cameoImg'), bubble = $('cameoBubble');
    clearCameoTimers();
    if (cameoAudio) { cameoAudio.pause(); cameoAudio = null; }

    // Läuft gerade ein anderer Auftritt: sofort weg, ohne Abgang
    el.classList.remove('is-in', 'is-talking');
    el.hidden = true;
    el.dataset.spot = fig.spot;
    bubble.hidden = true;
    $('cameoTyped').textContent = '';
    $('cameoRest').textContent = c.text;

    const audio = new Audio(`/lan-night/cameo/${encodeURIComponent(c.who)}/${c.line + 1}?v=${fig.v}`);
    audio.preload = 'auto';
    cameoAudio = audio;
    img.src = `/lan-night/cameo/${encodeURIComponent(c.who)}/bild?v=${fig.v}`;

    img.decode().catch(() => {}).then(() => {
      if (cameoAudio !== audio) return;
      el.hidden = false;
      void el.offsetWidth;
      el.classList.add('is-in');
      cameoTimers.push(setTimeout(() => speak(c, audio), 550));
    });
  }

  function speak(c, audio) {
    const el = $('cameo');
    let typed = false;
    const startTyping = (seconds) => {
      if (typed || !c.text) return;
      typed = true;
      $('cameoBubble').hidden = false;
      typeText(c.text, seconds);
    };
    // Ohne Ton (blockiert / Datei fehlt): trotzdem tippen und nach Lesezeit abtreten
    const silent = () => {
      el.classList.remove('is-talking');
      const read = Math.max(3, c.text.length * 0.07);
      startTyping(read * 0.6);
      cameoTimers.push(setTimeout(() => leaveCameo(), (read + 1.5) * 1000));
    };

    audio.onended = () => {
      if (cameoAudio !== audio) return;
      el.classList.remove('is-talking');
      cameoTimers.push(setTimeout(() => leaveCameo(), 1800));
    };
    audio.onerror = () => { if (cameoAudio === audio) silent(); };

    audio.play().then(() => {
      if (cameoAudio !== audio) return;
      soundOk();
      el.classList.add('is-talking');
      const dur = Number.isFinite(audio.duration) && audio.duration > 0 ? audio.duration : 4;
      startTyping(dur * 0.85);
    }).catch((e) => {
      if (cameoAudio !== audio) return;
      if (e?.name === 'NotAllowedError') { pendingCameo = { c, audio }; showSoundHint(); }
      silent();
    });
  }

  // Text erscheint Zeichen für Zeichen über die Sprechdauer verteilt
  function typeText(text, seconds) {
    const typed = $('cameoTyped'), rest = $('cameoRest');
    const perChar = Math.min(0.08, Math.max(0.02, seconds / text.length));
    const start = performance.now();
    const step = (t) => {
      const n = Math.min(text.length, Math.floor((t - start) / 1000 / perChar) + 1);
      typed.textContent = text.slice(0, n);
      rest.textContent = text.slice(n);
      if (n < text.length) typeFrame = requestAnimationFrame(step);
    };
    typeFrame = requestAnimationFrame(step);
  }

  function leaveCameo(instant = false) {
    const el = $('cameo');
    clearCameoTimers();
    if (cameoAudio) { cameoAudio.pause(); cameoAudio = null; }
    pendingCameo = null;
    el.classList.remove('is-in', 'is-talking');
    if (instant) { el.hidden = true; $('cameoBubble').hidden = true; return; }
    cameoTimers.push(setTimeout(() => { el.hidden = true; $('cameoBubble').hidden = true; }, 700));
  }

  // ---------- Ton freischalten ----------
  // Browser spielen ohne einen Klick auf die Seite keinen Ton ab. Beim Laden mit einem stillen Ton testen,
  // damit der Hinweis schon vor dem ersten Auftritt zu sehen ist (Chrome-Kiosk: --autoplay-policy=no-user-gesture-required).
  function silentWav() {
    const n = 800, v = new DataView(new ArrayBuffer(44 + n * 2));
    const w = (o, str) => [...str].forEach((ch, i) => v.setUint8(o + i, ch.charCodeAt(0)));
    w(0, 'RIFF'); v.setUint32(4, 36 + n * 2, true); w(8, 'WAVE'); w(12, 'fmt ');
    v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
    v.setUint32(24, 8000, true); v.setUint32(28, 16000, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true);
    w(36, 'data'); v.setUint32(40, n * 2, true);
    return URL.createObjectURL(new Blob([v.buffer], { type: 'audio/wav' }));
  }
  const showSoundHint = () => { $('soundHint').hidden = false; };
  const soundOk = () => { $('soundHint').hidden = true; };
  function testSound() {
    const a = new Audio(silentWav());
    a.play().then(soundOk).catch((e) => { if (e?.name === 'NotAllowedError') showSoundHint(); });
  }
  function unlockSound() {
    sfx();   // Web Audio (Spielautomat) braucht den Klick ebenfalls
    if ($('soundHint').hidden && !pendingCameo) return;
    soundOk();
    const p = pendingCameo;
    pendingCameo = null;
    if (p && cameoAudio === p.audio) {
      // Auftritt läuft noch stumm: Abgang abblasen, Satz von vorn – abgetreten wird nach dem Ton
      clearCameoTimers();
      $('cameoTyped').textContent = p.c.text;
      $('cameoRest').textContent = '';
      p.audio.currentTime = 0;
      p.audio.play().then(() => $('cameo').classList.add('is-talking')).catch(() => leaveCameo());
    } else {
      testSound();
    }
  }
  document.addEventListener('click', unlockSound);
  document.addEventListener('keydown', unlockSound);
  testSound();
  sfx();   // Soundeffekte schon mal laden

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

    renderQuizTimer(s);
    renderGuessTimer(s);
    renderPriceTimer(s);

    const pill = $('timerPill');
    pill.hidden = !t || !!big || s.screen === 'finale';
    pill.textContent = (t?.label ? t.label + ' · ' : '') + text;
    pill.classList.toggle('is-over', left <= 0);
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

  const PICK_HOLD_MS = 2800;
  let shownScreen = null, pickHold = null, celebrated = null;

  function render(s) {
    renderPick(s);

    // Aus der Spielauswahl heraus gestartet: erst die gewählte Kachel zeigen, dann umschalten
    if (shownScreen === 'pick' && s.screen !== 'pick' && s.currentGame && s.currentGame !== celebrated && !pickHold
        && $('pickGrid').querySelector(`[data-id="${CSS.escape(s.currentGame)}"]`)) {
      celebrated = s.currentGame;
      choosePick(s.currentGame);
      pickHold = setTimeout(() => { pickHold = null; render(state); }, PICK_HOLD_MS);
    }
    if (s.screen === 'pick') { clearTimeout(pickHold); pickHold = null; if (!s.currentGame) celebrated = null; }
    const screen = pickHold ? 'pick' : s.screen;
    if (screen === 'pick' && shownScreen !== 'pick') { enterPick(); playMatchball(s); }
    if (screen !== 'pick' || pickHold) stopMatchball();
    shownScreen = screen;

    document.querySelectorAll('.screen').forEach((el) => el.classList.toggle('is-active', el.dataset.screen === screen));
    $('topbar').classList.toggle('is-hidden', ['title', 'pick', 'finale', 'quiz', 'guess', 'price', 'awards'].includes(screen));

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
    renderSlots(s);
    renderCameo(s);
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
