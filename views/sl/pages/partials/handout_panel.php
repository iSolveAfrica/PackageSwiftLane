<?php
/**
 * Hand Out customer panel. Rendered inside views/sl/pages/HandOut.php and by ajax/sl/handout_ajax.php op=panel.
 * Expects $c (sl_whCustomer) and $d (sl_handOutFlags: canCode, canPkg, canUser, canSkip). Driven by dataJs/sl/handout.js.
 * Flow: the 6-digit code verifies itself as the last digit is typed (or a manager skips it) → tick each package
 * (or Check all; scanning is optional) → Hand over. Click a package to see its contents and payment.
 */
$unpaidN = count(array_filter($c['pkgs'], fn($p) => !$p['paid']));
?>
<div class="ho-panel" id="ho-flow" data-customer="<?= (int) $c['id'] ?>" data-verified="<?= h(json_encode($c['verifiedItems'])) ?>" data-can-user="<?= $d['canUser'] ? 1 : 0 ?>">
  <div class="ho-cust"><span class="sl-av lg"><?= h($c['ini']) ?></span><span class="col grow"><b class="fw5" style="font-size:18px"><?= h($c['name']) ?></b><span class="mut sm"><?= h($c['lock'] ?: 'No locker') ?><?= $c['phone'] ? ' · ' . h(cdp_pickupCodeMask($c['phone'])) : '' ?> · <?= count($c['pkgs']) ?> parcel<?= count($c['pkgs']) === 1 ? '' : 's' ?> ready</span></span><span class="sl-pill <?= h($c['payCls']) ?>"><?= h($c['payL']) ?></span><button type="button" class="sl-ib" data-ho-close aria-label="Close this customer"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>

  <section class="ho-codebox ho-hide-done" aria-label="Pickup code">
    <div class="row between wrap g8"><div class="col"><b class="fw5" style="font-size:18px">Pickup code</b><span class="mut sm" data-ho-codehelp><?= $c['code']['live'] ? 'Sent ' . h($c['code']['live']['sent_at']) . ($c['code']['live']['channels'] ? ' by ' . h(implode(', ', $c['code']['live']['channels'])) : '') . ' · type it in, it checks itself' : 'No code sent yet · send one, or ' . ($d['canSkip'] ? 'skip it as a manager' : 'ask a manager to skip it') ?></span></div>
      <span class="ho-state" data-ho-state hidden><i class="ic ic-sm i-check" aria-hidden="true"></i><span></span></span></div>
    <div class="row g12 wrap" style="align-items:center" data-ho-codeinputs>
      <div class="ho-code-inputs" role="group" aria-label="Pickup code"><?php for ($i = 1; $i <= 6; $i++): ?><input class="sl-in mono ho-digit" inputmode="numeric" maxlength="1" aria-label="Digit <?= $i ?>" autocomplete="off"<?= $d['canCode'] ? '' : ' disabled' ?>><?php endfor; ?></div>
      <div class="row g8 wrap"><?php if ($d['canCode']): ?><button class="sl-btn ghost" type="button" data-ho-send><i class="ic ic-sm i-message" aria-hidden="true"></i>Send code</button><?php endif; ?><?php if ($d['canSkip']): ?><button class="sl-btn ghost" type="button" data-ho-skip><i class="ic ic-sm i-right" aria-hidden="true"></i>Skip code</button><?php endif; ?></div>
    </div>
    <div class="ho-bad" data-ho-bad hidden><i class="ic i-x" aria-hidden="true"></i><span></span></div>
    <?php if ($d['canSkip']): ?>
    <div class="ho-skipbox" data-ho-skipbox hidden>
      <b class="fw5">Hand over without the code</b><span class="mut sm">Logged under your name. Check the customer's ID first.</span>
      <div class="row g6 wrap"><?php foreach (['ID checked in person', 'Code not received', 'Known regular customer', 'Phone lost or off'] as $r): ?><button type="button" class="sl-chip" data-ho-reason="<?= h($r) ?>"><?= h($r) ?></button><?php endforeach; ?></div>
      <input class="sl-in" data-ho-reasontext maxlength="200" placeholder="Reason (optional)">
      <div class="row g8"><button type="button" class="sl-btn" data-ho-skipok><i class="ic ic-sm i-check" aria-hidden="true"></i>ID checked · continue without code</button><button type="button" class="sl-btn ghost" data-ho-skipcancel>Back</button></div>
    </div>
    <?php endif; ?>
  </section>

  <section class="col g8 ho-hide-done ho-pkgs" data-ho-pkgs aria-label="Packages ready">
    <div class="row between wrap g8"><b class="fw5" style="font-size:18px">Packages ready · tick what you hand over</b><button type="button" class="sl-btn ghost sm" data-ho-all disabled><i class="ic ic-sm i-check" aria-hidden="true"></i>Check all</button></div>
    <span class="mut sm" data-ho-lockmsg>Enter the pickup code<?= $d['canSkip'] ? ' or skip it' : '' ?> to unlock the packages. Click any package to see what is inside.</span>
    <?php foreach ($c['pkgs'] as $p): ?>
    <div class="ho-pk locked" data-item="<?= h($p['module'] . ':' . $p['oid']) ?>" data-trk="<?= h($p['id']) ?>" data-post="<?= h($p['post']) ?>" data-cleared="<?= $p['cleared'] ? 1 : 0 ?>" data-paid="<?= $p['paid'] ? 1 : 0 ?>">
      <span class="ho-bay"><small>BAY</small><b><?= h($p['bay']) ?></b></span>
      <button type="button" class="ho-pk-main" data-ho-open="<?= h($p['module']) ?>" data-ho-id="<?= (int) $p['oid'] ?>" aria-label="Show what is inside <?= h($p['id']) ?>"><b class="fw5 mono"><?= h($p['id']) ?></b><span class="mut sm"><?= h($p['what']) ?> · <?= h($p['w']) ?> · <?= $p['module'] === 'sea' ? 'Sea' : 'Air' ?> · waiting <?= h($p['days']) ?><?= $p['cleared'] ? '' : ' · <b style="color:var(--red-ink)">not cleared by Accounts</b>' ?></span></button>
      <span class="sl-pill <?= h($p['pay'][1]) ?>"><?= h($p['pay'][0]) ?></span>
      <button type="button" class="ho-tick" data-ho-tick disabled aria-pressed="false" aria-label="Check <?= h($p['id']) ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i><span>Check</span></button>
    </div>
    <?php endforeach; ?>
    <label class="ho-scan"><span class="ho-beam" aria-hidden="true"></span><input data-ho-scan aria-label="Scan a label (optional)" placeholder="Optional: scan a label to tick it"></label>
    <div class="ho-bad" data-ho-scanbad hidden><i class="ic i-x" aria-hidden="true"></i><span></span></div>
    <?php if ($unpaidN): ?><button type="button" class="ho-chk" data-ho-paidchk hidden><span class="sl-chk" aria-hidden="true"></span>Balance collected for the unpaid package(s) ticked</button><?php endif; ?>
    <?php if ($d['canPkg']): ?><button class="ho-pri" type="button" data-ho-go disabled><i class="ic i-check" aria-hidden="true"></i><span>Hand over</span></button><?php else: ?><span class="mut sm">You can look up parcels but not hand them over.</span><?php endif; ?>
    <span class="mut sm" data-ho-left></span>
  </section>

  <div class="ho-done" data-ho-done hidden><span class="sl-tile" style="width:72px;height:72px;border-radius:22px;background:var(--green-bg);color:var(--green-ink)"><i class="ic i-check" aria-hidden="true"></i></span><h2 style="margin:0;font-size:26px;font-weight:600"></h2><span class="mut">Logged in the Hand-out Log · receipt sent to the customer</span><button type="button" class="ho-pri" style="max-width:360px" data-ho-next><i class="ic i-right" aria-hidden="true"></i>Next customer</button></div>
</div>
