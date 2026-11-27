<?php
/**
 * M-Intake: package intake on a phone. $d = views/sl/data/intake.php sl_intakeData().
 * Same form, field names and data-ix-* hooks as views/sl/pages/PackageIntake.php, so dataJs/sl/package_intake.js
 * does the lookup (ajax/sl/package_intake_ajax.php) and the save (ajax/sl/package_ajax.php op=create).
 * ?tracking=… (from the pre-alert list) fills the number and looks it up.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$me = sl_phoneMe();
$slPhone['tab'] = 'Scan';
$slPhone['scripts'][] = 'dataJs/sl/package_intake.js';
$df = $d['defaults'];
$unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => (int) $_SESSION['userid']]);
$pre = mb_substr(trim((string) ($_GET['tracking'] ?? '')), 0, 80);
$opts = function (array $rows, $cur): string {
    $o = '';
    foreach ($rows as $r) $o .= '<option value="' . (int) $r->id . '"' . ((int) $r->id === (int) $cur ? ' selected' : '') . '>' . h(str_replace('_', ' ', (string) $r->label)) . '</option>';
    return $o;
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-ops.css')) ?>">
<?= sl_phoneTop('Package intake', (int) $d['todayCount'] . ' added today', ['avatar' => $me['ini'], 'href' => sl_route('Workspace'), 'online' => true], sl_phoneIcon('search', 'Search', sl_route('LockerSearch')) . sl_phoneIcon('bell', 'Notifications', sl_route('Notifications'), $unread)) ?>
<main class="mx-body">
<form id="ix-form" class="mx-nsform" action="ajax/sl/package_ajax.php" method="post" novalidate data-rates="<?= h(json_encode($d['rates'])) ?>">
<input type="hidden" name="op" value="create"><input type="hidden" name="code_prefix" value="<?= h($d['prefix']) ?>"><input type="hidden" name="prealert_id" value=""><input type="hidden" name="packages" value="[]">
<input type="hidden" name="order_service_options" value="<?= (int) $df['order_service_options'] ?>"><input type="hidden" name="order_item_category" value="27"><input type="hidden" name="notify_whatsapp" value="1">
<label class="mx-scan" for="ix-track" aria-label="Tracking number"><div class="mx-vf" aria-hidden="true"><i></i><i></i><i></i><i></i><em></em></div><b>Scan the store barcode</b><small>A barcode scanner types into the box below, or type the number</small></label>
<section class="mx-card mx-form">
<input type="hidden" name="order_courier" value="<?= (int) $df['order_courier'] ?>">
<div class="ix-carriers mx-chips" role="radiogroup" aria-label="Carrier"><?php foreach ($d['carriers'] as $c): $on = $c['id'] === $df['order_courier']; ?><button type="button" role="radio" aria-checked="<?= $on ? 'true' : 'false' ?>" class="<?= $on ? 'on' : '' ?>" data-ix-carrier="<?= (int) $c['id'] ?>" data-label="<?= h($c['label']) ?>"><?= h($c['label']) ?></button><?php endforeach; ?></div>
<div class="ix-find mx-nsfind"><input id="ix-track" class="mono" name="tracking_purchase" aria-label="Carrier tracking number" placeholder="Tracking number" autocomplete="off" autocapitalize="characters" enterkeyhint="search" value="<?= h($pre) ?>" data-ix-track><button type="button" class="mx-chipbtn on" data-ix-lookup>Look up</button></div>
<p class="mx-note" aria-live="polite" hidden data-ix-loading>Looking for a pre-alert<?= $d['ai'] ? ' and reading the items' : '' ?>…</p>
<p class="mx-note" hidden data-ix-msg></p>
</section>
<section class="mx-card mx-form" hidden data-ix-details>
<p class="mx-note"><b data-ix-conf>Check and edit</b></p>
<label class="mx-fld"><span>Find customer by locker, name or phone</span><span class="mx-nsfind"><input data-ix-cq placeholder="Locker ID or name" autocomplete="off" enterkeyhint="search"><button type="button" class="mx-chipbtn" data-ix-cfind>Find</button></span></label>
<label class="mx-fld"><span>Matched to</span><select name="sender_id" data-ix-cust required><option value="">Look up or find a customer</option></select></label>
<label class="mx-fld"><span>Customer address</span><select name="sender_address_id" data-ix-addr required><option value="">Pick the customer first</option></select></label>
<label class="mx-fld"><span>Shipped from (store)</span><input name="provider_purchase" data-ix-shop placeholder="Amazon, Shein, eBay…" required></label>
<div class="mx-two"><label class="mx-fld"><span>Weight (<?= h($d['rates']['weight']) ?>)</span><input class="mono" data-ix-w inputmode="decimal" placeholder="0.0"></label><label class="mx-fld"><span>Purchase price</span><input class="mono" name="price_purchase" data-ix-price inputmode="decimal" placeholder="0.00"></label></div>
<label class="mx-fld"><span>Size L × W × H</span><input class="mono" data-ix-dims placeholder="12 × 8 × 4" inputmode="decimal"></label>
<?php if ($d['agencyLocked']): ?><input type="hidden" name="agency" value="<?= (int) $df['agency'] ?>"><?php else: ?><label class="mx-fld"><span>Agency</span><select name="agency"><?= $opts($d['agencies'], $df['agency']) ?></select></label><?php endif; ?>
<label class="mx-fld"><span>Warehouse</span><select name="origin_off"><?= $opts($d['offices'], $df['origin_off']) ?></select></label>
<label class="mx-fld"><span>Packaging</span><select name="order_package"><?= $opts($d['packaging'], $df['order_package']) ?></select></label>
<label class="mx-fld"><span>Delivery time</span><select name="order_deli_time"><?= $opts($d['delitimes'], $df['order_deli_time']) ?></select></label>
<label class="mx-fld"><span>Estimated arrival</span><input type="date" name="estimated_eta" data-ix-eta></label>
<span class="mx-fldl">Items in the package (name · qty · value)</span>
<div class="mx-ixitems" data-ix-items></div>
<button type="button" class="mx-btn sec" data-ix-add><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add item</button>
<p class="mx-note">Declared value <b class="mono" data-ix-declared>0.00</b> · estimated <b data-p="total">—</b><?php if ($d['rates']['rate'] > 0): ?> (<span data-p="ghs">—</span>)<?php endif; ?></p>
<label class="mx-fld"><span>Notes</span><input name="notes" maxlength="500" placeholder="Box dented, opened by customs…"></label>
<div class="mx-btns"><button type="submit" class="mx-btn or">Receive package</button><button type="button" class="mx-btn sec" data-ix-new>Clear</button></div>
<p class="mx-note">The customer gets a WhatsApp and SMS that it arrived; the pickup code goes out when it is ready.</p>
</section>
</form>
<div class="mx-sec"><h2>Added today (<?= (int) $d['todayCount'] ?>)</h2><a href="<?= h(sl_route('SeaPackages')) ?>">All</a></div>
<?php if ($d['recent']): ?>
<section class="mx-card mx-list">
<?php foreach ($d['recent'] as $r): ?><a class="mx-row" href="<?= h(sl_route('PackageDetail', ['id' => $r['id']])) ?>"><span class="mx-li"><i class="ic i-check" aria-hidden="true"></i></span><span class="mx-rm"><b class="mono"><?= h($r['track']) ?></b><small><?= h($r['name'] . ' · ' . $r['w']) ?></small></span></a><?php endforeach; ?>
</section>
<?php else: ?><?= sl_mxEmpty('box', 'Nothing received today yet') ?><?php endif; ?>
</main>
<div class="sl-dialog mx-ixdone" id="ix-done" hidden>
<div class="mx-card mx-form" role="dialog" aria-label="Package added">
<b style="font-size:18px">Package added</b><p class="mx-note" data-ix-donesub></p><span class="sl-pill delivered" data-ix-doneprice></span>
<div class="mx-chips" role="radiogroup" aria-label="Print">
<?php if ($d['canLabel']): ?><button type="button" role="radio" aria-checked="true" class="on" data-ix-print="label">Label 4 × 6<span class="sl-chk on" hidden></span></button><button type="button" role="radio" aria-checked="false" data-ix-print="small">Shelf label<span class="sl-chk" hidden></span></button><?php endif; ?>
<?php if ($d['canReceipt']): ?><button type="button" role="radio" aria-checked="<?= $d['canLabel'] ? 'false' : 'true' ?>" class="<?= $d['canLabel'] ? '' : 'on' ?>" data-ix-print="receipt">Receipt<span class="sl-chk" hidden></span></button><?php endif; ?>
</div>
<iframe title="Print preview" data-ix-preview hidden></iframe>
<div class="mx-btns"><a class="mx-btn or" target="_blank" rel="noopener" href="#" data-ix-printgo><i class="ic ic-sm i-print" aria-hidden="true"></i>Print</a><a class="mx-btn sec" href="#" data-ix-open>Open package</a><button type="button" class="mx-btn sec" data-ix-new>Receive the next one</button></div>
</div>
</div>
<?php if ($pre !== ''): ?><script>document.addEventListener('DOMContentLoaded', function () { var b = document.querySelector('[data-ix-lookup]'); if (b) b.click(); });</script><?php endif; ?>
