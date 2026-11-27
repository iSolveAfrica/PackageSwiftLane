<?php
/**
 * Tariffs - "Rates & tariffs" (shipping_tariffs.php), web layout. Read only: each card links to the page that
 * already saves it (settings_form.php?f=taxes, delivery_settings.php). Data: views/sl/data/tariffs.php.
 */
$can = $d['can'];
$rows = function (array $list): string {
    $out = '<div class="tf-list">';
    foreach ($list as $r) {
        $muted = isset($r['set']) && !$r['set'];
        $out .= '<div class="tf-row"><span class="sl-tile"><i class="ic i-' . h($r['icon']) . '" aria-hidden="true"></i></span><div class="col grow" style="min-width:0"><b class="fw5">' . h($r['t']) . '</b><span class="mut sm">' . h($r['s']) . '</span></div><b class="mono' . ($muted ? ' mut' : '') . '">' . h($r['v']) . '</b></div>';
    }
    return $out . '</div>';
};
$edit = fn(string $group, string $label) => '<a class="sl-btn ghost sm" href="' . h(sl_tariffsEditUrl($group)) . '"><i class="ic ic-sm i-edit" aria-hidden="true"></i>' . h($label) . '</a>';
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Rates &amp; tariffs</h1><p class="sl-lede">What shipments and deliveries are charged. Every figure here is the one the forms and invoices use.</p></div>
          <div class="row g8 wrap"><?php if ($d['canSend']): ?><a class="sl-btn ghost" href="<?= h(sl_route('SendPrices')) ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send prices</a><?php endif; ?><?php if ($user->cdp_hasPermission('view_tools')): ?><a class="sl-btn ghost" href="<?= h(sl_route('SettingsHub')) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Settings</a><?php endif; ?></div>
        </div>
        <div class="tf-grid">
          <section class="sl-card col g12">
            <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Freight</h2><span class="mut sm">Base price of every shipment</span></div><?= $can['taxes'] ? $edit('taxes', 'Edit') : '' ?></div>
            <?= $rows($d['freight']) ?>
            <span class="mut sm">New shipments, package intake and invoices start from this price. Staff can still change the price on a single shipment.</span>
            <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Route weight bands</h2><span class="mut sm">Used by the public rate calculator</span></div><a class="sl-btn ghost sm" href="<?= h(sl_route('QuoteCalculator')) ?>">Open calculator</a></div>
<?php if ($d['bands']): ?>
            <?= $rows($d['bands']) ?>
<?php else: ?>
            <div class="sl-empty">No route prices are set, so the public rate calculator cannot give a price yet. This system has no screen to add them.</div>
<?php endif; ?>
          </section>
          <section class="sl-card col g12">
            <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Taxes and insurance</h2><span class="mut sm">Added on top of freight</span></div><?= $can['taxes'] ? $edit('taxes', 'Edit') : '' ?></div>
            <?= $rows($d['charges']) ?>
            <span class="mut sm">0% means the charge is not applied.</span>
          </section>
          <section class="sl-card col g12">
            <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Door delivery</h2><span class="mut sm"><?= $d['deliveryOn'] ? 'Charged when a customer asks for delivery' : 'Turned off' ?></span></div><?= $can['delivery'] ? $edit('delivery', 'Edit') : '' ?></div>
<?php if (!$d['deliveryOn']): ?>
            <div class="sl-empty">Door delivery is off. Customers collect at the hub.</div>
<?php else: ?>
            <?= $d['delivery'] ? $rows($d['delivery']) : '' ?>
<?php if ($d['zoneMode']): ?>
            <h3 class="sl-ct" style="font-size:15px">Delivery zones</h3>
            <?= $d['zones'] ? $rows($d['zones']) : '<div class="sl-empty">No active zones. Add a zone with its flat fee to price deliveries by area.</div>' ?>
<?php endif; ?>
<?php endif; ?>
          </section>
        </div>
