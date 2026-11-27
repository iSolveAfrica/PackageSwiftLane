(function () {
  'use strict';
  var dock = document.querySelector('.sl-shortcuts[data-shortcuts-user]');
  if (!dock) return;
  document.querySelectorAll('.sl-rail [aria-label]').forEach(function (item) { item.dataset.slTip = item.getAttribute('aria-label'); item.removeAttribute('title'); });
  var lists = [dock.querySelector('.sl-shortcuts-list'), document.querySelector('.sl-mobile-shortcuts .sl-shortcuts-list')].filter(Boolean);
  var links = Array.from(document.querySelectorAll('.sl-nav-group .sl-nav a[data-shortcut-id]'));
  var byId = new Map(links.map(function (link) { return [link.dataset.shortcutId, link]; }));
  var key = 'sl.shortcuts.' + dock.dataset.shortcutsUser;
  var saved = [];
  try { var value = JSON.parse(localStorage.getItem(key) || '[]'); if (Array.isArray(value)) saved = value.filter(function (id) { return byId.has(id); }).slice(0, 8); } catch (e) {}
  function persist() { try { localStorage.setItem(key, JSON.stringify(saved)); } catch (e) {} }
  function render() {
    lists.forEach(function (list) {
      list.replaceChildren();
      saved.forEach(function (id) {
      var source = byId.get(id); if (!source) return;
      var label = source.querySelector('span')?.textContent?.trim() || id;
      var item = document.createElement('div'); item.className = 'sl-shortcut';
      var link = document.createElement('a'); link.href = source.href; link.setAttribute('aria-label', label); link.dataset.slTip = label;
      var icon = source.querySelector('i.ic'); if (icon) link.appendChild(icon.cloneNode(true));
      var name = document.createElement('span'); name.textContent = label;
      var remove = document.createElement('button'); remove.type = 'button'; remove.className = 'sl-shortcut-remove'; remove.textContent = '×'; remove.setAttribute('aria-label', 'Remove ' + label + ' shortcut');
      remove.addEventListener('click', function () { saved = saved.filter(function (entry) { return entry !== id; }); persist(); render(); });
      item.append(link, name, remove); list.appendChild(item);
      });
    });
    dock.querySelector('.sl-shortcuts-hint').hidden = saved.length > 0;
  }
  function add(id) { if (!byId.has(id) || saved.includes(id) || saved.length >= 8) return; saved.push(id); persist(); render(); }
  links.forEach(function (link) {
    var id = link.dataset.shortcutId; // menu items are pinned by dragging them onto the rail (no + buttons)
    link.draggable = true;
    link.addEventListener('dragstart', function (event) { event.dataTransfer.setData('text/plain', id); event.dataTransfer.effectAllowed = 'copy'; });
  });
  dock.addEventListener('dragover', function (event) { event.preventDefault(); event.dataTransfer.dropEffect = 'copy'; dock.classList.add('is-dragover'); });
  dock.addEventListener('dragleave', function (event) { if (!dock.contains(event.relatedTarget)) dock.classList.remove('is-dragover'); });
  dock.addEventListener('drop', function (event) { event.preventDefault(); dock.classList.remove('is-dragover'); add(event.dataTransfer.getData('text/plain')); });
  render();
})();
