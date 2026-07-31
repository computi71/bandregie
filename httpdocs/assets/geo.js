// Adress-Suche (Geocoding): der Knopf fragt den EIGENEN Endpunkt (der wiederum
// serverseitig OpenStreetMap fragt — die CSP lässt keinen Fremd-Abruf zu), zeigt
// ein paar Treffer, und ein Klick übernimmt die Koordinaten ins Formular. Nur
// aktiv, wenn der Knopf nicht ausgegraut ist; freigeschaltet wird er über den
// Schalter in den Einstellungen.
(function () {
  document.querySelectorAll('.geo-field').forEach((field) => {
    const btn = field.querySelector('button[data-geosearch]');
    const results = field.querySelector('.geo-results');
    const form = field.closest('form');
    if (!btn || !results || !form || btn.disabled) return;

    const val = (name) => {
      const el = form.querySelector('[name="' + name + '"]');
      return el ? el.value.trim() : '';
    };

    btn.addEventListener('click', async () => {
      const q = [val('name'), val('address'), val('city')].filter(Boolean).join(', ');
      if (q.length < 3) return;
      results.textContent = field.dataset.tSearching || '…';
      let list = [];
      try {
        const r = await fetch(field.dataset.geoEndpoint + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
        const data = await r.json();
        list = (data && data.results) || [];
      } catch (e) { list = []; }
      results.textContent = '';
      if (!list.length) { results.textContent = field.dataset.tNone || ''; return; }
      list.forEach((hit) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-ghost btn-small geo-hit';
        b.textContent = hit.name;
        b.addEventListener('click', () => {
          // Der gewählte Treffer füllt Adresse, Stadt und Koordinaten — so wird
          // aus einem bloßen Namen ein vollständiger Ort.
          const set = (name, v) => {
            const el = form.querySelector('[name="' + name + '"]');
            if (el && v) el.value = v;
          };
          set('address', hit.address);
          set('city', hit.city);
          set('lat', hit.lat);
          set('lng', hit.lng);
          results.textContent = '✓ ' + hit.name;
        });
        results.appendChild(b);
      });
    });
  });
})();
