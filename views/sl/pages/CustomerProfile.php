<?php
/**
 * CustomerProfile - from docs/handover/artifact-source/CustomerProfile.dc.html (customer 360 for staff).
 * Data: views/sl/data/customer_profile.php. Writes: ajax/sl/customer_status_ajax.php (approve/block/unblock),
 * ajax/sl/customer_profile_ajax.php (addresses, note, alerts, delete), ajax/customers/customers_document_edit_ajax.php
 * (ID card front / back photos; viewer and upload dialogs from sl_idDialogsHtml in views/sl/data/account_edit.php).
 */
if (!isset($d)) { $d = []; }
$tabs = ['all' => 'Everything', 'ship' => 'Shipments', 'pkg' => 'Packages', 'pay' => 'Payments', 'msg' => 'Messages', 'recip' => 'Recipients'];
$heads = ['ship' => ['Tracking', 'Date', 'Destination', 'Weight', 'Amount', 'Status'], 'pkg' => ['Tracking', 'Date', 'Bay', 'Weight', 'Amount', 'Status'], 'pay' => ['Invoice', 'Date', 'Payment', '', 'Amount', 'Status'], 'msg' => ['Channel', 'Sent', 'Message', '', 'Source', 'Status'], 'recip' => ['Recipient', '', 'Phone', 'E-mail', 'Shipments', '']];
?>
        <?php
          require_once CDP_ROOT . '/helpers/sl_chart_interactions.php';
          $idd = $d['doc'];
          $cpAct = (array) ($d['parcelActivity'] ?? []);
          $cpBars = $cpAct && array_sum($cpAct) ? sl_sparkBars(array_values($cpAct), 4, array_map(fn($x) => date('D j M', strtotime($x)), array_keys($cpAct))) : [];
          $cpYear = max(1, (int) $d['year']);
          $cpPic = $d['avatar'] !== '' ? 'background-image:url(' . h($d['avatar']) . ');background-size:cover;' : 'background:var(--ink);';
        ?>
        <link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/overview-hero.css')) ?>">
        <a href="<?= sl_route('Customers') ?>" class="row g6 mut sm"><i class="ic ic-sm i-left" aria-hidden="true"></i>Customers</a>
        <section class="ovh-me cp-hero" aria-label="Customer <?= h($d['name']) ?>">
          <div class="ovh-me-top">
            <span class="me-pic" style="width:72px;height:72px;font-size:26px;<?= $cpPic ?>"><?= $d['avatar'] !== '' ? '' : h($d['ini']) ?></span>
            <div class="col grow" style="min-width:0">
              <span class="ovh-eyebrow">Customer<?= $d['locker'] !== '—' ? ' · Locker ' . h($d['locker']) : '' ?></span>
              <div class="row g8 wrap"><h1 class="ovh-name"><?= h($d['name']) ?></h1><span class="sl-pill <?= h($d['st'][1]) ?>"><?= h($d['st'][0]) ?></span><?php if ($d['phoneV']): ?><span class="sl-badge ok">Phone verified</span><?php endif; ?><?php if ($idd['front'] !== '' || $idd['back'] !== ''): $idFull = $idd['front'] !== '' && ($idd['back'] !== '' || !$idd['hasBackCol']); ?><a href="#cp-id" class="sl-badge <?= $idFull ? ($d['approve'] ? 'ok' : '') : 'warn' ?>"><?= $idFull ? 'ID on file' : ($idd['front'] === '' ? 'ID front missing' : 'ID back missing') ?></a><?php endif; ?></div>
              <span class="mut sm">Customer since <?= h($d['since']) ?><?= $d['city'] ? ' · ' . h($d['city']) : '' ?><?= $d['company'] ? ' · ' . h($d['company']) : '' ?> · <?= h($d['mail']) ?><?= $d['phone'] !== '—' ? ' · ' . h($d['phone']) : '' ?></span>
            </div>
            <div class="row g8 wrap">
              <a class="sl-btn ghost sm" href="<?= sl_route('ComposeMessage', ['to' => $d['id']]) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Message</a>
              <?php if ($d['canEdit']): ?><a class="sl-btn ghost sm" href="customers_edit.php?user=<?= (int) $d['id'] ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><?php if ($d['blocked']): ?><button class="sl-btn ghost sm" data-sl-action="ajax/sl/customer_status_ajax.php" data-p-op="unblock" data-p-id="<?= (int) $d['id'] ?>" data-sl-reload><i class="ic ic-sm i-check" aria-hidden="true"></i>Unblock</button><?php else: ?><button class="sl-btn ghost sm" style="color:var(--red-ink)" data-sl-open="cp-block"><i class="ic ic-sm i-ban" aria-hidden="true"></i>Block</button><?php endif; ?><?php endif; ?>
              <?php if ($d['canShip']): ?><a class="sl-btn sm" href="<?= sl_route('NewShipment', ['sender' => $d['id']]) ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a><?php endif; ?>
            </div>
          </div>
          <?php if ($d['canViewAs']): ?>
          <div class="customer-next cp-viewas">
            <div><small>Customer view</small><h2>See exactly what <?= h($d['first']) ?> sees</h2><p>Open their portal: home page, parcels, bills and pickup codes. Your visit is recorded.</p></div>
            <a class="sl-btn" href="view_as.php?id=<?= (int) $d['id'] ?>" data-sl-confirm="View the portal as <?= h($d['name']) ?>? This is recorded."><i class="ic ic-sm i-eye" aria-hidden="true"></i>View as customer</a>
          </div>
          <?php endif; ?>
          <div class="ovh-me-mid">
            <div class="ovh-ring" style="--p:<?= sl_pct($d['deliveredYear'], $cpYear) ?>" role="img" aria-label="<?= (int) $d['deliveredYear'] ?> of <?= (int) $d['year'] ?> parcels this year delivered"><b><?= (int) $d['deliveredYear'] ?></b><span>of <?= (int) $d['year'] ?> delivered<br>this year</span></div>
            <dl class="ovh-stats">
              <div><dt>On the way</dt><dd><b><?= sl_num($d['onWay']) ?></b> parcel<?= (int) $d['onWay'] === 1 ? '' : 's' ?></dd></div>
              <div><dt>At the warehouse</dt><dd><b><?= sl_num($d['waiting']) ?></b> received</dd></div>
              <div><dt>Ready for pickup</dt><dd><b class="<?= (int) $d['ready'] ? 'ok' : '' ?>"><?= sl_num($d['ready']) ?></b> waiting</dd></div>
              <div><dt>Lifetime parcels</dt><dd><b><?= sl_num($d['nShip'] + $d['nPkg']) ?></b> <?= (int) $d['nShip'] ?> air · <?= (int) $d['nPkg'] ?> sea</dd></div>
              <?php if ($d['finance']): ?><div><dt>Total spend</dt><dd><b><?= h($d['spend']) ?></b></dd></div>
              <div><dt>Balance due</dt><dd><b class="<?= $d['dueN'] > 0 ? 'bad' : '' ?>"><?= h($d['due']) ?></b></dd></div><?php endif; ?>
            </dl>
          </div>
          <?php if ($cpBars): ?>
          <div class="ovh-me-bars">
            <div class="row between sm"><span class="mut">Parcels registered, 14 days · <?= sl_num(array_sum($cpAct)) ?> in total</span></div>
            <div class="ovh-bars violet" role="img" aria-label="Parcels registered per day, last 14 days"><?php $n = count($cpBars); foreach ($cpBars as $i => $bb): ?><i class="<?= $i === $n - 1 ? 'now' : '' ?>" style="<?= h($bb['s']) ?>" title="<?= h($bb['t']) ?>"></i><?php endforeach; ?></div>
          </div>
          <?php endif; ?>
          <div class="ovh-chips">
            <a href="<?= h(sl_url(['tab' => 'ship', 'page' => null, 'k' => null, 'n' => null])) ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>Last shipment <b><?= h($d['lastShip']) ?></b></a>
            <a href="<?= h(sl_url(['tab' => 'all', 'k' => 'account', 'n' => null])) ?>#everything"><i class="ic ic-sm i-shield" aria-hidden="true"></i>Last sign-in <b><?= h($d['lastLogin']) ?></b><?= $d['lastDevice'] ? ' · ' . h($d['lastDevice']) : '' ?></a>
            <a href="<?= h(sl_route('CsCustomer', ['id' => $d['id']])) ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>Open support <b><?= (int) $d['openTickets'] ?></b></a>
            <a href="<?= h(sl_route('AirShipments', ['q' => $d['locker']])) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>All their shipments</a>
          </div>
        </section>
        <?php if ($d['block']): ?><div class="sl-banner" style="background:var(--red-bg);color:var(--red-ink);border-color:transparent"><i class="ic i-ban" aria-hidden="true"></i><span class="grow"><b class="fw5">Blocked</b> · <?= h($d['block']['reason']) ?> · by <?= h($d['block']['by']) ?>, <?= h($d['block']['at']) ?>. Log-in, pickups and offers are off.</span></div><?php elseif ($d['blocked']): ?><div class="sl-banner" style="background:var(--red-bg);color:var(--red-ink);border-color:transparent"><i class="ic i-ban" aria-hidden="true"></i><span class="grow">The account is inactive.</span></div><?php endif; ?>
        <?php if (!$d['approve'] && !$d['blocked']): ?><section class="sl-card row g12 wrap" style="align-items:center"><span class="sl-tile" style="background:var(--amber-bg);color:var(--amber-ink)"><i class="ic i-shield" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">KYC to review</b><span class="mut sm"><?= $d['docUrl'] ? h(trim(($d['docType'] ?: 'ID') . ' ' . ($d['docNo'] ?: ''))) . ' uploaded' . ($idd['hasBackCol'] && $idd['back'] === '' ? ' (front only, the back is missing)' : '') . '. Check the photos, then approve.' : ($idd['back'] !== '' ? 'Only the back of the ID is uploaded.' : 'No ID document uploaded yet.') ?></span></div><?php if ($d['docUrl'] || $idd['back'] !== ''): ?><button type="button" class="sl-btn ghost" data-idv-open="<?= $d['docUrl'] ? 'front' : 'back' ?>"><i class="ic ic-sm i-eye" aria-hidden="true"></i>View ID card</button><?php endif; ?><?php if ($d['canApprove']): ?><button class="sl-btn" data-sl-action="ajax/sl/customer_status_ajax.php" data-p-op="approve" data-p-id="<?= (int) $d['id'] ?>" data-sl-confirm="Approve <?= h($d['name']) ?>?" data-sl-reload><i class="ic ic-sm i-check" aria-hidden="true"></i>Approve</button><?php endif; ?></section><?php endif; ?>
        <?php if (!empty($d['tracking']['items'])): $tc = $d['tracking']; $tcTitle = 'Parcels on the move'; $tcNoun = 'parcels'; $tcNavigate = true; $tcExpand = sl_route('AirShipments', ['q' => $d['locker']]); ?>
        <?php include __DIR__ . '/partials/track_console.php'; ?>
        <?php else: ?>
        <section class="sl-card row g12" style="align-items:center"><span class="sl-tile"><i class="ic i-route" aria-hidden="true"></i></span><div class="col grow"><b class="fw5">Parcels on the move</b><span class="mut sm">Nothing on the way for <?= h($d['first']) ?> right now.</span></div></section>
        <?php endif; ?>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card flush">
              <div class="row between wrap g12" style="padding: 14px 16px"><div class="sl-seg boxed"><?php foreach ($tabs as $k => $l): ?><a class="<?= $k === $d['tab'] ? 'on' : '' ?>" href="<?= h(sl_url(['tab' => $k, 'page' => null, 'k' => null, 'n' => null])) ?>"><?= $l ?><?php $tc = $k === 'all' ? ($d['timeline']['all'] ?? null) : ($d['tabCounts'][$k] ?? null); if ($tc !== null): ?> <b class="u3-n"><?= sl_num($tc) ?></b><?php endif; ?></a><?php endforeach; ?></div><?php if ($d['tab'] !== 'all'): ?><a href="<?= $d['tab'] === 'pkg' ? sl_route('SeaPackages', ['q' => $d['locker']]) : sl_route('AirShipments', ['q' => $d['locker']]) ?>" class="sm fw5 row g6">View all <i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?></div>
              <?php if ($d['tab'] === 'all'): ?><div style="padding:0 16px 16px"><?php $tl = $d['timeline']; $tlAud = 'staff'; $tlAnchor = 'everything'; include __DIR__ . '/partials/user_timeline.php'; ?></div><?php else: ?>
              <div class="sl-tw"><table class="sl-table"><thead><tr><?php foreach ($heads[$d['tab']] as $i => $hh): ?><th class="<?= $i === 3 || $i === 4 ? 'num' : '' ?>"><?= h($hh) ?></th><?php endforeach; ?></tr></thead><tbody>
                <?php foreach ($d['rows'] as $r): ?><tr><td><a class="id" href="<?= h($r['href']) ?>"><?= h($r['trk']) ?></a></td><td><?= h($r['date']) ?></td><td><?= h($r['dest']) ?></td><td class="num"><?= h($r['kg']) ?></td><td class="num"><?= h($r['amt']) ?></td><td><?php if ($r['st']): ?><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span><?php endif; ?></td></tr><?php endforeach; ?>
                <?php if (!$d['rows']): ?><tr><td colspan="6"><div class="sl-empty"><span class="sl-tile"><i class="ic i-box" aria-hidden="true"></i></span>Nothing here yet.</div></td></tr><?php endif; ?>
              </tbody></table></div>
              <?= $d['count'] > $d['per'] ? sl_pager($d['page'], $d['per'], $d['count'], 'rows') : '' ?>
              <?php endif; ?>
            </section>
            <?php $heat = $d['heat']; $heatTitle = 'Their shipping rhythm'; include __DIR__ . '/partials/user_heatmap.php'; ?>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><h2 class="sl-ct">Account &amp; security</h2>
              <div class="sl-kv"><div><span>Last sign-in</span><span><?= h($d['lastLoginAt'] ?: 'Never') ?></span></div><?php if ($d['lastDevice']): ?><div><span>Device</span><span><?= h($d['lastDevice']) ?></span></div><?php endif; ?><?php if ($d['lastIp']): ?><div><span>Last IP</span><span class="mono"><?= h($d['lastIp']) ?></span></div><?php endif; ?><div><span>Trusted devices</span><span><?= (int) $d['devices'] ?></span></div><div><span>Failed sign-ins (30 days)</span><span class="<?= $d['failed30'] >= 5 ? 'u3-bad' : '' ?>"><?= (int) $d['failed30'] ?></span></div></div>
              <a class="sm fw5 row g6" href="<?= h(sl_url(['tab' => 'all', 'k' => 'account', 'n' => null])) ?>#everything">All sign-ins <i class="ic ic-sm i-right" aria-hidden="true"></i></a>
            </section>
            <?php if ($d['loyalty']): ?><section class="sl-card col g12"><h2 class="sl-ct">Rewards</h2>
              <div class="sl-kv"><div><span>Points</span><span class="mono"><?= sl_num($d['loyalty']['pts']) ?></span></div><div><span>Lifetime</span><span class="mono"><?= sl_num($d['loyalty']['life']) ?></span></div><?php if ($d['loyalty']['tier']): ?><div><span>Tier</span><span><?= h($d['loyalty']['tier']) ?></span></div><?php endif; ?><?php if ($d['loyalty']['code']): ?><div><span>Referral code</span><span class="mono"><?= h($d['loyalty']['code']) ?></span></div><?php endif; ?></div>
            </section><?php endif; ?>
            <section class="sl-card col g12"><h2 class="sl-ct">Contact</h2>
              <div class="sl-kv"><div><span>Email</span><span><?= h($d['mail']) ?></span></div><div><span>Phone</span><span><?= h($d['phone']) ?></span></div><div><span>Username</span><span class="mono"><?= h($d['username']) ?></span></div><div><span>Newsletter</span><span class="row g8 end"><span class="sl-toggle <?= $d['newsletter'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $d['newsletter'] ? 'true' : 'false' ?>" aria-label="Newsletter" <?= $d['canEdit'] ? 'data-sl-save="ajax/sl/customer_profile_ajax.php" data-key="newsletter" data-p-op="pref" data-p-id="' . (int) $d['id'] . '"' : 'data-static' ?>></span></span></div></div>
            </section>
            <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Addresses</h2><?php if ($d['canEdit']): ?><button class="sl-ib sm" aria-label="Add address" data-sl-open="cp-addr"><i class="ic i-plus" aria-hidden="true"></i></button><?php endif; ?></div>
              <?php foreach ($d['addresses'] as $i => $a): ?><div class="row g12 top"><span class="sl-tile"><i class="ic i-pin" aria-hidden="true"></i></span><div class="grow"><div class="fw5">Address <?= $i + 1 ?><?php if ($i === 0): ?> <span class="sl-badge" style="margin-left: 6px">Default</span><?php endif; ?></div><div class="mut sm"><?= h($a['line'] ?: '—') ?></div></div><?php if ($d['canEdit'] && !$a['used']): ?><button class="sl-ib sm" aria-label="Remove address" data-sl-action="ajax/sl/customer_profile_ajax.php" data-p-op="address_delete" data-p-id="<?= (int) $d['id'] ?>" data-p-address_id="<?= (int) $a['id'] ?>" data-sl-confirm="Remove this address?" data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button><?php endif; ?></div><?php endforeach; ?>
              <?php if (!$d['addresses']): ?><span class="mut sm">No address on file.</span><?php endif; ?>
            </section>
            <section class="sl-card col g12" id="cp-id" style="scroll-margin-top:24px"><div class="row between g8"><h2 class="sl-ct">ID card</h2><?= sl_idDocPill($idd) ?></div>
              <div class="row g12"><span class="sl-tile"><i class="ic i-card" aria-hidden="true"></i></span><div class="grow" style="min-width:0"><div class="fw5"><?= h($d['docType'] ?: 'ID type not set') ?></div><div class="mut sm mono"><?= h($d['docNo'] ?: 'No number on file') ?></div></div><?php if ($idd['front'] !== '' || $idd['back'] !== ''): ?><span class="sl-badge <?= $d['approve'] ? 'ok' : '' ?>"><?= $d['approve'] ? 'OK' : 'To review' ?></span><?php endif; ?></div>
              <?= sl_idSlotsHtml($idd, ['canUpload' => $d['canDocument'], 'size' => 'sm']) ?>
              <?php foreach ($d['hist'] as $hh): ?><span class="mut sm">Changed <?= h($hh['at']) ?> by <?= h($hh['by'] ?: 'customer') ?><?= $hh['why'] ? ' · ' . h($hh['why']) : '' ?></span><?php endforeach; ?></section>
            <?php if ($d['canEdit']): ?><form class="sl-card col g8" data-sl-form action="ajax/sl/customer_profile_ajax.php" method="post"><h2 class="sl-ct">Staff note</h2><input type="hidden" name="op" value="note"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><textarea class="sl-in" name="notes" rows="3" placeholder="Visible to staff only"><?= h($d['notes']) ?></textarea><button class="sl-btn ghost sm" type="submit">Save note</button></form><?php endif; ?>
            <?php if ($d['canDelete']): ?><button class="sl-btn ghost sm" style="color:var(--red-ink)" data-sl-action="ajax/sl/customer_profile_ajax.php" data-p-op="delete" data-p-id="<?= (int) $d['id'] ?>" data-sl-confirm="Delete <?= h($d['name']) ?> permanently? Only possible without shipments."><i class="ic ic-sm i-trash" aria-hidden="true"></i>Delete customer</button><?php endif; ?>
          </aside>
        </div>
<?php if ($d['canEdit']): ?>
<div class="sl-dialog" id="cp-block" hidden><form class="sl-modal col g12" data-sl-form data-sl-reload action="ajax/sl/customer_status_ajax.php" method="post" role="dialog" aria-label="Block customer"><input type="hidden" name="op" value="block"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
<div class="row g10"><span class="sl-tile" style="background:var(--red-bg);color:var(--red-ink)"><i class="ic i-ban" aria-hidden="true"></i></span><div class="col"><b class="fw5">Block <?= h($d['name']) ?>?</b><span class="mut sm">They can't log in, book pickups or get offers.</span></div></div>
<label class="sl-field"><span class="sl-lab">Reason</span><span class="sl-sel native"><select name="reason"><?php foreach (['Fake payment receipt', 'Abusive to staff', 'Unpaid over 90 days', 'Prohibited items', 'Duplicate account'] as $r): ?><option><?= h($r) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
<label class="row g8 sm"><span class="sl-toggle on" role="switch" aria-checked="true" aria-label="Hold parcels"><input type="hidden" name="hold" value="1"></span>Hold their parcels at the warehouse</label><label class="row g8 sm"><span class="sl-toggle" role="switch" aria-checked="false" aria-label="Notify"><input type="hidden" name="notify" value="0"></span>Tell them by e-mail why</label>
<div class="row g8"><button class="sl-btn" type="submit" style="background:var(--red);border-color:var(--red);color:#fff"><i class="ic ic-sm i-ban" aria-hidden="true"></i>Block customer</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<div class="sl-dialog" id="cp-addr" hidden><form class="sl-modal col g12" data-sl-form data-sl-reload action="ajax/sl/customer_profile_ajax.php" method="post" role="dialog" aria-label="Add address"><input type="hidden" name="op" value="address_add"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><b class="fw5">Add an address</b>
<label class="sl-field"><span class="sl-lab">Address</span><input class="sl-in" name="address" required></label><div class="grid cols2" style="gap:10px"><label class="sl-field"><span class="sl-lab">Country</span><span class="sl-sel native"><select name="country" required><option value="">Choose</option><?php foreach ($d['countries'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><label class="sl-field"><span class="sl-lab">Postal code</span><input class="sl-in" name="zip_code"></label><label class="sl-field"><span class="sl-lab">State (id)</span><input class="sl-in" name="state" inputmode="numeric" placeholder="optional"></label><label class="sl-field"><span class="sl-lab">City (id)</span><input class="sl-in" name="city" inputmode="numeric" placeholder="optional"></label></div>
<div class="row g8"><button class="sl-btn" type="submit">Add address</button><button type="button" class="sl-btn ghost" data-sl-close>Cancel</button></div></form></div>
<?php endif; ?>
<?= sl_idDialogsHtml($idd, ['canUpload' => $d['canDocument'], 'endpoint' => 'ajax/customers/customers_document_edit_ajax.php', 'hidden' => ['id' => (int) $d['id']], 'fields' => ['front' => 'document', 'back' => 'document'], 'sideParam' => 'side']) ?>
