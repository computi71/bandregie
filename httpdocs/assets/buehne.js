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

  function render() {
    const song = songs[index];
    titleEl.textContent = song.title;
    posEl.textContent = songs.length > 1 ? index + 1 + ' / ' + songs.length : '';
    if (song.bpm) bpm = song.bpm; // pro Lied das gespeicherte Tempo übernehmen
    if (!song.lines.length) {
      stage.innerHTML = '<p class="buehne-empty">' + escapeHtml(root.dataset.empty || '') + '</p>';
    } else {
      let html = '';
      for (const line of song.lines) {
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
  function showSpeed() { if (speedEl) speedEl.textContent = Math.round(bpm) + ' BPM'; }
  function faster() { bpm = Math.min(260, bpm + 2); showSpeed(); }
  function slower() { bpm = Math.max(30, bpm - 2); showSpeed(); }

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
      else if (act === 'next') go(1);
      else if (act === 'prev') go(-1);
    });
  });

  render();
  showSpeed();
  requestAnimationFrame(frame);
})();
