/* Rider pickup scan (views/sl/pages/RiderScan.php): type or scan a tracking number, the server says whether the
   parcel is on one of the rider's open jobs and ticks it. The camera is offered only where the browser can read
   barcodes itself (BarcodeDetector: Chrome on Android); everywhere else the typed field is the whole feature. */
(function () {
  'use strict';
  var root = document.querySelector('[data-ro-scan]');
  if (!root || !window.SL) return;
  var url = root.getAttribute('data-ro-scan');
  var form = root.querySelector('[data-ro-form]'), input = form.querySelector('input[name=q]');
  var status = root.querySelector('[data-ro-status]'), hint = root.querySelector('[data-ro-hint]');
  var busy = false, lastCode = '', lastAt = 0;

  function say(text, sub) { status.textContent = text; if (sub !== undefined) hint.textContent = sub; }
  function counts() {
    var total = root.querySelectorAll('[data-ro-parcel]').length, ticked = root.querySelectorAll('[data-ro-parcel].ok').length;
    document.querySelectorAll('[data-ro-ticked]').forEach(function (el) { el.textContent = ticked; });
    var left = root.querySelector('[data-ro-left]');
    if (left) { left.textContent = total - ticked > 0 ? (total - ticked) + ' to go' : (total ? 'all ticked' : 'nothing to scan'); left.className = total && ticked >= total ? 'up' : 'warn'; }
    root.querySelectorAll('[data-ro-job]').forEach(function (job) {
      var act = job.querySelector('[data-ro-act]');
      if (act) act.hidden = job.querySelectorAll('[data-ro-parcel]:not(.ok)').length > 0;
    });
  }
  function mark(parcel, on) {
    var row = root.querySelector('[data-ro-parcel="' + parcel + '"]');
    if (!row) return false;
    row.classList.toggle('ok', on);
    row.querySelector('.rb-cki .ic').className = 'ic ic-sm i-' + (on ? 'check' : 'scan');
    row.querySelector('.rb-ckl').textContent = on ? 'Scanned' : 'To scan';
    if (on) row.scrollIntoView({ block: 'nearest' });
    counts();
    return true;
  }
  function check(code) {
    code = String(code || '').trim();
    if (!code || busy) return Promise.resolve();
    busy = true;
    say('Checking ' + code.slice(0, 30) + '…');
    return SL.post(url, { op: 'scan', code: code }).then(function (j) {
      if (j.success && j.match) {
        // A parcel of a job that is not listed on this (filtered) screen is still ticked on the server.
        var shown = mark(j.parcel, true);
        say(j.trk + ' is yours', shown ? j.scanned + ' of ' + j.total + ' on this job' : 'It is on another of your open jobs.');
        SL.toast(j.message);
        input.value = '';
        if (navigator.vibrate && navigator.userActivation && navigator.userActivation.hasBeenActive) navigator.vibrate(60);
      } else {
        say('Not on your jobs', j.message || 'Check the number and try again.');
        SL.toast(j.message || 'Not on your jobs', true);
        if (navigator.vibrate && navigator.userActivation && navigator.userActivation.hasBeenActive) navigator.vibrate([80, 60, 80]);
      }
    }).catch(function (err) { say('Could not check', err.message || 'Network error. Try again.'); SL.toast(err.message || 'Network error. Try again.', true); })
      .finally(function () { busy = false; });
  }
  form.addEventListener('submit', function (e) { e.preventDefault(); check(input.value); });
  root.addEventListener('click', function (e) {
    var un = e.target.closest('[data-ro-un]');
    if (!un || busy) return;
    var row = un.closest('[data-ro-parcel]'), parcel = row.getAttribute('data-ro-parcel');
    busy = true;
    SL.post(url, { op: 'unscan', parcel: parcel }).then(function (j) {
      if (j.success) { mark(parcel, false); SL.toast(j.message); } else SL.toast(j.message || 'Not changed', true);
    }).catch(function (err) { SL.toast(err.message || 'Network error. Try again.', true); }).finally(function () { busy = false; });
  });
  // Arriving from the home search box (?q=): check it straight away.
  if (input.value.trim() && !input.disabled) check(input.value);

  /* ---- camera, only where the browser reads barcodes ---- */
  var bar = root.querySelector('[data-ro-cambar]'), camBtn = root.querySelector('[data-ro-cam]'), torchBtn = root.querySelector('[data-ro-torch]');
  var video = root.querySelector('[data-ro-video]'), vf = root.querySelector('[data-ro-vf]');
  var stream = null, timer = null, detector = null;
  if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || input.disabled) return;
  bar.hidden = false;
  say(status.textContent, 'Use the camera, or type the number printed under the barcode.');
  function stop() {
    clearInterval(timer); timer = null;
    if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
    stream = null; video.hidden = true; video.srcObject = null; vf.classList.remove('live'); torchBtn.hidden = true;
    camBtn.querySelector('span').textContent = 'Use camera';
  }
  function start() {
    var formats = ['code_128', 'code_39', 'ean_13', 'qr_code', 'itf', 'codabar'];
    (window.BarcodeDetector.getSupportedFormats ? window.BarcodeDetector.getSupportedFormats() : Promise.resolve(formats)).then(function (ok) {
      detector = new window.BarcodeDetector({ formats: formats.filter(function (f) { return ok.indexOf(f) >= 0; }) });
      return navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    }).then(function (s) {
      stream = s; video.srcObject = s; video.hidden = false; vf.classList.add('live');
      camBtn.querySelector('span').textContent = 'Stop camera';
      say('Point at the barcode', 'Hold the label flat inside the frame.');
      var track = s.getVideoTracks()[0], caps = track.getCapabilities ? track.getCapabilities() : {};
      torchBtn.hidden = !caps.torch;
      torchBtn.onclick = function () { var on = torchBtn.getAttribute('aria-pressed') !== 'true'; track.applyConstraints({ advanced: [{ torch: on }] }).then(function () { torchBtn.setAttribute('aria-pressed', String(on)); }).catch(function () {}); };
      return video.play();
    }).then(function () {
      timer = setInterval(function () {
        if (busy || !stream || video.readyState < 2) return;
        detector.detect(video).then(function (codes) {
          if (!codes.length) return;
          var v = codes[0].rawValue, now = Date.now();
          if (v === lastCode && now - lastAt < 4000) return; // the same label still in view
          lastCode = v; lastAt = now; check(v);
        }).catch(function () {});
      }, 350);
    }).catch(function () { stop(); say('Camera not available', 'Allow camera access for this site, or type the number.'); });
  }
  camBtn.addEventListener('click', function () { if (stream) stop(); else start(); });
  root.querySelector('[data-ro-type]').addEventListener('click', function () { stop(); input.focus(); });
  document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); });
  addEventListener('pagehide', stop);
})();
