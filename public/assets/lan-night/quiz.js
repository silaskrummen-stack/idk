(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const LETTERS = ['A', 'B', 'C', 'D'];

  // Team merken (privater Modus: dann eben nur bis zum Neuladen)
  const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return this[k] ?? null; } },
    set(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) { this[k] = v; } },
  };
  let team = store.get('lan_team') === null ? null : Number(store.get('lan_team'));

  // Gerät für die Award-Abstimmung (eine Stimme pro Handy und Kategorie, umentscheiden erlaubt)
  let voter = store.get('lan_voter');
  if (!voter || !/^[a-z0-9]{8,40}$/i.test(voter)) {
    voter = Array.from(crypto.getRandomValues(new Uint8Array(12)), (b) => b.toString(36).padStart(2, '0')).join('').slice(0, 20);
    store.set('lan_voter', voter);
  }
  const ballot = {};          // Kategorie → Spieler (auch bevor der Server antwortet)
  let pending = 0;            // laufende Award-Stimmen – solange gilt die eigene Anzeige

  let state = null;
  let offset = 0;
  let pick = null;      // { index, a } – auf diesem Handy ausgewählt, noch nicht eingeloggt
  let sending = false;
  let note = '';
  let noteTimer = null;
  let lastKey = '';

  const now = () => Date.now() / 1000 + offset;

  function show(id) {
    ['viewTeam', 'viewWait', 'viewQuestion', 'viewAwards', 'viewGuess', 'viewPrice'].forEach((v) => { $(v).hidden = v !== id; });
  }

  function render() {
    const s = state;
    if (!s) return;
    const q = s.quiz;
    $('title').textContent = q.title || s.title;

    const t = team !== null ? s.teams[team] : null;
    $('myTeam').hidden = !t;
    if (t) {
      $('myTeam').textContent = t.name;
      $('myTeam').style.setProperty('--c', t.color);
    }

    // Award-Abstimmung hat Vorrang und braucht kein Team
    const aw = s.awards;
    if (aw.phase === 'voting') { renderAwards(s); return; }
    if (s.screen === 'awards') {
      show('viewWait');
      $('viewWait').classList.add('is-result');
      $('waitTitle').innerHTML = '<span class="result-icon">🏆</span><br>Abstimmung beendet';
      $('waitText').textContent = 'Schau auf den Bildschirm – gleich werden die Awards verliehen!';
      return;
    }

    if (!t) {
      show('viewTeam');
      const html = s.teams.map((tm, i) => `
        <button style="--c:${esc(tm.color)}" data-team="${i}">${esc(tm.name)}
          ${tm.members.length ? `<small>${tm.members.map(esc).join(', ')}</small>` : ''}</button>`).join('');
      if ($('teamPick')._html !== html) { $('teamPick')._html = html; $('teamPick').innerHTML = html; }
      return;
    }

    // Guess the Price, wenn es auf dem Bildschirm ist (oder gerade eine Runde läuft)
    if (s.screen === 'price' || ['item', 'locked'].includes(s.price.phase)) {
      renderPrice(s, t);
      return;
    }

    // Guess the Game, wenn es auf dem Bildschirm ist (oder gerade ein Bild läuft)
    if (s.screen === 'guess' || ['picture', 'shown'].includes(s.guess.phase)) {
      renderGuess(s, t);
      return;
    }

    if (q.phase === 'done') {
      show('viewWait');
      $('viewWait').classList.add('is-result');
      const [a, b] = q.tally;
      const ours = q.tally[team], theirs = q.tally[1 - team];
      $('waitTitle').innerHTML = `<span class="result-icon">${ours > theirs ? '🏆' : ours === theirs ? '🤝' : '💪'}</span><br>${ours > theirs ? 'Ihr habt das Quiz gewonnen!' : ours === theirs ? 'Gleichstand!' : 'Knapp daneben!'}`;
      $('waitText').textContent = `${s.teams[0].name} ${a} : ${b} ${s.teams[1].name}`;
      return;
    }

    if (!q.question) {
      show('viewWait');
      $('viewWait').classList.remove('is-result');
      $('waitTitle').textContent = q.index < 0 ? 'Gleich geht\'s los' : 'Gleich kommt die nächste Frage';
      $('waitText').textContent = 'Die Frage erscheint hier automatisch. Lass die Seite einfach offen.';
      return;
    }

    show('viewQuestion');
    if (pick && pick.index !== q.index) pick = null;

    const open = q.phase === 'question';
    const revealed = q.phase === 'reveal';
    const teamIn = q.locked[team];
    // Nach dem Einloggen beider Teams kennt der Server die Antwort; vorher nur dieses Handy (falls es eingeloggt hat)
    const ours = q.answers[team] ?? (teamIn && pick?.locked ? pick.a : null);
    const canPick = open && !teamIn;
    const key = JSON.stringify([q.index, q.phase, pick, ours, q.correct, team, teamIn]);

    $('qNum').textContent = `Frage ${q.index + 1} / ${q.total}`;
    if (key !== lastKey) {
      lastKey = key;
      $('qText').textContent = q.question.q;
      $('qAnswers').innerHTML = q.question.answers.map((text, i) => {
        const mine = canPick ? pick?.a === i : ours === i;
        const cls = ['qanswer', mine ? 'is-mine' : '', revealed && q.correct === i ? 'is-correct' : ''].join(' ');
        const tag = !canPick && ours === i ? '<span class="qanswer__tag">Euer Team</span>'
          : (revealed && q.correct === i ? '<span class="qanswer__tag">richtig</span>' : '');
        return `<button class="${cls}" style="--a:var(--ans-${i})" data-answer="${i}" ${canPick ? '' : 'disabled'}>
          <span class="qanswer__letter">${LETTERS[i]}</span><span>${esc(text)}</span>${tag}</button>`;
      }).join('');
    }

    const lockBtn = $('qLock');
    lockBtn.hidden = !(canPick && pick);
    lockBtn.disabled = sending;
    if (canPick && pick) lockBtn.textContent = `${LETTERS[pick.a]} für ${t.name} einloggen`;

    let text;
    if (canPick) {
      text = pick ? 'Einloggen gilt fürs ganze Team und ist endgültig.' : 'Tippe auf eine Antwort. Ein Handy pro Team loggt ein – sprecht euch ab!';
    } else if (open) {
      text = ours !== null ? `Eingeloggt: <b>${LETTERS[ours]}</b> – warte auf das andere Team …` : 'Euer Team hat schon eingeloggt – warte auf das andere Team …';
    } else if (!revealed) {
      text = ours === null ? 'Euer Team hat nichts eingeloggt.' : `Eingeloggt: <b>${LETTERS[ours]}</b> – gleich kommt die Auflösung …`;
    } else {
      text = ours === q.correct
        ? '<b>Richtig!</b> 🎉 Ein Schritt näher am Quiz-Sieg.'
        : `Leider falsch – richtig war <b>${LETTERS[q.correct]}</b>.`;
    }
    $('qNote').innerHTML = note || text;
    tick();
  }

  function renderAwards(s) {
    const aw = s.awards;
    show('viewAwards');
    // Stand vom Server übernehmen (z. B. nach Neuladen oder wenn die Spielleitung zurückgesetzt hat)
    if (!pending && aw.myBallot) {
      Object.keys(ballot).forEach((k) => delete ballot[k]);
      Object.assign(ballot, aw.myBallot);
    }
    if (!aw.players.length) {
      $('awList').innerHTML = '<p class="view__text">Noch keine Spieler eingetragen – die Spielleitung muss erst die Teams füllen.</p>';
      $('awNote').textContent = '';
      return;
    }
    const html = aw.categories.map((c, i) => `
      <div class="aw-cat ${ballot[i] ? 'is-done' : ''}">
        <div class="aw-cat__head"><span class="aw-cat__emoji">${esc(c.emoji)}</span>${esc(c.title)}${ballot[i] ? '<span class="aw-cat__check">✓</span>' : ''}</div>
        <div class="aw-players">
          ${aw.players.map((p) => `<button class="aw-player ${ballot[i] === p.name ? 'is-mine' : ''}" style="--c:${esc(s.teams[p.team].color)}" data-cat="${i}" data-player="${esc(p.name)}">${esc(p.name)}</button>`).join('')}
        </div>
      </div>`).join('');
    if ($('awList')._html !== html) { $('awList')._html = html; $('awList').innerHTML = html; }
    const done = aw.categories.filter((c, i) => ballot[i]).length;
    $('awNote').innerHTML = note || (done === aw.categories.length
      ? '<b>Alles abgestimmt!</b> Umentscheiden geht, bis die Abstimmung endet.'
      : `${done} von ${aw.categories.length} abgestimmt – wird sofort gespeichert.`);
  }

  // Award-Stimme: antippen = wählen, nochmal antippen = zurücknehmen
  $('awList').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-player]');
    if (!b) return;
    const cat = Number(b.dataset.cat);
    const player = ballot[cat] === b.dataset.player ? '' : b.dataset.player;
    const before = ballot[cat];
    if (player) ballot[cat] = player; else delete ballot[cat];
    if (navigator.vibrate) navigator.vibrate(20);
    pending++;
    render();
    try {
      const res = await fetch('/lan-night/api/award-vote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ voter, category: cat, player }),
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) {
        if (before) ballot[cat] = before; else delete ballot[cat];
        flashNote(esc(body.error || 'Hat nicht geklappt'));
      } else {
        apply(body);
      }
    } catch (err) {
      if (before) ballot[cat] = before; else delete ballot[cat];
      flashNote('Keine Verbindung – nochmal tippen');
    }
    pending--;
    render();
  });

  // ---------- Guess the Price ----------
  const eur = (v) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
  let myPrice = null;   // { item, value }

  // Gleiche Regeln wie auf dem Server (LanNightPrice::parseAmount) – nur für die Vorschau
  function parseAmount(text) {
    let t = String(text).toLowerCase().trim().replace(/€|euro|eur|\s|\u00a0|'/g, '');
    let factor = 1, m;
    if ((m = t.match(/(mrd\.?|milliarden?|bn)$/))) factor = 1e9;
    else if ((m = t.match(/(mio\.?|millionen?|mill?\.?|m)$/))) factor = 1e6;
    else if ((m = t.match(/(tsd\.?|tausend|k)$/))) factor = 1e3;
    if (factor > 1) t = t.slice(0, t.length - m[1].length);
    if (!/^\d[\d.,]*$/.test(t)) return null;
    const dots = (t.match(/\./g) || []).length, commas = (t.match(/,/g) || []).length;
    const lastDot = t.lastIndexOf('.'), lastComma = t.lastIndexOf(',');
    if (factor > 1 && dots + commas === 1) t = t.replace(',', '.');
    else if (dots && commas) {
      const dec = lastDot > lastComma ? '.' : ',';
      t = t.split(dec === '.' ? ',' : '.').join('').replace(dec, '.');
    } else if (commas) {
      t = commas === 1 && t.length - lastComma - 1 !== 3 ? t.replace(',', '.') : t.split(',').join('');
    } else if (dots) {
      t = dots === 1 && t.length - lastDot - 1 !== 3 ? t : t.split('.').join('');
    }
    const v = Number(t);
    if (!Number.isFinite(v)) return null;
    const out = Math.round(v * factor * 100) / 100;
    return out >= 0 && out < 1e15 ? out : null;
  }

  function renderPrice(s, t) {
    const p = s.price;
    if (p.phase === 'intro' || p.phase === 'done') {
      show('viewWait');
      if (p.phase === 'intro') {
        $('viewWait').classList.remove('is-result');
        $('waitTitle').textContent = 'Guess the Price';
        $('waitText').textContent = 'Gleich kommt der erste Gegenstand. Lass die Seite einfach offen.';
      } else {
        $('viewWait').classList.add('is-result');
        const ours = p.rounds[team], theirs = p.rounds[1 - team];
        $('waitTitle').innerHTML = `<span class="result-icon">${ours > theirs ? '🏆' : ours === theirs ? '🤝' : '💪'}</span><br>${ours > theirs ? 'Ihr habt gewonnen!' : ours === theirs ? 'Gleichstand!' : 'Knapp daneben!'}`;
        $('waitText').textContent = `Runden: ${s.teams[0].name} ${p.rounds[0]} : ${p.rounds[1]} ${s.teams[1].name}`;
      }
      return;
    }

    show('viewPrice');
    $('pNum').textContent = `Gegenstand ${p.item + 1} / ${p.total}`;
    $('pName').textContent = p.name;
    if (p.image && $('pImg').getAttribute('src') !== p.image) $('pImg').setAttribute('src', p.image);
    if (!p.image) $('pImg').removeAttribute('src');

    const mine = myPrice && myPrice.item === p.item ? myPrice.value : null;
    const canType = p.phase === 'item' && !p.locked[team];
    $('pForm').hidden = !canType;
    $('pSend').textContent = `Für ${t.name} tippen`;
    $('pSend').disabled = sending;

    let text;
    if (canType) {
      text = 'Ein Tipp pro Team – sprecht euch ab! Unter dem Feld siehst du, was genau eingeloggt wird.';
    } else if (p.phase === 'item') {
      text = mine !== null ? `Getippt: <b>${eur(mine)}</b> – warte auf das andere Team …` : 'Euer Team hat schon getippt – warte auf das andere Team …';
    } else if (p.phase === 'locked') {
      const ours = p.guesses[team];
      text = ours === null ? 'Kein Tipp abgegeben. Gleich kommt der Preis …' : `Euer Tipp: <b>${eur(ours)}</b> – gleich kommt der Preis …`;
    } else {
      text = `Der Preis: <b>${eur(p.price)}</b><br>${p.winner[team] ? 'Ihr wart näher dran – Runde für euch! 🎉' : 'Diesmal war das andere Team näher dran.'}`;
    }
    $('pNote').innerHTML = note || text;
    tick();
  }

  // Live-Vorschau: was wird eingeloggt?
  $('pForm').text.addEventListener('input', (e) => {
    const raw = e.target.value.trim();
    const v = parseAmount(raw);
    const el = $('pPreview');
    el.classList.toggle('is-bad', raw !== '' && v === null);
    el.textContent = raw === '' ? '' : (v === null ? 'Das versteht der Server nicht – z. B. „1.250“, „430 Mio“ oder „7,4 Mrd“' : `= ${eur(v)}`);
  });

  $('pForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const p = state?.price;
    const input = e.target.text;
    const value = parseAmount(input.value);
    if (!p || value === null || sending || p.phase !== 'item') return;
    sending = true;
    render();
    try {
      const res = await fetch('/lan-night/api/price', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ team, text: input.value, item: p.item }),
      });
      const body = await res.json().catch(() => ({}));
      if (res.ok) {
        myPrice = { item: p.item, value };
        input.value = '';
        $('pPreview').textContent = '';
        input.blur();
        if (navigator.vibrate) navigator.vibrate([40, 60, 40]);
        apply(body);
      } else {
        flashNote(esc(body.error || 'Hat nicht geklappt'));
      }
    } catch (err) {
      flashNote('Keine Verbindung – nochmal versuchen');
    }
    sending = false;
    render();
  });

  // ---------- Guess the Game ----------
  let myGuess = null;   // { game, picture, text } – was dieses Handy getippt hat

  function renderGuess(s, t) {
    const g = s.guess;
    if (g.phase === 'intro' || g.phase === 'done') {
      show('viewWait');
      if (g.phase === 'intro') {
        $('viewWait').classList.remove('is-result');
        $('waitTitle').textContent = 'Guess the Game';
        $('waitText').textContent = 'Gleich kommt das erste Bild. Lass die Seite einfach offen.';
      } else {
        $('viewWait').classList.add('is-result');
        const ours = g.score[team], theirs = g.score[1 - team];
        $('waitTitle').innerHTML = `<span class="result-icon">${ours > theirs ? '🏆' : ours === theirs ? '🤝' : '💪'}</span><br>${ours > theirs ? 'Ihr habt gewonnen!' : ours === theirs ? 'Gleichstand!' : 'Knapp daneben!'}`;
        $('waitText').textContent = `Ratepunkte: ${s.teams[0].name} ${g.score[0]} : ${g.score[1]} ${s.teams[1].name}`;
      }
      return;
    }

    show('viewGuess');
    $('gNum').textContent = `Spiel ${g.game + 1} / ${g.total} · Bild ${g.picture + 1} / ${g.pictures}`;
    if (g.image && $('gImg').getAttribute('src') !== g.image) $('gImg').setAttribute('src', g.image);

    const mine = myGuess && myGuess.game === g.game && myGuess.picture === g.picture ? myGuess.text : null;
    const canType = g.phase === 'picture' && !g.locked[team];
    $('gForm').hidden = !canType;
    $('gSend').textContent = `Für ${t.name} tippen`;
    $('gSend').disabled = sending;

    let text;
    if (canType) {
      text = 'Ein Tipp pro Team und Bild – sprecht euch ab! Tippfehler sind nicht schlimm.';
    } else if (g.phase === 'picture') {
      text = mine ? `Getippt: <b>„${esc(mine)}“</b> – warte auf das andere Team …` : 'Euer Team hat schon getippt – warte auf das andere Team …';
    } else if (g.phase === 'shown') {
      const ours = g.guesses[team];
      text = !ours ? 'Kein Tipp abgegeben.' : (ours.correct ? `<b>„${esc(ours.text)}“ – richtig!</b> 🎉` : `„${esc(ours.text)}“ ist leider falsch.`);
    } else {
      const hit = g.solvedBy[team];
      text = `Lösung: <b>${esc(g.answer)}</b><br>${hit ? `Ihr habt's auf Bild ${hit} erraten – +${6 - hit} Ratepunkte!` : 'Diesmal nicht erraten.'}`;
    }
    $('gNote').innerHTML = note || text;
    tick();
  }

  $('gForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const g = state?.guess;
    const input = e.target.text;
    const text = input.value.trim();
    if (!g || !text || sending || g.phase !== 'picture') return;
    sending = true;
    render();
    try {
      const res = await fetch('/lan-night/api/guess', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ team, text, game: g.game, picture: g.picture }),
      });
      const body = await res.json().catch(() => ({}));
      if (res.ok) {
        myGuess = { game: g.game, picture: g.picture, text };
        input.value = '';
        input.blur();
        if (navigator.vibrate) navigator.vibrate([40, 60, 40]);
        apply(body);
      } else {
        flashNote(esc(body.error || 'Hat nicht geklappt'));
      }
    } catch (err) {
      flashNote('Keine Verbindung – nochmal versuchen');
    }
    sending = false;
    render();
  });

  function tick() {
    const pr = state?.price;
    if (pr && pr.phase === 'item' && !$('viewPrice').hidden) {
      const left = Math.max(0, pr.endsAt - now());
      $('pTimer').textContent = Math.ceil(left);
      $('pTimer').classList.toggle('is-low', left <= 5);
      $('pBar').style.transform = `scaleX(${Math.min(1, left / pr.seconds)})`;
    } else {
      $('pTimer').textContent = '';
      $('pBar').style.transform = 'scaleX(0)';
    }

    const g = state?.guess;
    if (g && g.phase === 'picture' && !$('viewGuess').hidden) {
      const left = Math.max(0, g.endsAt - now());
      $('gTimer').textContent = Math.ceil(left);
      $('gTimer').classList.toggle('is-low', left <= 5);
      $('gBar').style.transform = `scaleX(${Math.min(1, left / g.seconds)})`;
    } else {
      $('gTimer').textContent = '';
      $('gBar').style.transform = 'scaleX(0)';
    }

    const q = state?.quiz;
    if (!q || q.phase !== 'question' || $('viewQuestion').hidden) {
      $('qTimer').textContent = '';
      $('qBar').style.transform = 'scaleX(0)';
      return;
    }
    const left = Math.max(0, q.endsAt - now());
    $('qTimer').textContent = Math.ceil(left);
    $('qTimer').classList.toggle('is-low', left <= 5);
    $('qBar').style.transform = `scaleX(${Math.min(1, left / q.seconds)})`;
  }

  function flashNote(msg) {
    note = msg;
    clearTimeout(noteTimer);
    noteTimer = setTimeout(() => { note = ''; render(); }, 3500);
  }

  // ---------- Eingaben ----------
  $('teamPick').addEventListener('click', (e) => {
    const b = e.target.closest('[data-team]');
    if (!b) return;
    team = Number(b.dataset.team);
    store.set('lan_team', String(team));
    pick = null;
    lastKey = '';
    render();
  });

  $('myTeam').addEventListener('click', () => {
    if (!confirm('Team wechseln?')) return;
    team = null;
    store.set('lan_team', null);
    pick = null;
    lastKey = '';
    render();
  });

  // Antwort antippen = nur auswählen
  $('qAnswers').addEventListener('click', (e) => {
    const b = e.target.closest('[data-answer]');
    const q = state?.quiz;
    if (!b || b.disabled || !q || q.phase !== 'question') return;
    pick = { index: q.index, a: Number(b.dataset.answer) };
    if (navigator.vibrate) navigator.vibrate(20);
    render();
  });

  // Einloggen = endgültig fürs Team
  $('qLock').addEventListener('click', async () => {
    const q = state?.quiz;
    if (!pick || !q || sending) return;
    sending = true;
    render();
    try {
      const res = await fetch('/lan-night/api/vote', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ team, answer: pick.a, index: pick.index }),
      });
      const body = await res.json().catch(() => ({}));
      if (res.ok) {
        pick.locked = true;
        if (navigator.vibrate) navigator.vibrate([40, 60, 40]);
        apply(body);
      } else {
        flashNote(esc(body.error || 'Hat nicht geklappt'));
      }
    } catch (err) {
      flashNote('Keine Verbindung – nochmal versuchen');
    }
    sending = false;
    lastKey = '';
    render();
  });

  // ---------- Abfrage ----------
  function apply(s) {
    offset = s.serverTime - Date.now() / 1000;
    state = s;
  }

  async function poll() {
    try {
      const res = await fetch('/lan-night/api/state?voter=' + encodeURIComponent(voter), { cache: 'no-store' });
      if (res.ok) { apply(await res.json()); render(); }
    } catch (e) { /* nächster Versuch */ }
    const live = ['question', 'locked'].includes(state?.quiz?.phase) || ['picture', 'shown'].includes(state?.guess?.phase)
      || ['item', 'locked'].includes(state?.price?.phase);
    setTimeout(poll, live ? 500 : 1000);
  }

  setInterval(tick, 200);
  poll();
})();
