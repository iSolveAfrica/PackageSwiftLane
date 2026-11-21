<?php
/**
 * PreAlerts - from docs/handover/artifact-source/PreAlerts.dc.html.
 * Data: views/sl/data/prealerts.php. Full-width list with statistics; "New Pre-Alert" opens a pop-up
 * (#pa-new). prealert_add.php sends ?new=1, which opens the pop-up on load.
 * Styles: assets/swiftlane/overview-hero.css (cards) + assets/swiftlane/pages/PreAlerts.css.
 */
if (!isset($d)) { $d = []; }
$st = $d['stats'];
$bars = function (array $bars, string $tone = '') {
    if (!$bars) return '';
    $o = '<div class="ovh-bars" role="img" aria-label="Chart"' . ($tone ? ' style="--tone:' . h($tone) . '"' : '') . '>';
    foreach ($bars as $b) $o .= '<i style="' . h($b['s']) . '" title="' . h($b['t']) . '"></i>';
    return $o . '</div>';
};
$cols = $d['isClient'] ? 6 : 7;
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
        <div class="sl-head">
          <div><h1 class="sl-title">Pre-Alerts</h1><p class="sl-lede"><?= $d['isClient'] ? 'Tell us what is coming before it reaches the warehouse' : 'Customers tell us what is coming before it reaches the warehouse' ?> · <?= sl_num($d['openN']) ?> open · <?= sl_num($d['weekN']) ?> sent this week</p></div>
          <?php if ($d['canAdd']): ?><button type="button" class="sl-btn" data-sl-open="pa-new"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Pre-Alert</button><?php endif; ?>
        </div>

        <div class="pa-stats">
          <a class="ovh-card t-blue" href="<?= h(sl_url(['filter' => 'week', 'page' => null])) ?>">
            <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm i-inbox" aria-hidden="true"></i>Expected</span><i class="ic ic-sm i-expand ovh-go" aria-hidden="true"></i></div>
            <b class="ovh-big"><?= sl_num($st['open']) ?> <small class="pa-small">open</small></b>
            <span class="ovh-lede"><?= sl_num($st['today']) ?> due today · <?= sl_num($st['week']) ?> in the next 7 days</span>
            <?= $bars($st['fwd']) ?>
            <div class="ovh-foot"><span>Arrivals by expected date, next 14 days</span><span><?= sl_num($st['fwdN']) ?></span></div>
          </a>
          <a class="ovh-card t-rose" href="<?= h(sl_url(['filter' => 'overdue', 'page' => null])) ?>">
            <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm i-alert" aria-hidden="true"></i>Overdue</span><?php if ($st['late7']): ?><span class="ovh-alert"><?= sl_num($st['late7']) ?> over 7 days</span><?php else: ?><i class="ic ic-sm i-expand ovh-go" aria-hidden="true"></i><?php endif; ?></div>
            <b class="ovh-big"><?= sl_num($st['overdue']) ?> <small class="pa-small">late</small></b>
            <span class="ovh-lede"><?= $st['overdue'] ? 'Past the expected date and not yet received · oldest ' . (int) $st['oldest'] . ' d' : 'Everything due has arrived' ?></span>
            <?= $bars($st['late']) ?>
            <div class="pa-axis"><?php foreach ($st['lateL'] as $l): ?><span><?= h($l) ?></span><?php endforeach; ?></div>
            <div class="ovh-foot"><span>Open pre-alerts by days late</span></div>
          </a>
          <a class="ovh-card t-mint" href="<?= h(sl_url(['filter' => 'matched', 'page' => null])) ?>">
            <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm i-check" aria-hidden="true"></i>Arrived</span><i class="ic ic-sm i-expand ovh-go" aria-hidden="true"></i></div>
            <b class="ovh-big"><?= (int) $st['rate'] ?>%</b>
            <span class="ovh-lede"><?= sl_num($st['matched30']) ?> of <?= sl_num($st['sent30']) ?> sent in the last 30 days have arrived · <?= sl_num($st['matchedAll']) ?> in total</span>
            <div class="pa-meter" role="img" aria-label="<?= (int) $st['rate'] ?>% matched"><i style="width:<?= (int) $st['rate'] ?>%"></i></div>
            <?= $bars($st['sent'], 'var(--green)') ?>
            <div class="ovh-foot"><span>Pre-alerts sent per day, 14 days</span><span><?= sl_num($st['sentN']) ?></span></div>
          </a>
          <div class="ovh-card t-amber">
            <div class="ovh-ch"><span class="ovh-eyebrow"><i class="ic ic-sm i-tag" aria-hidden="true"></i><?= $st['showValue'] ? 'Declared value' : 'Top shops' ?></span></div>
            <?php if ($st['showValue']): ?><b class="ovh-big"><?= h($st['value']) ?></b><span class="ovh-lede">purchase price of open pre-alerts</span><?php endif; ?>
            <div class="pa-shops">
              <?php foreach ($st['shops'] as $s): ?><div><div class="row between sm"><span class="fw5"><?= h($s['name']) ?></span><span class="mut mono"><?= (int) $s['n'] ?></span></div><div class="pa-meter sm"><i style="width:<?= (int) $s['w'] ?>%"></i></div></div><?php endforeach; ?>
              <?php if (!$st['shops']): ?><span class="mut sm">No pre-alerts in the last 90 days.</span><?php endif; ?>
            </div>
            <div class="ovh-foot"><span>Shops, last 90 days</span></div>
          </div>
        </div>

        <div class="sl-card flush">
          <form method="get" class="row wrap g12" style="padding: 14px 16px">
            <input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
            <div class="sl-search" style="flex:1;max-width:420px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search pre-alerts" placeholder="Search tracking, shop, contents or customer"></div>
            <span class="grow"></span>
            <div class="sl-seg boxed"><?php foreach ($d['filters'] as $x): ?><a class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div>
          </form>
          <div class="sl-tw"><table class="sl-table pa-table">
            <thead><tr><th>Tracking</th><?php if (!$d['isClient']): ?><th>Customer</th><?php endif; ?><th>Shop &amp; carrier</th><th>What is inside</th><th>Expected</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($d['rows'] as $r): ?>
                <tr>
                  <td><span class="mono fw5"><?= h($r['trk']) ?></span><span class="sub">sent <?= h($r['sent']) ?></span></td>
                  <?php if (!$d['isClient']): ?><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><span class="fw5"><?= h($r['name']) ?></span><span class="sub mono"><?= h($r['locker']) ?></span></span></div></td><?php endif; ?>
                  <td><?= h($r['sup'] ?: '—') ?><span class="sub"><?= h($r['carrier']) ?></span></td>
                  <td><span class="pa-what" title="<?= h($r['what']) ?>"><?= h($r['what']) ?></span><span class="sub mono">value <?= h($r['price']) ?></span></td>
                  <td><?= h($r['eta']) ?><?php if ($r['lateBy']): ?><span class="sub" style="color:var(--red-ink)"><?= (int) $r['lateBy'] ?> d late</span><?php endif; ?></td>
                  <td><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></td>
                  <td class="pa-act"><?php $canReg = $r['open'] && ($d['canPackage'] || $d['canShipment']); $canInv = $d['canInvoice'] && $r['invoice']; ?>
                    <?php if ($canReg || $canInv): ?><details class="sl-menu" data-sl-pop><summary class="sl-btn ghost sm"><?= $canReg ? 'Register' : 'Invoice' ?> <i class="ic ic-sm i-down" aria-hidden="true"></i></summary><div class="pop" role="menu">
                      <?php if ($r['open'] && $d['canPackage']): ?><a role="menuitem" href="<?= sl_route('NewPackage', ['prealert_id' => $r['id']]) ?>">Create sea package</a><?php endif; ?>
                      <?php if ($r['open'] && $d['canShipment']): ?><a role="menuitem" href="courier_add.php?prealert_id=<?= (int) $r['id'] ?>">Create air shipment</a><?php endif; ?>
                      <?php if ($canInv): ?><a role="menuitem" target="_blank" href="<?= h($r['invoice']) ?>">Open invoice</a><?php endif; ?>
                    </div></details><?php endif; ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$d['rows']): ?><tr><td colspan="<?= $cols ?>"><div class="sl-empty"><span class="sl-tile"><i class="ic i-inbox" aria-hidden="true"></i></span>No pre-alerts here.<?php if ($d['canAdd']): ?> <button type="button" class="sl-btn sm" data-sl-open="pa-new">New Pre-Alert</button><?php endif; ?></div></td></tr><?php endif; ?>
            </tbody>
          </table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'pre-alerts') ?>
        </div>

<?php if ($d['canAdd']): ?>
<div class="sl-dialog" id="pa-new" hidden>
  <form class="sl-modal" data-sl-form data-sl-reload action="ajax/sl/prealert_ajax.php" method="post" enctype="multipart/form-data" role="dialog" aria-modal="true" aria-labelledby="pa-new-title">
    <div class="row between"><h2 class="sl-ct" id="pa-new-title">New pre-alert</h2><button type="button" class="sl-ib sm" data-sl-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button></div>
    <p class="mut sm" style="margin-top:-6px">Tell us about a parcel on its way to the US warehouse, so we can match it the moment it arrives.</p>
    <input type="hidden" name="op" value="create">
    <?php if (!$d['isClient']): ?><label class="sl-field"><span class="sl-lab">Customer</span><span class="sl-sel native"><select name="customer_id" required><option value="">Select customer</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h(trim($c->fname . ' ' . $c->lname)) ?><?= $c->locker ? ' · ' . h($c->locker) : '' ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
    <div class="pa-two">
      <label class="sl-field"><span class="sl-lab">Carrier tracking number</span><input name="tracking" class="sl-in mono" placeholder="1Z999AA1…" required></label>
      <label class="sl-field"><span class="sl-lab">Carrier</span><span class="sl-sel native"><select name="courier" required><?php foreach ($d['couriers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name_com) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
    </div>
    <label class="sl-field"><span class="sl-lab">What is in it</span><input name="description" class="sl-in" placeholder="2 pairs of shoes" required></label>
    <div class="pa-two"><label class="sl-field"><span class="sl-lab">Purchase price</span><input name="price" type="number" step="0.01" min="0.01" class="sl-in mono" required></label><label class="sl-field"><span class="sl-lab">Expected at the warehouse</span><input name="date" type="date" class="sl-in" value="<?= h(date('Y-m-d', strtotime('+5 days'))) ?>" required></label></div>
    <label class="sl-drop" style="padding: 16px;cursor:pointer"><i class="ic i-upload" aria-hidden="true"></i><span class="sm" id="pa-file-note">Attach invoice<?= $d['isClient'] ? '' : ' (optional)' ?></span><input type="file" name="file_invoice" accept=".pdf,image/*" hidden onchange="document.getElementById('pa-file-note').textContent=this.files.length?this.files[0].name:'Attach invoice'"></label>
    <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>When a parcel is registered with the same carrier tracking number, this pre-alert is linked automatically.</span></div>
    <div class="row g8"><button type="submit" class="sl-btn">Submit pre-alert</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div>
  </form>
</div>
<?php endif; ?>
<?php if ($d['openForm'] && $d['canAdd']): ?><script>document.addEventListener('DOMContentLoaded',function(){var d=document.getElementById('pa-new');if(d){d.hidden=false;var f=d.querySelector('input[name=tracking]');if(f)f.focus();}});</script><?php endif; ?>
