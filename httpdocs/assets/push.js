// Push-Mitteilungen im Profil (#24): je Gerät aktivieren oder abschalten.
//
// Ohne Browser-Unterstützung bleibt der Geräte-Teil unsichtbar (stiller
// Rückfall) — die Themen-Auswahl gilt kontoweit und bleibt bedienbar. Am
// iPhone gibt es Push nur für die installierte Home-Screen-App; solange die
// Seite dort im Safari läuft, erscheint stattdessen der Hinweis.
(function () {
  const box = document.querySelector('[data-push]');
  const supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  const ios = /iPhone|iPad|iPod/.test(navigator.userAgent || '');
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

  // Hinweis auf der Startseite: Dass auf DIESEM Gerät keine Mitteilungen
  // ankommen, sieht man der App sonst nicht an — sie schweigt einfach, und man
  // hält es für Ruhe statt für einen abgeschalteten Schalter. Ob ein Abo
  // besteht, weiß nur der Browser; der Server kennt nur die Abos, die einmal
  // angelegt wurden, und ein abgeschaltetes Gerät meldet sich nicht ab.
  const hinweis = document.querySelector('[data-push-hint]');
  if (hinweis && supported) {
    navigator.serviceWorker.ready
      .then((reg) => reg.pushManager.getSubscription())
      .then((sub) => {
        if (!sub) { hinweis.hidden = false; return; }
        // Lebenszeichen: Sagt dem Server, dass es dieses Abo noch gibt. Ohne
        // das kann er ein totes nicht von einem stillen unterscheiden — der
        // Zustelldienst nimmt beide an. Einmal am Tag genügt dafür bei Weitem.
        const heute = new Date().toISOString().slice(0, 10);
        try {
          if (localStorage.getItem('bandregie-push-seen') === heute) return;
          localStorage.setItem('bandregie-push-seen', heute);
        } catch (e) { /* ohne Gedächtnis eben jedes Mal */ }
        const fd = new FormData();
        fd.append('_token', hinweis.dataset.token || '');
        fd.append('endpoint', sub.endpoint);
        fetch('/intern/push/seen', { method: 'POST', body: fd, credentials: 'same-origin' })
          .catch(() => {});
      })
      .catch(() => {});
  }

  if (!box) return;
  if (!supported) {
    if (ios && !standalone) {
      const p = box.querySelector('[data-push-ios]');
      if (p) p.hidden = false;
    }
    return; // still: kein Knopf, der nichts tut
  }

  const enableBtn = box.querySelector('[data-push-enable]');
  const disableBtn = box.querySelector('[data-push-disable]');
  const deniedMsg = box.querySelector('[data-push-denied]');
  const offenMsg = box.querySelector('[data-push-open]');
  const fehlerMsg = box.querySelector('[data-push-failed]');
  const key = box.dataset.pushKey;
  const token = box.dataset.pushToken;

  const b64ToBytes = (s) => {
    const raw = atob(s.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
  };
  const post = (url, fields) => {
    const fd = new FormData();
    fd.append('_token', token);
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' });
  };
  // Hat der Browser die Mitteilungen für diese Seite blockiert, fragt er nie
  // wieder — ein Klick auf „aktivieren" läuft dann ins Leere. Also den Knopf
  // gar nicht erst anbieten und stattdessen sagen, woran es liegt: Freigeben
  // geht nur in den Browsereinstellungen, nicht von hier aus.
  const blockiert = () => Notification.permission === 'denied';
  const show = (sub) => {
    if (enableBtn) enableBtn.hidden = !!sub || blockiert();
    if (disableBtn) disableBtn.hidden = !sub;
    if (deniedMsg && blockiert()) deniedMsg.hidden = false;
  };

  navigator.serviceWorker.ready
    .then((reg) => reg.pushManager.getSubscription())
    .then(show)
    .catch(() => {});

  // Drei Gründe, aus denen es nicht klappt, und drei verschiedene Auswege:
  //
  //  * blockiert — der Browser fragt nie wieder, Freigabe nur in seinen
  //    Einstellungen.
  //  * nicht beantwortet — Chrome und Edge zeigen die Frage seit einiger Zeit
  //    oft nicht mehr als Fenster, sondern nur als kleines Glockensymbol in der
  //    Adressleiste. Wer das nicht bemerkt, hat nie „blockieren" geklickt und
  //    steht trotzdem ohne Erlaubnis da. Genau dieser Fall wurde bisher als
  //    „blockiert" gemeldet, was schlicht nicht stimmte.
  //  * technisch gescheitert — Abo ließ sich nicht anlegen.
  const meldung = (el) => {
    [deniedMsg, offenMsg, fehlerMsg].forEach((m) => { if (m) m.hidden = true; });
    if (el) el.hidden = false;
  };
  if (enableBtn) enableBtn.addEventListener('click', async () => {
    try {
      const antwort = await Notification.requestPermission();
      if (antwort === 'denied') { meldung(deniedMsg); show(null); return; }
      if (antwort !== 'granted') { meldung(offenMsg); return; }
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(key) });
      const j = sub.toJSON();
      await post('/intern/push/subscribe', { endpoint: sub.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth });
      meldung(null);
      show(sub);
    } catch (e) {
      meldung(fehlerMsg);
    }
  });

  if (disableBtn) disableBtn.addEventListener('click', async () => {
    try {
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.getSubscription();
      if (sub) {
        await post('/intern/push/unsubscribe', { endpoint: sub.endpoint });
        await sub.unsubscribe();
      }
      show(null);
    } catch (e) { /* dann eben beim nächsten Versuch */ }
  });
})();
