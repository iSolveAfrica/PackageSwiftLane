<?php
/**
 * M-Rates: rates & tariffs. Same $d as the web page (views/sl/data/tariffs.php). Read only: "Edit" opens the
 * page that already saves each group (settings_form.php?f=taxes, delivery_settings.php).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$can = $d['can'];
$slPhone['tab'] = 'Menu';
$row = function (array $r): string {
    $muted = isset($r['set']) && !$r['set'];
    return sl_mxRow($r['icon'], $r['t'], $r['s'], '<b' . ($muted ? ' class="sp-off"' : '') . '>' . h($r['v']) . '</b>');
};
$edit = fn(string $group, string $label) => '<div class="mx-btns"><a class="mx-btn" href="' . h(sl_tariffsEditUrl($group)) . '">' . h($label) . '</a></div>';
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-staff.css')) ?>">
<?= sl_phoneTop('Rates & tariffs', '', ['back' => $user->cdp_hasPermission('view_tools') ? sl_route('SettingsHub') : 'menu.php'], $d['canSend'] ? sl_phoneIcon('send', 'Send prices to customers', sl_route('SendPrices')) : '') ?>
<main class="mx-body" data-mx-scope>
<div class="mx-seg" data-mx-seg role="group" aria-label="Rate group">
  <button type="button" class="on" aria-pressed="true" data-mx-panel="freight">Freight</button>
  <button type="button" aria-pressed="false" data-mx-panel="charges">Taxes and insurance</button>
  <button type="button" aria-pressed="false" data-mx-panel="delivery">Delivery</button>
</div>
<div class="sp-panel sp-wrap" data-mx-panel-of="freight">
  <section class="mx-card mx-list"><?php foreach ($d['freight'] as $r) echo $row($r); ?><?php foreach ($d['bands'] as $r) echo $row($r); ?></section>
  <p class="mx-note">New shipments, package intake and invoices start from the freight price.<?= $d['bands'] ? ' The public rate calculator quotes from the route prices.' : ' No route prices are set, so the public rate calculator cannot quote yet.' ?></p>
  <?php if ($can['taxes']) echo $edit('taxes', 'Edit rates'); ?>
</div>
<div class="sp-panel sp-wrap" data-mx-panel-of="charges" hidden>
  <section class="mx-card mx-list"><?php foreach ($d['charges'] as $r) echo $row($r); ?></section>
  <p class="mx-note">Added on top of freight when a shipment is priced. 0% means the charge is not applied.</p>
  <?php if ($can['taxes']) echo $edit('taxes', 'Edit taxes and insurance'); ?>
</div>
<div class="sp-panel sp-wrap" data-mx-panel-of="delivery" hidden>
<?php if (!$d['deliveryOn']): ?>
  <section class="mx-card"><?= sl_mxEmpty('truck', 'Door delivery is off', 'Customers collect at the hub. Turn delivery on to set its prices.') ?></section>
<?php else: ?>
<?php if ($d['delivery']): ?><section class="mx-card mx-list"><?php foreach ($d['delivery'] as $r) echo $row($r); ?></section><?php endif; ?>
<?php if ($d['zoneMode']): ?>
  <div class="mx-sec"><h2>Delivery zones</h2></div>
<?php if ($d['zones']): ?>
  <section class="mx-card mx-list"><?php foreach ($d['zones'] as $r) echo $row($r); ?></section>
<?php else: ?>
  <section class="mx-card"><?= sl_mxEmpty('pin', 'No active zones', 'Add a zone with its flat fee to price deliveries by area.') ?></section>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
  <?php if ($can['delivery']) echo $edit('delivery', 'Edit delivery prices'); ?>
</div>
</main>
