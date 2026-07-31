// Bühnenansicht: der Liedtext läuft von selbst, das Handy ist der Notenständer.
// Zwei Dinge sind hier nicht verhandelbar: Es muss ohne Netz gehen (auf der
// Bühne gibt es keins), und der Bildschirm muss wach bleiben — ein Display, das
// im zweiten Refrain einschläft, macht die ganze Sache wertlos.
(function () {
  const root = document.getElementById('buehne');
  if (!root) return;

  const songs = JSON.parse(root.dataset.songs || '[]');
  const stage = root.querySelector('.buehne-scroll');
  const titleEl = root.querySelector('.buehne-title');
  const posEl = root.querySelector('.buehne-pos');
  const speedEl = root.querySelector('.buehne-speed');
  const playBtn = root.querySelector('.buehne-play');
  const musSel = root.querySelector('.buehne-musician');
  const tempoPop = root.querySelector('.buehne-tempo');
  const bpmInput = root.querySelector('.buehne-bpm');
  const isMono = root.classList.contains('is-mono');

  let index = songs.findIndex((s) => s.id === Number(root.dataset.start));
  if (index < 0) index = 0;

  // Tempo als BPM — die Zahl, die die Band ohnehin im Kopf hat. Pro Lied aus dem
  // gespeicherten Tempo vorbelegt (song.bpm); daraus wird die Scroll-
  // Geschwindigkeit gerechnet. Mitten im Lied erreichbar, keine Einstellung für
  // zwischendurch.
  const DEFAULT_BPM = 100;
  const PX_PER_BPM = 0.25; // grobe Kopplung: 120 BPM ≈ 30 px/s, live feinjustierbar
  let bpm = DEFAULT_BPM;
  let running = false;
  let last = 0;
  let carry = 0; // Rest unter einem ganzen Pixel, damit langsames Scrollen gleichmäßig bleibt
  let wakeLock = null;

  function escapeHtml(s) {
    return s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  function renderLines(lines) {
    if (!lines || !lines.length) {
      stage.innerHTML = '<p class="buehne-empty">' + escapeHtml(root.dataset.empty || '') + '</p>';
    } else {
      let html = '';
      for (const line of lines) {
        if (line.part !== undefined) {
          html += '<p class="buehne-part part-' + line.cat + '">' + escapeHtml(line.part) + '</p>';
        } else if (line.text.trim() === '') {
          html += '<p class="buehne-gap"></p>';
        } else {
          html += '<p class="buehne-line">' + escapeHtml(line.text) + '</p>';
        }
      }
      stage.innerHTML = html;
    }
    stage.scrollTop = 0;
    carry = 0;
  }

  // Noten-Modus: die Musiker mit Noten ins Dropdown, den eigenen vorwählen. Wer
  // selbst nichts hinterlegt hat, sieht erst etwas, wenn er aktiv einen Kollegen
  // wählt; Musiker ohne Noten erscheinen gar nicht.
  function renderMusicians(song) {
    const ms = song.musicians || [];
    if (!ms.length) { if (musSel) musSel.style.display = 'none'; renderLines([]); return; }
    if (musSel) musSel.style.display = '';
    const def = ms.findIndex((m) => m.me);
    let opts = ms.map((m, i) => '<option value="' + i + '">' + escapeHtml(m.name) + '</option>').join('');
    if (def < 0) opts = '<option value="-1">—</option>' + opts;
    if (musSel) { musSel.innerHTML = opts; musSel.value = String(def); }
    renderLines(def >= 0 ? ms[def].lines : []);
  }

  function render() {
    const song = songs[index];
    titleEl.textContent = song.title;
    posEl.textContent = songs.length > 1 ? index + 1 + ' / ' + songs.length : '';
    bpm = song.bpm || DEFAULT_BPM; // pro Lied das gespeicherte Tempo, sonst zurück auf Standard
    if (isMono) renderMusicians(song);
    else renderLines(song.lines);
    showSpeed();
  }

  function frame(now) {
    if (running && last) {
      carry += (bpm * PX_PER_BPM * (now - last)) / 1000;
      const step = Math.floor(carry);
      if (step >= 1) {
        stage.scrollTop += step;
        carry -= step;
        // Am Ende angekommen: stehen bleiben, nicht heimlich weiterlaufen.
        if (stage.scrollTop + stage.clientHeight >= stage.scrollHeight - 1) setRunning(false);
      }
    }
    last = now;
    requestAnimationFrame(frame);
  }

  async function acquireWake() {
    try {
      if ('wakeLock' in navigator) wakeLock = await navigator.wakeLock.request('screen');
    } catch (e) {
      /* Kein Drama, wenn das Gerät es nicht kann — der Rest funktioniert trotzdem. */
    }
  }

  // Der Sperr-Wunsch wird beim Wegblenden vom System aufgehoben; sichtbar und
  // laufend fordern wir ihn neu an.
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && running) acquireWake();
  });

  function setRunning(on) {
    running = on;
    last = 0;
    root.classList.toggle('is-running', on);
    if (playBtn) playBtn.textContent = on ? '⏸' : '▶';
    if (on) {
      acquireWake();
      // Vollbild nur auf ausdrücklichen Wunsch, und nur wenn der Browser es
      // zulässt — beides braucht die Geste, in der wir gerade stecken.
      if (document.documentElement.requestFullscreen && !document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
      }
    } else if (wakeLock) {
      wakeLock.release().catch(() => {});
      wakeLock = null;
    }
  }

  function toggle() { setRunning(!running); }
  function showSpeed() {
    const t = Math.round(bpm);
    if (speedEl) speedEl.textContent = t + ' BPM';
    // Das Eingabefeld nur nachziehen, wenn gerade niemand darin tippt.
    if (bpmInput && document.activeElement !== bpmInput) bpmInput.value = t;
  }
  function faster() { bpm = Math.min(260, bpm + 2); showSpeed(); }
  function slower() { bpm = Math.max(30, bpm - 2); showSpeed(); }

  // Tempo-Popup: öffnen, schließen, und die getippte Zahl übernehmen.
  function openTempo() { if (tempoPop) { tempoPop.hidden = false; showSpeed(); } }
  function closeTempo() { if (tempoPop) tempoPop.hidden = true; }

  // Tempo eintippen: die Abstände zwischen den Taps mitteln. Eine lange Pause
  // (über 2 s) beginnt eine neue Zählung.
  let tapTimes = [];
  function tap() {
    const now = performance.now();
    if (tapTimes.length && now - tapTimes[tapTimes.length - 1] > 2000) tapTimes = [];
    tapTimes.push(now);
    if (tapTimes.length > 6) tapTimes.shift();
    if (tapTimes.length >= 2) {
      const b = Math.round(60000 / ((tapTimes[tapTimes.length - 1] - tapTimes[0]) / (tapTimes.length - 1)));
      if (b >= 30 && b <= 260) { bpm = b; showSpeed(); }
    }
  }

  function go(delta) {
    const next = index + delta;
    if (next < 0 || next >= songs.length) return;
    index = next;
    render();
  }

  // Tap auf die Textfläche: Pause/Weiter. Ein langes Lied ist kein Vortrag.
  stage.addEventListener('click', toggle);

  // Bluetooth-Umblätterer und Fußpedale schicken Pfeil- und Bild-Tasten; wer
  // darauf reagiert, unterstützt sie ohne weiteren Code.
  document.addEventListener('keydown', (e) => {
    switch (e.key) {
      case ' ': case 'Enter': e.preventDefault(); toggle(); break;
      case 'ArrowUp': case 'PageUp': e.preventDefault(); stage.scrollTop -= 60; break;
      case 'ArrowDown': case 'PageDown': e.preventDefault(); stage.scrollTop += 60; break;
      case 'ArrowRight': e.preventDefault(); go(1); break;
      case 'ArrowLeft': e.preventDefault(); go(-1); break;
      case '+': faster(); break;
      case '-': slower(); break;
    }
  });

  root.querySelectorAll('[data-act]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const act = btn.dataset.act;
      if (act === 'play') toggle();
      else if (act === 'faster') faster();
      else if (act === 'slower') slower();
      else if (act === 'tap') tap();
      else if (act === 'next') go(1);
      else if (act === 'prev') go(-1);
      else if (act === 'tempo') openTempo();
      else if (act === 'tempo-close') closeTempo();
    });
  });

  if (tempoPop && bpmInput) {
    // Hintergrund (außerhalb der Karte) antippen schließt das Popup.
    tempoPop.addEventListener('click', (e) => { if (e.target === tempoPop) closeTempo(); });
    // Getippte Zahl sofort übernehmen (im gültigen Bereich), beim Verlassen
    // fehlende/außerhalb liegende Werte auf die Grenzen ziehen.
    bpmInput.addEventListener('input', () => {
      const v = parseInt(bpmInput.value, 10);
      if (!isNaN(v) && v >= 30 && v <= 260) { bpm = v; if (speedEl) speedEl.textContent = v + ' BPM'; }
    });
    bpmInput.addEventListener('change', () => {
      let v = parseInt(bpmInput.value, 10);
      if (isNaN(v)) v = Math.round(bpm);
      bpm = Math.max(30, Math.min(260, v));
      showSpeed();
    });
  }

  if (musSel) {
    musSel.addEventListener('change', () => {
      const song = songs[index];
      const i = Number(musSel.value);
      renderLines(i >= 0 && song.musicians ? song.musicians[i].lines : []);
    });
  }

  render();
  showSpeed();
  requestAnimationFrame(frame);
})();
