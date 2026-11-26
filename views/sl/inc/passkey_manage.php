<?php /** Fingerprint / face sign-in: manage dialog + one-time offer on phones (dataJs/sl/passkey.js). */ ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/passkey.css')) ?>">
<div class="sl-dialog" id="pk-manage" hidden>
  <div class="sl-modal" role="dialog" aria-modal="true" aria-labelledby="pk-manage-t">
    <div class="row between"><h2 class="sl-ct" id="pk-manage-t">Fingerprint and face sign-in</h2><button type="button" class="sl-ib sm" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
    <p class="mut sm" style="margin:0">Sign in with your phone's fingerprint, face or screen lock instead of typing your password. Your fingerprint never leaves your phone: the phone only proves it is you.</p>
    <div class="col g8" id="pk-list"><div class="mut sm">Loading…</div></div>
    <div class="sl-banner info" data-pk-unsupported hidden><i class="ic i-info" aria-hidden="true"></i><span>This browser or device cannot do fingerprint sign-in. Open the site on your phone (Chrome or Safari) to set it up there.</span></div>
    <div class="row g8"><button type="button" class="sl-btn" data-pk-add data-pk-needs hidden>Set up on this device</button><button type="button" class="sl-btn ghost" data-sl-close>Done</button></div>
  </div>
</div>
<div class="pk-offer" id="pk-offer" data-has="<?= (int) ($slPkCount ?? 0) > 0 ? '1' : '0' ?>" hidden role="dialog" aria-labelledby="pk-offer-t">
  <span class="pk-offer-ic"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M12 11v4.5a6.5 6.5 0 0 1-1.6 4.3"/><path d="M8.5 11.5a3.5 3.5 0 0 1 7 0V14a10 10 0 0 1-.8 4"/><path d="M5.5 15.5c.3-1.2.5-2.5.5-4a6 6 0 0 1 9.8-4.6"/><path d="M18 9.5c.3.6.5 1.3.5 2v2.5a13 13 0 0 1-.6 4"/><path d="M3.7 11a8.5 8.5 0 0 1 2-5.2M8 3.9A8.5 8.5 0 0 1 20.2 8"/></svg></span>
  <div class="col grow"><b class="fw5" id="pk-offer-t">Sign in with your fingerprint next time?</b><span class="mut sm">Faster than your password, and only works on this phone.</span></div>
  <div class="row g8"><button type="button" class="sl-btn sm ghost" data-pk-later>Not now</button><button type="button" class="sl-btn sm" data-pk-add>Turn on</button></div>
</div>
