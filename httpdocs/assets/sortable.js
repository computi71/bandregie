// Setlist per Ziehen umsortieren. Die Pfeiltasten bleiben bestehen — sie sind
// die Rückfallebene für Touchgeräte, auf denen HTML5-Drag-and-drop nicht greift.
document.addEventListener('DOMContentLoaded', () => {
  const list = document.querySelector('ol.sortable');
  if (!list) return;

  const url = list.dataset.reorder;
  const token = list.dataset.token;
  let dragged = null;

  const flash = (text, ok = true) => {
    let note = document.getElementById('sort-note');
    if (!note) {
      note = document.createElement('div');
      note.id = 'sort-note';
      note.className = 'sort-note';
      list.parentNode.insertBefore(note, list.nextSibling);
    }
    note.textContent = text;
    note.classList.toggle('sort-note-error', !ok);
    note.style.opacity = '1';
    clearTimeout(note.timer);
    note.timer = setTimeout(() => { note.style.opacity = '0'; }, 2500);
  };

  const renumber = () => {
    [...list.children].forEach((li, i) => {
      const pos = li.querySelector('.pos');
      if (pos) pos.textContent = i + 1;
    });
  };

  const save = () => {
    const body = new FormData();
    body.append('_token', token);
    [...list.children].forEach(li => body.append('order[]', li.dataset.item));
    fetch(url, { method: 'POST', body, credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(() => flash(list.dataset.savedText || '✔'))
      .catch(() => flash('✕', false));
  };

  list.addEventListener('dragstart', e => {
    const li = e.target.closest('li');
    if (!li) return;
    dragged = li;
    li.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    // Firefox startet den Zieh-Vorgang nur mit gesetzten Daten
    e.dataTransfer.setData('text/plain', li.dataset.item);
  });

  list.addEventListener('dragover', e => {
    if (!dragged) return;
    e.preventDefault();
    const li = e.target.closest('li');
    if (!li || li === dragged) return;
    const box = li.getBoundingClientRect();
    const after = e.clientY > box.top + box.height / 2;
    list.insertBefore(dragged, after ? li.nextSibling : li);
  });

  list.addEventListener('dragend', () => {
    if (!dragged) return;
    dragged.classList.remove('dragging');
    dragged = null;
    renumber();
    save();
  });
});
