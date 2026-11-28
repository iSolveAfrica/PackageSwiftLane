<?php
/**
 * Customer care alerts for staff who answer chats (cs:agent): a pop-up at the side of every page, with a chime,
 * when a customer chat is waiting for a person or a customer writes on a chat you have taken.
 * Behaviour: dataJs/sl/cs_alerts.js. Styles: assets/swiftlane/cs_alerts.css. Data: ajax/support/desk_ajax.php?action=alerts.
 */
$csaCfg = ['url' => 'ajax/support/desk_ajax.php', 'ticket' => sl_route('CsTicket') . (strpos(sl_route('CsTicket'), '?') === false ? '?' : '&') . 'id=', 'canAccept' => true];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/cs_alerts.css')) ?>">
<aside class="csa" id="csa" aria-label="Customer chats" hidden>
  <div class="csa-top"><b><i class="ic ic-sm i-message" aria-hidden="true"></i>Customer chats <em data-csa-n></em></b>
    <button type="button" class="csa-desk" data-csa-desk hidden>Desktop alerts</button>
    <button type="button" class="csa-mute" data-csa-mute aria-pressed="false"><i class="ic ic-sm i-bell" aria-hidden="true"></i><span>Sound on</span></button></div>
  <div class="csa-list" data-csa-list></div>
</aside>
<script>window.SL_CSA = <?= json_encode($csaCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;</script>
<script defer src="<?= h(cdp_asset('dataJs/sl/cs_alerts.js')) ?>"></script>
