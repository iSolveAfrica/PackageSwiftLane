/* Staff AI chat: sends to ajax/ai_assistant/chat_ajax.php (replies {ok, answer, tools, conversation_id}).
   Never shows an answer the server did not return. */
(function () {
  'use strict';
  var root = document.getElementById('ai'); if (!root) return;
  var live = root.getAttribute('data-live') === '1', conv = parseInt(root.getAttribute('data-conv'), 10) || 0;
  var th = document.getElementById('ai-th'), q = document.getElementById('ai-q'), form = document.getElementById('ai-form'), url = 'ajax/ai_assistant/chat_ajax.php';
  function bubble(me, text, tools) {
    var w = document.getElementById('ai-welcome'); if (w) w.remove();
    var m = document.createElement('div'); m.className = 'ai-m' + (me ? ' me' : '');
    if (!me) { var av = document.createElement('span'); av.className = 'ai-av'; av.innerHTML = '<i class="ic ic-sm i-ai" aria-hidden="true"></i>'; m.appendChild(av); }
    var b = document.createElement('div'); b.className = 'ai-b';
    (tools || []).forEach(function (t) { var d = document.createElement('div'); d.className = 'ai-tool'; d.innerHTML = '<i class="ic ic-sm i-search" aria-hidden="true"></i>'; d.appendChild(document.createTextNode((t.name || '') + (t.summary ? ' · ' + t.summary : ''))); b.appendChild(d); });
    if (me || !SL.aiResponse) { b.appendChild(document.createTextNode(text)); }
    else { var answer = document.createElement('div'); b.appendChild(answer); SL.aiResponse.render(answer, text); } m.appendChild(b); th.appendChild(m); m.scrollIntoView({ block: 'end' }); return m;
  }
  function typing() { var m = bubble(false, ''); m.querySelector('.ai-b').innerHTML = '<span class="ai-typing"><i></i><i></i><i></i></span>'; return m; }
  function ask(text) {
    text = (text || '').trim();
    if (!text || !live) return;
    bubble(true, text); q.value = '';
    var t = typing();
    SL.post(url, { action: 'send', message: text, conversation_id: conv }).then(function (j) {
      t.remove();
      if (j.conversation_id) { conv = j.conversation_id; history.replaceState(null, '', 'ai_assistant.php?c=' + conv); }
      if (!j.ok) { var e = bubble(false, j.error || j.message || 'The assistant could not answer.'); e.querySelector('.ai-b').style.background = 'var(--red-bg)'; return; }
      bubble(false, j.answer, j.tools);
    }).catch(function () { t.remove(); SL.toast('Network error', true); });
  }
  form.addEventListener('submit', function (e) { e.preventDefault(); ask(q.value); });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(q.value); } });
  document.addEventListener('click', function (e) { var s = e.target.closest('[data-ai-ask]'); if (s) ask(s.getAttribute('data-ai-ask')); });
  var del = document.getElementById('ai-del');
  if (del) del.addEventListener('click', function () { if (!confirm('Delete this conversation?')) return; SL.post(url, { action: 'delete', id: conv }).then(function () { location.href = 'ai_assistant.php'; }); });
  /* mobile bottom sheet */
  var app = document.getElementById('sl-app');
  document.getElementById('ai-fab').addEventListener('click', function () { app.classList.add('ai-open'); q.focus(); });
  document.addEventListener('click', function (e) { if (app.classList.contains('ai-open') && !e.target.closest('.ai-chat') && !e.target.closest('#ai-fab')) app.classList.remove('ai-open'); });
  if (matchMedia('(max-width:640px)').matches && (conv || q.value)) app.classList.add('ai-open');
  if (root.getAttribute('data-auto') === '1' && q.value) ask(q.value);
})();
