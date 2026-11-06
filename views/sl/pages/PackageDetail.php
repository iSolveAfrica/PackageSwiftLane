<?php
/**
 * PackageDetail - from docs/handover/artifact-source/PackageDetail.dc.html.
 * Data: views/sl/data/packages.php (sl_packageDetailData).
 */
if (!isset($d)) { $d = []; }
$can = $d['can']; $o = $d['owner'];
?>
        <div class="sl-head">
          <div><a href="<?= sl_route('SeaPackages') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Sea Packages</a>
            <div class="row g12 wrap" style="margin-top: 4px"><h1 class="sl-title mono"><?= h($d['trk']) ?></h1><span class="sl-pill <?= h($d['stCls']) ?>"><?= h($d['st']) ?></span><span class="sl-badge sea">Sea</span><?php if ($d['done']): ?><span class="sl-badge ok"><?= h($d['handLabel']) ?></span><?php endif; ?></div></div>
          <div class="row g8 wrap">
            <?php if ($can['label']): ?><a class="sl-btn ghost" target="_blank" href="print_label_package.php?id=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Label</a><?php endif; ?>
            <?php if ($can['print']): ?><a class="sl-btn ghost" target="_blank" href="print_invoice_package.php?id=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-receipt" aria-hidden="true"></i>Invoice</a><?php endif; ?>
            <?php if ($can['edit']): ?><a class="sl-btn ghost" href="customer_package_edit.php?id=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><?php endif; ?>
            <?php if ($can['code']): ?><button type="button" class="sl-btn ghost" data-sl-open="sp-code" data-code-id="<?= (int) $d['id'] ?>" data-code-trk="<?= h($d['trk']) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Pickup code</button><?php endif; ?>
            <?php if ($can['deliver']): ?><a class="sl-btn ghost" href="customer_package_deliver.php?id=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>Hand over</a><?php endif; ?>
            <?php if ($can['pay']): ?><a class="sl-btn" href="add_payment_gateways_package.php?id_order=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-card" aria-hidden="true"></i>Pay now</a><?php endif; ?>
            <?php if ($can['track']): ?><button type="button" class="sl-btn" data-sl-open="pd-status"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Update status</button><?php endif; ?>
          </div>
        </div>
        <section class="sl-card col g16">
          <div class="row between wrap g12"><div><div class="up">Estimated arrival</div><div class="big" style="margin-top: 4px"><?= h($d['eta']) ?></div></div><div class="col g4 tr"><span class="mut"><?= $d['con'] ? 'Container ' . h($d['con']['code']) . ' · ' . h($d['con']['st']) : 'Not in a container yet' ?></span><span class="mono sm mut">Registered <?= h($d['registered']) ?> · <?= h($d['office']) ?></span></div></div>
          <div class="sl-steps" aria-label="Step <?= (int) $d['stage'] ?> of 5"><?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= $d['stage'] ? 'on' : '' ?>"></i><?php endfor; ?></div>
          <div class="row between wrap g8 mut sm"><?php foreach ($d['steps'] as $i => $s): ?><span class="<?= $i + 1 === $d['stage'] ? 'fw5' : '' ?>" <?= $i + 1 === $d['stage'] ? 'style="color: var(--ink)"' : '' ?>><?= h($i + 1 === 5 && $d['done'] ? $d['handLabel'] : $s) ?></span><?php endforeach; ?></div>
        </section>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Boxes</h2><span class="mut sm"><?= (int) $d['boxN'] ?> box<?= $d['boxN'] === 1 ? '' : 'es' ?> · <?= h($d['weight']) ?> · <?= h($d['vol']) ?> vol.</span></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>#</th><th>Contents</th><th class="num">Qty</th><th class="num">Dimensions</th><th class="num">Weight</th><th class="num">Declared</th><th class="num">Volume</th></tr></thead><tbody>
                <?php foreach ($d['boxes'] as $b): ?><tr><td><?= (int) $b['n'] ?></td><td><?= h($b['what']) ?></td><td class="num"><?= h($b['qty']) ?></td><td class="num mono"><?= h($b['dims']) ?></td><td class="num"><?= h($b['w']) ?></td><td class="num"><?= h($b['declared']) ?></td><td class="num"><?= h($b['vol']) ?></td></tr><?php endforeach; ?>
                <?php if (!$d['boxes']): ?><tr><td colspan="7"><div class="sl-empty">No box details were recorded for this package.</div></td></tr><?php endif; ?>
              </tbody></table></div>
            </section>
            <div class="grid cols2">
              <section class="sl-card col g12"><h2 class="sl-ct">Customer</h2><div class="row g12"><span class="sl-av"><?= h($o['ini']) ?></span><div><div class="fw5"><a href="<?= sl_route('CustomerProfile', ['id' => $o['id']]) ?>"><?= h($o['name']) ?></a></div><div class="mut sm"><?= h($o['locker']) ?><?= $o['phone'] ? ' · ' . h($o['phone']) : '' ?></div></div></div><div class="sl-kv"><div><span>Deliver to</span><span><?= h($d['deliverTo']) ?></span></div><div><span>Supplier</span><span><?= h($d['supplier']) ?></span></div><div><span>Supplier tracking</span><span class="mono"><?= h($d['postal']) ?></span></div><div><span>Carrier</span><span><?= h($d['courier']) ?></span></div><div><span>Purchase price</span><span><?= h($d['price']) ?></span></div></div></section>
              <section class="sl-card col g12" id="payment"><h2 class="sl-ct">Payment</h2><div class="row between"><span class="big"><?= h($d['total']) ?></span><span class="sl-pill <?= h($d['payCls']) ?>"><?= h($d['pay']) ?></span></div><div class="sl-kv"><?php foreach ($d['charges'] as $c): ?><div><span><?= h($c[0]) ?></span><span><?= h($c[1]) ?></span></div><?php endforeach; ?><div><span>Method</span><span><?= h($d['method']) ?></span></div><div><span>Paid on</span><span><?= h($d['paidOn']) ?></span></div></div>
                <?php if ($user->cdp_hasPermission('verify_payments') && !sl_userIs('client') && $d['payCls'] === 'onway'): ?><div class="row g8"><button class="sl-btn sm" data-sl-action="ajax/sl/package_ajax.php" data-p-op="confirm_payment" data-p-id="<?= (int) $d['id'] ?>" data-sl-confirm="Confirm the payment for <?= h($d['trk']) ?>?" data-sl-reload><i class="ic ic-sm i-check" aria-hidden="true"></i>Confirm payment</button><a class="sl-btn ghost sm" href="customer_packages_view.php?id=<?= (int) $d['id'] ?>&legacy=1">Legacy view</a></div><?php endif; ?>
              </section>
            </div>
            <?php if ($d['files']): ?><section class="sl-card col g8"><h2 class="sl-ct">Files</h2><?php foreach ($d['files'] as $f): ?><a class="row g10 sm" href="<?= h($f['url']) ?>" target="_blank"><i class="ic ic-sm i-file" aria-hidden="true"></i><span class="grow"><?= h($f['name']) ?></span><span class="mut"><?= h($f['date']) ?></span></a><?php endforeach; ?></section><?php endif; ?>
          </div>
          <aside class="b col g16">
            <section class="sl-card"><div class="sl-ch"><h2 class="sl-ct">Tracking</h2></div>
              <div class="sl-tl"><?php foreach ($d['events'] as $e): ?><div class="<?= h($e['cls']) ?>"><span class="dot"></span><div><div class="fw5"><?= h($e['t']) ?></div><div class="mut sm"><?= h($e['s']) ?></div></div></div><?php endforeach; ?><?php if (!$d['events']): ?><div class="mut sm">No tracking events yet.</div><?php endif; ?></div>
            </section>
            <?php if ($d['con']): ?><section class="sl-card col g10"><div class="row between"><span class="sl-ct">Container <?= h($d['con']['code']) ?></span><span class="sl-pill <?= h($d['con']['cls']) ?>"><?= h($d['con']['st']) ?></span></div><div class="sl-mv" style="margin: 0"><?= (int) $d['con']['n'] ?> <i>packages · <?= h($d['con']['w']) ?></i></div><p class="mut sm">Created <?= h($d['con']['date']) ?></p><a href="<?= sl_route('ConsolidationDetail', ['id' => $d['con']['id'], 'type' => 'sea']) ?>" class="sm fw5 row g6">View container <i class="ic ic-sm i-right" aria-hidden="true"></i></a></section><?php endif; ?>
            <?php if ($can['code']): ?><section class="sl-card col g8"><h2 class="sl-ct">Pickup code</h2><?php if ($d['code']['verified']): ?><div class="sl-banner ok"><i class="ic i-check" aria-hidden="true"></i><span>Code verified · hand over within 30 minutes.</span></div><?php elseif ($d['code']['live']): ?><span class="sm">Customer holds a code sent <?= h($d['code']['live']['sent_at']) ?>.</span><?php else: ?><span class="mut sm">No code yet. It goes out with the ready message, or send it now.</span><?php endif; ?><button type="button" class="sl-btn ghost sm" data-sl-open="sp-code" data-code-id="<?= (int) $d['id'] ?>" data-code-trk="<?= h($d['trk']) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Open code dialog</button></section><?php endif; ?>
            <?php if ($can['delete']): ?><button class="sl-btn ghost" style="color:var(--red-ink)" data-sl-action="ajax/sl/package_ajax.php" data-p-op="delete" data-p-id="<?= (int) $d['id'] ?>" data-sl-confirm="Delete package <?= h($d['trk']) ?>? This cannot be undone."><i class="ic ic-sm i-trash" aria-hidden="true"></i>Delete package</button><?php endif; ?>
          </aside>
        </div>
<?php if ($can['track']): ?>
<div class="sl-dialog" id="pd-status" hidden><form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/package_ajax.php" method="post" role="dialog" aria-label="Update status">
  <input type="hidden" name="op" value="status"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
  <div><b class="fw5">Update <span class="mono"><?= h($d['trk']) ?></span></b><div class="mut sm">The customer is told by WhatsApp and e-mail; a ready status carries their pickup code. Handing over needs a verified code: use the pickup code dialog or the Hand over page.</div></div>
  <label class="sl-field"><span class="sl-lab">New status</span><span class="sl-sel native"><select name="status" required><?php foreach ($d['statuses'] as $s): ?><?php if (cdp_isHandedOver($s->id)) continue; ?><option value="<?= (int) $s->id ?>" <?= (int) $s->id === $d['raw'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $s->mod_style)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
  <label class="sl-field"><span class="sl-lab">Location</span><input class="sl-in" name="city" placeholder="Tema port"></label>
  <label class="sl-field"><span class="sl-lab">Note</span><input class="sl-in" name="comment" placeholder="Optional note for the customer"></label>
  <div class="row g8"><button type="submit" class="sl-btn">Save status</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
<?php if ($can['code']) { include CDP_ROOT . '/views/sl/inc/pickup_code_dialog.php'; } ?>
