// Zwei-Klick-Lösung (§ 25 TDDDG): externe Inhalte erst nach Einwilligung laden
(function () {
  function load(box) {
    var iframe = document.createElement('iframe');
    iframe.src = box.dataset.embed;
    iframe.title = box.dataset.title;
    iframe.loading = 'lazy';
    iframe.setAttribute('allowfullscreen', '');
    if (box.dataset.provider === 'spotify') iframe.setAttribute('allow', 'encrypted-media');
    box.innerHTML = '';
    box.appendChild(iframe);
    box.classList.add('embed-loaded');
  }
  document.querySelectorAll('.embed-consent').forEach(function (box) {
    var key = 'embed-consent-' + box.dataset.provider;
    if (localStorage.getItem(key) === 'yes') { load(box); return; }
    var btn = box.querySelector('.embed-load');
    if (btn) btn.addEventListener('click', function () {
      var remember = box.querySelector('.embed-remember');
      if (remember && remember.checked) {
        localStorage.setItem(key, 'yes');
        document.querySelectorAll('.embed-consent[data-provider="' + box.dataset.provider + '"]').forEach(load);
      } else {
        load(box);
      }
    });
  });
})();
