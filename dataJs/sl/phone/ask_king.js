'use strict';
/* M-AskKing (views/sl/phone/StaffAI.php): same endpoint and actions as dataJs/sl/ai_chat.js
   (ajax/ai_assistant/chat_ajax.php: send, delete). Never shows an answer the server did not return. */
(function () {
  var root = document.getElementById('ak'); if (!root) return;
  var live = root.getAttribute('data-live') === '1', conv = parseInt(root.getAttribute('data-conv'), 10) || 0, king = root.getAttribute('data-king') || 'King';
  var th = document.getElementById('ak-th'), q = document.getElementById('ak-q'), form = document.getElementById('ak-form'), url = 'ajax/ai_assistant/chat_ajax.php';
  function now() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  function render(el, text) { if (window.SL && SL.aiResponse) SL.aiResponse.render(el, text); else el.textContent = text; }
  function bubble(me, text, tools) {
    var chips = document.getElementById('ak-chips'); if (chips) chips.remove();
    var m = document.createElement('div'); m.className = 'mx-msg ' + (me ? 'me' : 'ai');
    (tools || []).forEach(function (t) { var s = document.createElement('small'); s.textContent = '⌕ ' + (t.name || '') + (t.summary ? ' · ' + t.summary : ''); m.appendChild(s); });
    var p = document.createElement(me ? 'p' : 'div'); if (!me) p.className = 'mx-ans'; if (me) p.textContent = text; else render(p, text); m.appendChild(p);
    var tm = document.createElement('small'); tm.textContent = now(); m.appendChild(tm);
    th.appendChild(m); m.scrollIntoView({ block: 'end' }); return m;
  }
  function ask(text) {
    text = (text || '').trim();
    if (!text || !live) return;
    bubble(true, text); q.value = '';
    var t = bubble(false, ''); t.querySelector('p,.mx-ans').textContent = '…';
    SL.post(url, { action: 'send', message: text, conversation_id: conv }).then(function (j) {
      t.remove();
      if (j.conversation_id) { conv = j.conversation_id; history.replaceState(null, '', 'ai_assistant.php?c=' + conv); }
      if (!j.ok) { var e = bubble(false, ''); e.querySelector('p,.mx-ans').textContent = j.error || j.message || (king + ' could not answer.'); e.querySelector('p,.mx-ans').classList.add('ak-err'); return; }
      bubble(false, j.answer, j.tools);
    }).catch(function () { t.remove(); SL.toast('Network error', true); });
  }
  form.addEventListener('submit', function (e) { e.preventDefault(); ask(q.value); });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(q.value); } });
  document.addEventListener('click', function (e) { var s = e.target.closest('[data-ak-ask]'); if (s) ask(s.getAttribute('data-ak-ask')); });
  var del = document.getElementById('ak-del');
  if (del) del.addEventListener('click', function () { if (!confirm('Delete this conversation?')) return; SL.post(url, { action: 'delete', id: conv }).then(function () { location.href = 'ai_assistant.php'; }); });
  var body = root; body.scrollTop = body.scrollHeight;
  if (root.getAttribute('data-auto') === '1' && q.value) ask(q.value);
})();
