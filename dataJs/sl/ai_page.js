/* AI pop-up on every page (views/sl/inc/ai_page.php). Sends the question with what is on screen to
 * ajax/ai_assistant/chat_ajax.php (scope=page). Loads the Markdown renderer the first time it opens.
 * One conversation per page per browser tab (sessionStorage), reloaded when the pop-up reopens. */
(function () {
  'use strict';
  var box = document.getElementById('sl-ai');
  if (!box || window.slAiPage) return;
  window.slAiPage = true;
  var ENDPOINT = 'ajax/ai_assistant/chat_ajax.php';
  var log = box.querySelector('[data-ai-log]'), form = box.querySelector('[data-ai-form]'), input = box.querySelector('[data-ai-in]'), hello = box.querySelector('[data-ai-hello]');
  var key = 'slai:' + (box.getAttribute('data-board') || '') + ':' + location.pathname;
  var conv = 0, busy = false, libs = null, loaded = false, opener = null;
  try { conv = parseInt(sessionStorage.getItem(key) || '0', 10) || 0; } catch (e) { conv = 0; }

  function loadLibs() {
    if (libs) return libs;
    var cfg = window.SL_AI || { libs: [] };
    if (cfg.css && !document.querySelector('link[data-ai-css]')) { var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = cfg.css; l.setAttribute('data-ai-css', ''); document.head.appendChild(l); }
    libs = cfg.libs.reduce(function (p, src) {
      return p.then(function () {
        if (document.querySelector('script[src="' + src + '"]')) return null;
        return new Promise(function (ok) { var s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ok; document.head.appendChild(s); });
      });
    }, Promise.resolve());
    return libs;
  }
  function render(el, text) {
    if (window.SL && SL.aiResponse) SL.aiResponse.render(el, text); else el.textContent = text;
  }
  function bubble(who, text) {
    if (hello) hello.hidden = true;
    var b = document.createElement('div'); b.className = 'sl-ai-msg ' + who;
    if (who === 'bot') { var inner = document.createElement('div'); b.appendChild(inner); render(inner, text); } else b.textContent = text;
    log.appendChild(b); log.scrollTop = log.scrollHeight; return b;
  }
  function thinking() {
    var b = document.createElement('div'); b.className = 'sl-ai-msg bot wait'; b.innerHTML = '<span></span><span></span><span></span>';
    b.setAttribute('aria-label', 'Thinking'); log.appendChild(b); log.scrollTop = log.scrollHeight; return b;
  }
  /* What is on screen: the visible text of the page's main area (tables come through row by row). */
  function pageText() {
    var main = document.getElementById('sl-main') || document.querySelector('main') || document.body;
    var t = (main.innerText || '').replace(/ /g, ' ').replace(/[ \t]+/g, ' ').replace(/\n\s*\n+/g, '\n').trim();
    return t.length > 9000 ? t.slice(0, 9000) + '\n[…page continues]' : t;
  }
  function restore() {
    if (!conv || loaded) return;
    loaded = true;
    fetch(ENDPOINT + '?action=load&id=' + conv, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { conv = 0; try { sessionStorage.removeItem(key); } catch (e) {} return; }
        (j.messages || []).forEach(function (m) { if (m.role === 'user' || m.role === 'assistant') bubble(m.role === 'user' ? 'you' : 'bot', m.content); });
      }).catch(function () {});
  }
  function open(q) {
    opener = document.activeElement;
    box.hidden = false; document.documentElement.classList.add('sl-ai-on');
    loadLibs().then(restore);
    setTimeout(function () { input.focus(); }, 30);
    if (q) { input.value = q; send(); }
  }
  function close() { box.hidden = true; document.documentElement.classList.remove('sl-ai-on'); if (opener && opener.focus) opener.focus(); }
  function send() {
    var q = input.value.trim();
    if (!q || busy) return;
    busy = true; input.value = ''; grow();
    bubble('you', q);
    var wait = thinking();
    var data = { action: 'send', message: q, scope: 'page', page_title: box.getAttribute('data-title') || document.title, page_url: location.pathname.split('/').pop() + location.search, page_text: pageText() };
    if (conv) data.conversation_id = conv;
    var post = window.SL && SL.post ? SL.post(ENDPOINT, data) : fetch(ENDPOINT, { method: 'POST', body: new URLSearchParams(data), credentials: 'same-origin' }).then(function (r) { return r.json(); });
    loadLibs().then(function () { return post; }).then(function (j) {
      wait.remove();
      if (j && j.conversation_id) { conv = j.conversation_id; try { sessionStorage.setItem(key, String(conv)); } catch (e) {} }
      if (j && j.ok) bubble('bot', j.answer);
      else { var b = bubble('bot', (j && (j.error || j.message)) || 'The assistant could not answer.'); b.classList.add('bad'); }
    }).catch(function (e) { wait.remove(); var b = bubble('bot', (e && e.message) || 'Network error. Please try again.'); b.classList.add('bad'); })
      .then(function () { busy = false; input.focus(); });
  }
  function grow() { input.style.height = 'auto'; input.style.height = Math.min(140, input.scrollHeight) + 'px'; }

  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-sl-ai]');
    if (t) { e.preventDefault(); var d = t.closest('details'); if (d) d.open = false; open(t.getAttribute('data-sl-ai') || ''); return; }
    if (box.hidden) return;
    if (e.target.closest('[data-ai-close]')) { close(); return; }
    var c = e.target.closest('[data-ai-q]'); if (c) { input.value = c.textContent; send(); return; }
    if (e.target.closest('[data-ai-new]')) { conv = 0; loaded = true; try { sessionStorage.removeItem(key); } catch (x) {} log.querySelectorAll('.sl-ai-msg').forEach(function (m) { m.remove(); }); if (hello) hello.hidden = false; input.focus(); }
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); send(); });
  input.addEventListener('input', grow);
  input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'j' || e.key === 'J')) { e.preventDefault(); box.hidden ? open('') : close(); }
    else if (e.key === 'Escape' && !box.hidden) close();
  });
})();
