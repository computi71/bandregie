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
      .then((sub) => { if (!sub) hinweis.hidden = false; })
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
  const show = (sub) => {
    if (enableBtn) enableBtn.hidden = !!sub;
    if (disableBtn) disableBtn.hidden = !sub;
  };

  navigator.serviceWorker.ready
    .then((reg) => reg.pushManager.getSubscription())
    .then(show)
    .catch(() => {});

  if (enableBtn) enableBtn.addEventListener('click', async () => {
    try {
      if (await Notification.requestPermission() !== 'granted') {
        if (deniedMsg) deniedMsg.hidden = false;
        return;
      }
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToBytes(key) });
      const j = sub.toJSON();
      await post('/intern/push/subscribe', { endpoint: sub.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth });
      show(sub);
    } catch (e) {
      if (deniedMsg) deniedMsg.hidden = false;
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
