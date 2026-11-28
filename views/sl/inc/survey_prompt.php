<?php
/**
 * In-app survey pop-up for customers (included by views/sl/inc/shell_end.php
 * on client pages, before swiftlane.js so the .sl-dialog mechanics apply).
 *
 * Shown when the customer has an unanswered survey younger than the window
 * whose form asks in-app (helpers/comms_surveys.php cdp_surveyPending):
 * once per visit gap, at most N times (counted when shown: action "shown"). "Ask me later", Escape or a click
 * outside wait the gap; "Don't ask again" stops it for this survey. Answers
 * and dismissals post to ajax/comms/survey_answer_ajax.php, which only
 * accepts the signed-in customer's own survey.
 */
if (empty($slIsClient) || !is_file(CDP_ROOT . '/helpers/comms_surveys.php')) {
    return;
}
if (in_array(sl_self(), ['survey.php', 'support.php', 'logout.php'], true)) {
    return;
}
try {
    require_once CDP_ROOT . '/helpers/comms_surveys.php';
    $spSurvey = cdp_surveyPending((int) ($_SESSION['userid'] ?? 0));
    if (!$spSurvey) {
        return;
    }
    $spForm = cdp_surveyFormFor($spSurvey);
    // Counted as asked when it is actually shown (action "shown"), not when a page merely renders it.
} catch (Throwable $e) {
    error_log('survey prompt: ' . $e->getMessage());
    return;
}
$spRef = trim((string) ($spSurvey->entity_ref ?? ''));
// Intro and thank-you may carry {company} / {first_name} (written with King): filled, never shown raw.
$spUid = (int) ($_SESSION['userid'] ?? 0);
$spIntro = cdp_surveyFillText($spForm['intro'] !== '' ? $spForm['intro'] : 'Tell us in a few seconds how it went.', $spUid);
$spThanks = cdp_surveyFillText($spForm['thanks'] !== '' ? $spForm['thanks'] : cdp_surveyDefaultThanks(), $spUid);
?>
<style><?= cdp_surveyQuestionsCss() ?>
.sp-modal{gap:14px}
.sp-modal [hidden]{display:none!important}
.sp-hd{display:flex;gap:12px;align-items:flex-start}
.sp-ic{flex:none;width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:var(--amber-bg);color:var(--amber-ink);font-size:20px}
.sp-hd h2{margin:0;font:600 19px/1.3 var(--font);color:var(--ink);outline:0}
.sp-hd p{margin:4px 0 0;color:var(--ink-2);font-size:15px;line-height:1.45}
.sp-x{margin-left:auto;flex:none;width:36px;height:36px;border-radius:10px;border:0;background:transparent;color:var(--muted);font-size:22px;cursor:pointer}
.sp-x:hover{background:var(--hover,var(--field))}
.sp-qs{max-height:52vh;overflow:auto;padding-right:2px}
#sl-survey fieldset.sq-q{border:0;padding:0;margin:0 0 18px;background:none;box-shadow:none;border-radius:0}
#sl-survey fieldset.sq-q legend{padding:0;float:none;width:auto}
.sp-ft{display:flex;gap:8px;align-items:center;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:12px}
.sp-ft .grow{flex:1}
.sp-never{background:none;border:0;padding:6px 0;color:var(--muted);font:400 14px var(--font);text-decoration:underline;cursor:pointer}
.sp-done{text-align:center;padding:12px 4px}
.sp-done .sp-ic{margin:0 auto 10px;background:var(--green-bg,var(--field));color:var(--green-ink,var(--ink))}
.sp-done h2{margin:0 0 6px;font:600 19px var(--font)}
@media (max-width:560px){.sp-qs{max-height:58vh}.sp-ft .sl-btn{flex:1}.sp-never{width:100%;text-align:center}}
</style>
<div class="sl-dialog" id="sl-survey" hidden data-sp="<?= (int) $spSurvey->id ?>">
  <form class="sl-modal sp-modal" role="dialog" aria-modal="true" aria-labelledby="sp-t" novalidate data-sp-form>
    <div data-sp-ask>
      <div class="sp-hd">
        <span class="sp-ic" aria-hidden="true">&#9733;</span>
        <div>
          <h2 id="sp-t" tabindex="-1"><?= h($spForm['name'] ?: 'How did we do?') ?></h2>
          <p><?= h($spIntro) ?><?php if ($spRef !== ''): ?> <span class="mut sm">(<?= h($spRef) ?>)</span><?php endif; ?></p>
        </div>
        <button type="button" class="sp-x" data-sp-later aria-label="Ask me later">&times;</button>
      </div>
    </div>
    <div class="sp-qs" data-sp-ask><?= cdp_surveyQuestionsHtml($spForm['questions'], [], 'sp') ?></div>
    <p class="sq-err" data-sp-msg hidden role="alert"></p>
    <div class="sp-ft" data-sp-ask>
      <button type="button" class="sp-never" data-sp-never>Don't ask me again</button>
      <span class="grow"></span>
      <button type="button" class="sl-btn ghost" data-sp-later>Ask me later</button>
      <button type="submit" class="sl-btn">Send feedback</button>
    </div>
    <div class="sp-done" data-sp-done hidden>
      <span class="sp-ic" aria-hidden="true">&#10003;</span>
      <h2 tabindex="-1" data-sp-thanks-h>All done</h2>
      <p class="mut" data-sp-thanks><?= h($spThanks) ?></p>
      <button type="button" class="sl-btn" data-sp-close>Close</button>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var dlg = document.getElementById('sl-survey'); if (!dlg || !window.SL) return;
  var form = dlg.querySelector('[data-sp-form]'), id = dlg.getAttribute('data-sp'), url = 'ajax/comms/survey_answer_ajax.php';
  var settled = false, busy = false;
  function q(s) { return Array.prototype.slice.call(dlg.querySelectorAll(s)); }
  function msg(t) { var m = dlg.querySelector('[data-sp-msg]'); m.textContent = t || ''; m.hidden = !t; }
  function close() { dlg.hidden = true; }
  function later(kind) {
    if (settled) return; settled = true;
    SL.post(url, { action: kind || 'later', id: id }).then(function (j) { if (j && j.message && kind === 'never') SL.toast(j.message); }).catch(function () {});
  }
  // Escape and a click outside close the dialog (swiftlane.js): that is "ask me later".
  new MutationObserver(function () { if (dlg.hidden) later('later'); }).observe(dlg, { attributes: true, attributeFilter: ['hidden'] });
  q('[data-sp-later]').forEach(function (b) { b.addEventListener('click', function () { later('later'); close(); }); });
  q('[data-sp-never]').forEach(function (b) { b.addEventListener('click', function () { later('never'); close(); }); });
  q('[data-sp-close]').forEach(function (b) { b.addEventListener('click', close); });
  form.addEventListener('change', function (e) { var fs = e.target.closest('[data-sq]'); if (fs) { var er = fs.querySelector('[data-sq-err]'); if (er) er.hidden = true; } });
  form.addEventListener('submit', function (e) {
    e.preventDefault(); if (busy) return;
    msg('');
    var missing = null;
    q('[data-sq][data-sq-req]').forEach(function (fs) {
      var ok = fs.getAttribute('data-sq-type') === 'text' ? !!(fs.querySelector('textarea').value || '').trim() : !!fs.querySelector('input:checked');
      var er = fs.querySelector('[data-sq-err]');
      if (!ok) { er.textContent = fs.getAttribute('data-sq-type') === 'text' ? 'Please write a short answer.' : 'Please choose an answer.'; er.hidden = false; missing = missing || fs; }
      else er.hidden = true;
    });
    if (missing) { var f = missing.querySelector('input,textarea'); if (f) f.focus(); return; }
    var data = new FormData(form); data.append('action', 'answer'); data.append('id', id);
    busy = true; var btn = form.querySelector('[type="submit"]'); btn.classList.add('loading'); btn.disabled = true;
    SL.post(url, data).then(function (j) {
      if (!j || !j.success) {
        if (j && j.errors) Object.keys(j.errors).forEach(function (k) { var er = dlg.querySelector('[data-sq-err="' + k + '"]'); if (er) { er.textContent = j.errors[k]; er.hidden = false; } });
        msg((j && j.message) || 'Your answers were not saved. Please try again.'); return;
      }
      settled = true;
      q('[data-sp-ask]').forEach(function (x) { x.hidden = true; });
      var d = dlg.querySelector('[data-sp-done]'); d.hidden = false;
      if (j.thanks) dlg.querySelector('[data-sp-thanks]').textContent = j.thanks;
      dlg.querySelector('[data-sp-thanks-h]').focus();
    }).catch(function (err) { msg(err.message || 'Network error. Please try again.'); })
      .finally(function () { busy = false; btn.classList.remove('loading'); btn.disabled = false; });
  });
  // Wait for any other dialog the page opened first (install prompt, sign-in notices).
  function busyElsewhere() {
    return Array.prototype.some.call(document.querySelectorAll('[role="dialog"][aria-modal="true"], .sl-dialog'), function (d) {
      return d !== dlg && !dlg.contains(d) && !d.closest('#sl-survey') && !d.hidden && d.getClientRects().length > 0;
    });
  }
  var tries = 0;
  function show() {
    if (busyElsewhere() && tries++ < 200) { setTimeout(show, 1500); return; }
    dlg.hidden = false;
    SL.post(url, { action: 'shown', id: id }).catch(function () {});
    // swiftlane.js moves focus to the first button (the "x") from its own observer,
    // which runs after this: put it on the title once that has happened, so an
    // accidental Enter does not dismiss the survey.
    function toTitle() { var t = document.getElementById('sp-t'); if (t && !dlg.hidden) { try { t.focus({ preventScroll: true }); } catch (e) { t.focus(); } } }
    setTimeout(function () { requestAnimationFrame(toTitle); }, 0);
  }
  setTimeout(show, 1200);
});
</script>
