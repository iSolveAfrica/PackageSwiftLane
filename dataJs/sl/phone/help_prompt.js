/* Customer phone home: after 3 minutes on the page, ask once "Need any help?" over a blurred screen.
   "Chat with us" opens support; "No thanks", the close button, a tap on the blur or Escape just closes it.
   Asked at most once per browser session (sessionStorage), never while the person is typing. */
(function () {
  'use strict';
  var KEY = 'sl.helpAsked', WAIT = 180000;
  function asked() { try { return sessionStorage.getItem(KEY) === '1'; } catch (e) { return false; } }
  function mark() { try { sessionStorage.setItem(KEY, '1'); } catch (e) {} }
  if (asked()) return;
  var chat = document.querySelector('.b3-chat');
  var href = chat ? chat.getAttribute('href') : 'support.php';
  var shown = 0, timer;

  function show() {
    if (asked() || document.hidden) { timer = setTimeout(show, 15000); return; }
    var a = document.activeElement;
    if (a && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) { timer = setTimeout(show, 15000); return; }
    mark();
    var ov = document.createElement('div');
    ov.className = 'hp-ov';
    ov.setAttribute('role', 'dialog');
    ov.setAttribute('aria-modal', 'true');
    ov.setAttribute('aria-labelledby', 'hp-t');
    ov.innerHTML = '<div class="hp-card">'
      + '<button type="button" class="hp-x" aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>'
      + '<span class="hp-ic"><i class="ic i-message" aria-hidden="true"></i></span>'
      + '<h2 id="hp-t">Need any help?</h2>'
      + '<p>We are here if you have a question about a parcel, a bill or your address.</p>'
      + '<a class="hp-yes" href="' + href.replace(/"/g, '&quot;') + '"><i class="ic ic-sm i-message" aria-hidden="true"></i>Yes, chat with us</a>'
      + '<button type="button" class="hp-no">No thanks</button>'
      + '</div>';
    var last = document.activeElement;
    function close() {
      ov.classList.remove('on');
      document.removeEventListener('keydown', onKey);
      setTimeout(function () { ov.remove(); }, 220);
      if (last && last.focus) last.focus();
    }
    function onKey(e) { if (e.key === 'Escape') close(); }
    ov.addEventListener('click', function (e) {
      if (e.target === ov || e.target.closest('.hp-no,.hp-x')) close();
    });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
    requestAnimationFrame(function () { ov.classList.add('on'); ov.querySelector('.hp-yes').focus(); });
    shown++;
  }
  timer = setTimeout(show, WAIT);
})();
