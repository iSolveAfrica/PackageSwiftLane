<?php
/**
 * M-CParcel body, shared by PackageDetail.customer.php (sea package) and ShipmentDetail.customer.php (air
 * shipment). Each sets $p (normalised from its own $d) and includes this file. Only data the web page shows
 * the customer is printed.
 * $p: scene (sl_parcelScene(), the status picture), back, trk, st, stCls, mode, from, to, pct, steps[], step (0-based), events[{t,s}] newest first, tags[[label,cls]],
 *     acts[[label,icon,href,cls]], items[{qty,desc,w,val}], kv[[label,value]], files[{name,url,img}],
 *     pay[[label,value]], payLink, code ('ready'|'verified'|'done'|''), driver{name,ini,phone}|null, publicUrl, deliver(bool)
 */
$slPhone['nav'] = 'none';
$pct = max(0, min(100, (int) $p['pct']));
// Route on the map: quadratic curve (96,132) → (300,262), control (214,96); the plane sits at $pct.
$t = max(0.04, min(0.96, $pct / 100));
$px = (1 - $t) ** 2 * 96 + 2 * (1 - $t) * $t * 214 + $t ** 2 * 300;
$py = (1 - $t) ** 2 * 132 + 2 * (1 - $t) * $t * 96 + $t ** 2 * 262;
$events = array_reverse((array) $p['events']);
$todo = array_slice((array) $p['steps'], (int) $p['step'] + 1);
$sum = ['qty' => 0];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-auth.css')) ?>">
<?= sl_phoneTop('Shipment tracking', '', ['back' => $p['back']], '<a class="mx-ib" href="#cp-more" aria-label="More actions"><i class="ic i-more" aria-hidden="true"></i></a>') ?>
<main class="mx-body">
<div class="b1-trk">
<div class="b1-tmap"><svg viewBox="0 0 390 380" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="390" height="380" fill="#DCE5E8"/><g fill="#F1EEE8" stroke="#DED8CD" stroke-width="1.5"><path d="M-20 52 C 30 38, 92 44, 124 84 C 146 112, 128 150, 100 170 C 80 186, 70 212, 46 226 C 24 238, 0 236, -20 240Z"/><path d="M40 262 C 82 250, 126 268, 140 300 C 148 330, 124 362, 104 400 L 52 400 C 50 360, 36 300, 40 262Z"/><path d="M252 32 C 292 20, 352 30, 410 52 L 410 120 C 370 132, 330 116, 300 126 C 282 108, 250 76, 252 32Z"/><path d="M262 160 C 302 146, 366 148, 410 168 L 410 400 L 336 400 C 326 350, 306 316, 296 290 C 280 268, 248 222, 262 160Z"/></g><g stroke="#FFFFFF" stroke-opacity=".55" stroke-width="1"><path d="M0 95H390M0 190H390M0 285H390M98 0V380M195 0V380M292 0V380"/></g><path d="M96 132 Q 214 96 300 262" fill="none" stroke="#161615" stroke-opacity=".35" stroke-width="2.5" stroke-dasharray="3 7" stroke-linecap="round"/><path d="M96 132 Q 214 96 300 262" pathLength="100" fill="none" stroke="#D9792B" stroke-width="4" stroke-linecap="round" stroke-dasharray="<?= $pct ?> 100"/><circle cx="96" cy="132" r="8" fill="#161615" stroke="#fff" stroke-width="3"/><g transform="translate(300 262)"><ellipse cx="0" cy="2" rx="11" ry="5" fill="#D9792B" stroke="#fff" stroke-width="2"/><path d="M0 0c-9-12-15-18-15-26a15 15 0 0 1 30 0c0 8-6 14-15 26z" fill="#E5484D" stroke="#fff" stroke-width="2"/><circle cx="0" cy="-26" r="5.5" fill="#fff"/></g><?php if ($pct > 0 && $pct < 100): ?><g transform="translate(<?= round($px, 1) ?> <?= round($py, 1) ?>)"><circle r="17" fill="#fff" stroke="#161615" stroke-width="2"/><?php if ($p['mode'] === 'Air'): ?><path d="M-8 1.5l5-.5 3.5 6h2.5l-1.8-6.4 4.3-.4 1.6 2h2l-1-3 1-3h-2l-1.6 2-4.3-.4 1.8-6.4h-2.5l-3.5 6-5-.5c-1.2 0-1.2 3.6 0 3.6z" fill="#161615" transform="rotate(30)"/><?php else: ?><circle r="6" fill="#D9792B"/><?php endif; ?></g><?php endif; ?></svg>
<span class="b1-mlab" style="left:58px;top:146px"><?= h($p['from']) ?></span><span class="b1-mlab" style="left:180px;top:276px"><?= h($p['to']) ?></span><span class="b1-mchip"><i class="ic ic-sm <?= $p['mode'] === 'Air' ? 'i-plane' : 'i-ship' ?>" aria-hidden="true"></i><span><b><?= $pct ?>%</b> of the way</span></span></div>
<section class="b1-sh"><div class="b1-grab"></div><div class="b1-oh"><div><b class="b1-oid">#<?= h($p['trk']) ?></b><span class="b1-rt"><?= h($p['from']) ?><i class="ic ic-sm i-arrow" aria-hidden="true"></i><?= h($p['to']) ?></span></div><span class="b1-st <?= h($p['stCls']) ?>"><?= h($p['st']) ?></span></div>
<ol class="b1-tl">
<?php foreach ($events as $i => $e): ?><li class="<?= $i === count($events) - 1 ? 'cur' : 'done' ?>"><i></i><div><b><?= h($e['t']) ?></b><small><?= h($e['s']) ?></small></div></li><?php endforeach; ?>
<?php if (!$events): ?><li class="cur"><i></i><div><b><?= h($p['st']) ?></b><small>No tracking events yet</small></div></li><?php endif; ?>
<?php foreach ($todo as $k => $label): ?><li class="todo"><i></i><div><b><?= h($label) ?></b><small><?= $k === count($todo) - 1 && ($p['eta'] ?? '') !== '' && ($p['eta'] ?? '') !== 'Not set' ? 'Expected ' . h($p['eta']) : 'Pending' ?></small></div></li><?php endforeach; ?>
</ol></section>
</div>
<div class="b1-pad">
<?php if (!empty($p['scene'])): $sc = $p['scene']; $scDone = $p['code'] === 'done'; ?><div class="b1-scene"><?php include CDP_ROOT . '/views/sl/pages/partials/parcel_scene.php'; ?></div><?php endif; ?>
<div class="b1-tags"><?php foreach ((array) $p['tags'] as $tg): ?><span class="b1-tag <?= h($tg[1]) ?>"><?= h($tg[0]) ?></span><?php endforeach; ?></div>
<section class="b1-acts"><?php foreach ((array) $p['acts'] as $a): ?><a class="<?= h($a[3]) ?>" href="<?= h($a[2]) ?>"<?= !empty($a[4]) ? ' target="_blank" rel="noopener"' : '' ?>><span><i class="ic <?= h($a[1]) ?>" aria-hidden="true"></i></span><?= h($a[0]) ?></a><?php endforeach; ?></section>
<?php if ($p['items']): ?>
<div class="b1-shd"><h2>Items</h2></div>
<section class="b1-tb"><div class="b1-tr" style="grid-template-columns:34px 1fr 62px 76px"><span>Qty</span><span>Description</span><span class="r">Weight</span><span class="r">Declared</span></div>
<?php foreach ((array) $p['items'] as $it): ?><div class="b1-tr" style="grid-template-columns:34px 1fr 62px 76px"><span><?= h((string) $it['qty']) ?></span><span><?= h($it['desc']) ?></span><span class="r"><?= h($it['w']) ?></span><span class="r"><?= h($it['val']) ?></span></div><?php endforeach; ?>
<div class="b1-tr tot" style="grid-template-columns:34px 1fr 62px 76px"><span><?= h((string) array_sum(array_map(fn($x) => (float) $x['qty'], (array) $p['items']))) ?></span><span>Total</span><span class="r"><?= h($p['weight']) ?></span><span class="r"></span></div></section>
<?php endif; ?>
<?= sl_mxSec('Sender and recipient') ?>
<section class="mx-card mx-kv"><?php foreach ((array) $p['kv'] as $kv): if ((string) $kv[1] === '') continue; ?><div><span><?= h($kv[0]) ?></span><b><?= h($kv[1]) ?></b></div><?php endforeach; ?></section>
<?php if ($p['files']): ?>
<div class="b1-shd"><h2>Photos and documents</h2></div>
<div class="b1-thumbs"><?php foreach (array_slice((array) $p['files'], 0, 8) as $f): ?><a href="<?= h($f['url']) ?>" target="_blank" rel="noopener"><i class="ic <?= !empty($f['img']) ? 'i-image' : 'i-file' ?>" aria-hidden="true"></i><small><?= h(mb_strimwidth((string) $f['name'], 0, 14, '…')) ?></small></a><?php endforeach; ?></div>
<?php endif; ?>
<?= sl_mxSec('Payment') ?>
<section class="mx-card mx-kv"><?php foreach ((array) $p['pay'] as $kv): if ((string) $kv[1] === '' || $kv[1] === '—') continue; ?><div><span><?= h($kv[0]) ?></span><b><?= h($kv[1]) ?></b></div><?php endforeach; ?></section>
<?php if ($p['payLink'] !== ''): ?><a class="mx-btn or" href="<?= h($p['payLink']) ?>"><i class="ic ic-sm i-card" aria-hidden="true"></i>Pay now</a><?php endif; ?>
<section class="b1-card"><b style="font-size:15px">Pickup code</b><?php if ($p['code'] === 'verified'): ?><small class="mx-note">Verified at hand-over.</small><?php elseif ($p['code'] === 'done'): ?><small class="mx-note">This parcel has been handed over.</small><?php elseif ($p['code'] === 'ready'): ?><small class="mx-note">Your code is ready. Show it with your ID at the hub.</small><a class="mx-btn sec" href="<?= h(sl_route('PickupCodes')) ?>"><i class="ic ic-sm i-key" aria-hidden="true"></i>Open my pickup codes</a><?php else: ?><div class="b1-code" aria-label="No pickup code yet"><span class="m">•</span><span class="m">•</span><span class="m">•</span><i></i><span class="m">•</span><span class="m">•</span><span class="m">•</span></div><small class="mx-note">It arrives when the parcel is ready for pickup. Bring it with your ID.</small><?php endif; ?></section>
<?php if (!empty($p['driver'])): $dr = $p['driver']; ?>
<section class="b1-card"><b class="b1-ct" style="margin-top:0;font-size:16px">Assigned driver</b><div class="b1-drv"><span class="b1-dav"><?= h($dr['ini']) ?></span><div><b><?= h($dr['name']) ?></b><small>Delivers this parcel</small></div><a href="support.php" aria-label="Chat with support about <?= h($dr['name']) ?>"><i class="ic i-message" aria-hidden="true"></i></a><?php if (($dr['phone'] ?? '') !== ''): ?><a class="amb" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $dr['phone'])) ?>" aria-label="Call <?= h($dr['name']) ?>"><i class="ic i-phone" aria-hidden="true"></i></a><?php endif; ?></div></section>
<?php endif; ?>
<section class="mx-card mx-list" id="cp-more">
<a class="mx-row" href="<?= h($p['publicUrl']) ?>"><span class="mx-li"><i class="ic i-link" aria-hidden="true"></i></span><span class="mx-rm"><b>Public tracking page</b><small>Share it with whoever collects</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php if (!empty($p['deliver'])): ?><a class="mx-row" href="delivery_request.php"><span class="mx-li"><i class="ic i-truck" aria-hidden="true"></i></span><span class="mx-rm"><b>Deliver to my door instead</b><small>Request a delivery</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
<a class="mx-row" href="support.php?q=<?= rawurlencode('Help with ' . $p['trk']) ?>"><span class="mx-li"><i class="ic i-help" aria-hidden="true"></i></span><span class="mx-rm"><b>Get help</b><small>About this parcel</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
</section>
</div>
</main>
<div class="mx-foot"><div class="b1-fb"><?php if (!empty($p['driver']['phone'])): ?><a class="b1-amb" href="tel:<?= h(preg_replace('/[^0-9+]/', '', $p['driver']['phone'])) ?>"><i class="ic ic-sm i-phone" aria-hidden="true"></i>Call driver</a><?php else: ?><a class="b1-amb" href="<?= h(sl_route('LiveTracking') . '?q=' . rawurlencode($p['trk'])) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Live tracking</a><?php endif; ?><a class="b1-wht" href="support.php?q=<?= rawurlencode('Help with ' . $p['trk']) ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>Chat support</a></div></div>
