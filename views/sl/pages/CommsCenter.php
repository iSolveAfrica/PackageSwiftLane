<?php
/**
 * CommsCenter - the old Communication Center address (comms_center.php).
 * Sends the browser to the page that replaced the section named after #
 * (#customers -> Customer history, #settings -> Settings, ...); anything
 * else goes to the Overview. Without JavaScript the links below do it.
 */
if (!isset($d)) { $d = []; }
$labels = ['overview' => 'Overview', 'automations' => 'Automations', 'templates' => 'Status messages', 'send' => 'Send', 'outbox' => 'Outbox',
    'customers' => 'Customer history', 'surveys' => 'Surveys', 'settings' => 'Settings'];
?>
<div class="sl-head"><div><h1 class="sl-title">The Communication Center has moved</h1><p class="sl-lede">Its sections are now pages of Notifications &amp; Messages. Taking you there…</p></div></div>
<nav class="row g8 wrap" aria-label="Notifications and messages pages">
<?php foreach ($labels as $k => $l): ?><a class="sl-btn ghost sm" href="<?= h($d['targets'][$k]) ?>"><?= h($l) ?></a><?php endforeach; ?>
</nav>
<script>
(function () {
  var t = <?= json_encode($d['targets'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
  var h = (location.hash || '').replace(/^#/, '').toLowerCase();
  location.replace(t[h] || <?= json_encode($d['home'], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>);
})();
</script>
