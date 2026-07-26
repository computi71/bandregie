// Passwort-Stärke-Anzeige: Balken + Label unter jedem input[data-strength]
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[data-strength]').forEach((input) => {
    const labels = (input.dataset.labels || 'schwach|mittel|stark|sehr stark').split('|');
    const wrap = document.createElement('div');
    wrap.style.cssText = 'margin-top:0.3rem';
    wrap.innerHTML = '<div style="height:6px;border-radius:3px;background:rgba(255,255,255,0.15);overflow:hidden">'
      + '<div class="pw-bar" style="height:100%;width:0;border-radius:3px;transition:all .25s"></div></div>'
      + '<span class="pw-label" style="font-size:0.78rem;opacity:0.8"></span>';
    input.after(wrap);
    const bar = wrap.querySelector('.pw-bar');
    const label = wrap.querySelector('.pw-label');
    const colors = ['#e05656', '#e0a856', '#a8d060', '#4ec06e'];
    input.addEventListener('input', () => {
      const v = input.value;
      let score = 0;
      if (v.length >= 8) score++;
      if (v.length >= 12) score++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
      if (/\d/.test(v)) score++;
      if (/[^A-Za-z0-9]/.test(v)) score++;
      const step = v.length === 0 ? -1 : Math.min(3, Math.max(0, score - 1));
      bar.style.width = step < 0 ? '0' : ((step + 1) * 25) + '%';
      bar.style.background = step < 0 ? 'transparent' : colors[step];
      label.textContent = step < 0 ? '' : labels[step];
    });
  });
});
