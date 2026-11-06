/* AiStudio: upload a new photo through the existing avatar endpoint, apply the name suggestion. */
(function () {
  'use strict';
  var f = document.getElementById('st-avatar');
  if (f) {
    var inp = f.querySelector('input[type=file]');
    inp.addEventListener('change', function () {
      if (!inp.files[0]) return;
      if (inp.files[0].size > 5 * 1024 * 1024) { SL.toast('The photo must be under 5 MB', true); return; }
      var img = document.getElementById('st-img');
      if (img) { img.style.backgroundImage = 'url(' + URL.createObjectURL(inp.files[0]) + ')'; img.style.backgroundSize = 'cover'; img.innerHTML = ''; }
      SL.post(f.getAttribute('action'), new FormData(f)).then(function (j) {
        var ok = j.success === true || j.status === 'success';
        SL.toast(j.message || (ok ? 'Photo updated' : 'Could not upload the photo'), !ok);
        if (ok) setTimeout(function () { location.reload(); }, 600);
      }).catch(function () { SL.toast('Network error', true); });
    });
  }
  var use = document.getElementById('st-use');
  if (use) use.addEventListener('click', function () {
    var parts = use.getAttribute('data-name').split(' '), form = document.getElementById('st-name');
    form.querySelector('[name=fname]').value = parts.shift() || '';
    form.querySelector('[name=lname]').value = parts.join(' ');
    SL.toast('Suggestion applied. Press Save changes.');
  });
})();
