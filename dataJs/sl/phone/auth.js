/* Phone sign-up (views/sl/phone/SignUp.php): the step chips scroll to their section and follow the scroll. */
(function () {
  'use strict';
  var bar = document.querySelector('[data-pa-steps]');
  if (!bar) return;
  var btns = Array.prototype.slice.call(bar.querySelectorAll('[data-pa-goto]'));
  function light(b) { btns.forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-pressed', String(x === b)); }); }
  btns.forEach(function (b) {
    b.addEventListener('click', function () {
      var t = document.querySelector(b.getAttribute('data-pa-goto'));
      if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      light(b);
    });
  });
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) { if (e.isIntersecting) { var b = bar.querySelector('[data-pa-goto="#' + e.target.id + '"]'); if (b) light(b); } });
    }, { rootMargin: '0px 0px -70% 0px' });
    btns.forEach(function (b) { var t = document.querySelector(b.getAttribute('data-pa-goto')); if (t) io.observe(t); });
  }
})();
