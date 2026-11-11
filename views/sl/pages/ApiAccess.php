<?php
/**
 * ApiAccess - from docs/handover/artifact-source/ApiAccess.dc.html.
 * Data: api_access.php. Keys: ajax/sl/api_tokens_ajax.php (raw key shown once).
 */
if (!isset($d)) { $d = []; }
$ajax = 'ajax/sl/api_tokens_ajax.php';
$base = $d['base'];
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">API Access</h1><p class="sl-lede">Connect your store, ERP or app to Swift Lane · REST API at <span class="mono"><?= h($base) ?></span></p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="api/v1/docs/openapi.yaml"><i class="ic ic-sm i-book" aria-hidden="true"></i>API reference (OpenAPI)</a><button class="sl-btn" data-sl-open="ak-new"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Create API key</button></div></div>
<div class="grid cols3">
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-gauge" aria-hidden="true"></i>Requests this month</span></div><div class="fig"><?= sl_num($d['month']) ?> <i>requests from your keys</i></div><div class="sl-ticks" role="img" aria-label="requests"><i style="width:<?= min(100, (int) $d['month']) ?>%"></i></div><div class="ft">Counted from the API request log</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-clock" aria-hidden="true"></i>Rate limit</span></div><div class="fig"><?= (int) $d['peak'] ?> <i>requests / minute peak today</i></div><div class="sl-ticks y" role="img" aria-label="Peak"><i style="width:<?= min(100, (int) $d['peak']) ?>%"></i></div><div class="ft">Tokens last up to a year</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-key" aria-hidden="true"></i>Active keys</span></div><div class="fig"><?= (int) $d['keyCount'] ?> <i>/ <?= (int) $d['max'] ?> allowed</i></div><div class="sl-ticks" role="img" aria-label="keys"><i style="<?= sl_v($d, 'keyW') ?>"></i></div><div class="ft">Oldest key is pruned past <?= (int) $d['max'] ?></div></div>
</div>
<section class="ap-new" aria-live="polite" id="ak-shown" hidden><div class="row between wrap g8"><div><h2 class="sl-ct">Your new key is ready</h2><p class="mut sm">Copy it now. For safety we only show it once.</p></div><span class="ov-pill ok">Created</span></div><div class="ap-secret"><span class="grow mono" id="ak-raw" style="word-break:break-all"></span><button class="sl-btn sm" type="button" id="ak-copy"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy</button></div><div class="row g8 wrap sm mut"><span>Scopes:</span><span class="ap-scope">your permissions</span><span>· expires <b id="ak-exp"></b></span></div><button class="sl-btn ghost" style="align-self:flex-start" type="button" onclick="location.reload()">Done</button></section>
<div class="sl-dialog" id="ak-new" hidden><form class="sl-modal col g12" id="ak-form" role="dialog" aria-label="Create API key"><h2 class="sl-ct">Create API key</h2><label class="sl-field"><span class="sl-lab">Name</span><input class="sl-in" name="name" placeholder="Shopify store" required></label><label class="sl-field"><span class="sl-lab">Expires after</span><label class="sl-sel native"><select name="days"><option value="30">30 days</option><option value="90" selected>90 days</option><option value="365">1 year</option></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></label><span class="mut sm">The key acts as you, with your permissions.</span><div class="row g8"><button class="sl-btn" type="submit">Create key</button><button class="sl-btn ghost" type="button" data-sl-close>Cancel</button></div></form></div>
<div class="split">
<section class="a sl-card col g8"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Your API keys</h2><span class="mut sm">Owner: <?= h($d['owner']) ?><?= $d['locker'] ? ' · Locker ' . h($d['locker']) : '' ?></span></div>
<?php foreach ($d['keys'] as $k): ?><div class="ap-key"><span class="sl-tile"><i class="ic ic-sm i-key" aria-hidden="true"></i></span><div class="grow col g4"><div class="row g8 wrap"><b class="fw5"><?= h($k['name']) ?></b><span class="ov-pill <?= h($k['cls']) ?>"><?= h($k['state']) ?></span></div><span class="mono mut sm"><?= h($k['mask']) ?> · last used <?= h($k['used']) ?> · expires <?= h($k['expires']) ?></span></div><span class="mono sm"><?= h($k['calls']) ?></span><?php if ($k['active']): ?><button class="sl-btn sm ghost" data-sl-action="<?= $ajax ?>" data-p-op="revoke" data-p-id="<?= (int) $k['id'] ?>" data-sl-confirm="Revoke this key? Apps using it stop working." data-sl-reload>Revoke</button><?php endif; ?></div><?php endforeach; ?><?php if (!$d['keys']): ?><div class="sl-empty"><span class="sl-tile"><i class="ic i-key" aria-hidden="true"></i></span>No API keys yet. Create one to connect your store or app.</div><?php endif; ?>
</section>
<section class="b sl-card col g12"><h2 class="sl-ct">Quick start</h2><div class="sl-seg boxed" style="align-self:flex-start"><?php foreach ($d['langs'] as $l): ?><a class="<?= $l['k'] === $d['lang'] ? 'on' : '' ?>" href="<?= h(sl_url(['lang' => $l['k']])) ?>"><?= h($l['label']) ?></a><?php endforeach; ?></div>
<?php if ($d['lang'] === 'curl'): ?><div class="ap-code"><span class="c"># Track a parcel</span>
curl <?= h($base) ?>/tracking/<span class="s">SL-100480</span> \
  -H <span class="s">"Authorization: Bearer $SWIFTLANE_KEY"</span>

<span class="c"># Create a pre-alert</span>
curl -X POST <?= h($base) ?>/pre-alerts \
  -H <span class="s">"Authorization: Bearer $SWIFTLANE_KEY"</span> \
  -d <span class="s">'{"carrier":"ups","tracking":"1Z999AA1..."}'</span></div><?php endif; ?>
<?php if ($d['lang'] === 'js'): ?><div class="ap-code"><span class="k">const</span> res = <span class="k">await</span> fetch(<span class="s">"<?= h($base) ?>/shipments?status=in_transit"</span>, {
  headers: { Authorization: <span class="s">`Bearer ${key}`</span> }
});
<span class="k">const</span> { data, meta } = <span class="k">await</span> res.json();</div><?php endif; ?>
<?php if ($d['lang'] === 'php'): ?><div class="ap-code">$ch = curl_init(<span class="s">"<?= h($base) ?>/packages"</span>);
curl_setopt($ch, CURLOPT_HTTPHEADER, [<span class="s">"Authorization: Bearer $key"</span>]);
$packages = json_decode(curl_exec($ch), <span class="k">true</span>);</div><?php endif; ?>
<p class="mut sm">Every response is JSON: <span class="mono">data</span>, <span class="mono">meta</span> for paging (<span class="mono">page</span>, <span class="mono">per_page</span>, <span class="mono">sort</span>) and <span class="mono">error</span> on failure.</p></section>
</div>
<section class="sl-card col g4" data-sl-scope><div class="sl-ch"><h2 class="sl-ct">What you can do</h2><div class="sl-search" style="max-width:260px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search endpoints" placeholder="Search endpoints" data-sl-filter-q></div></div>
<div class="ap-ep" data-sl-row><span class="ap-m post">POST</span><span><span class="mono">/auth/login</span> <span class="mut sm">· Sign in and get a token</span></span><span class="ap-scope">public</span></div><div class="ap-ep" data-sl-row><span class="ap-m post">POST</span><span><span class="mono">/auth/otp/verify</span> <span class="mut sm">· Confirm the one-time code</span></span><span class="ap-scope">public</span></div><div class="ap-ep" data-sl-row><span class="ap-m get">GET</span><span><span class="mono">/auth/me</span> <span class="mut sm">· Your profile and permissions</span></span><span class="ap-scope">me</span></div><div class="ap-ep" data-sl-row><span class="ap-m get">GET</span><span><span class="mono">/tracking/{number}</span> <span class="mut sm">· Track any parcel</span></span><span class="ap-scope">tracking:read</span></div><div class="ap-ep" data-sl-row><span class="ap-m get">GET</span><span><span class="mono">/shipments</span> <span class="mut sm">· Your shipments, with filters</span></span><span class="ap-scope">shipments:read</span></div><div class="ap-ep" data-sl-row><span class="ap-m post">POST</span><span><span class="mono">/pre-alerts</span> <span class="mut sm">· Tell us a parcel is coming</span></span><span class="ap-scope">prealerts:write</span></div><div class="ap-ep" data-sl-row><span class="ap-m get">GET</span><span><span class="mono">/packages</span> <span class="mut sm">· Your registered packages</span></span><span class="ap-scope">packages:read</span></div><div class="ap-ep" data-sl-row><span class="ap-m post">POST</span><span><span class="mono">/pickups</span> <span class="mut sm">· Book a pickup</span></span><span class="ap-scope">pickups:write</span></div><div class="ap-ep" data-sl-row><span class="ap-m get">GET</span><span><span class="mono">/payments</span> <span class="mut sm">· Bills and receipts</span></span><span class="ap-scope">payments:read</span></div><div class="ap-ep" data-sl-row><span class="ap-m patch">PATCH</span><span><span class="mono">/notifications/settings</span> <span class="mut sm">· Choose e-mail, SMS or WhatsApp</span></span><span class="ap-scope">me</span></div><div class="sl-empty" data-sl-empty hidden>No endpoints match your search.</div></section>
<script>
document.getElementById('ak-form').addEventListener('submit', function (e) {
  e.preventDefault();
  SL.post('<?= $ajax ?>', Object.assign({ op: 'create' }, Object.fromEntries(new FormData(this)))).then(function (j) {
    SL.toast(j.message || '', !j.success); if (!j.success) return;
    document.getElementById('ak-new').hidden = true; document.getElementById('ak-raw').textContent = j.token; document.getElementById('ak-exp').textContent = j.expires_at; document.getElementById('ak-shown').hidden = false;
  });
});
document.getElementById('ak-copy').addEventListener('click', function () { navigator.clipboard.writeText(document.getElementById('ak-raw').textContent).then(function () { SL.toast('Copied'); }); });
</script>
