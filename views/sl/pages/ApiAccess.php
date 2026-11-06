<?php
/**
 * ApiAccess - generated from docs/handover/artifact-source/ApiAccess.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  keys                         as $k
 *   loop  langs                        as $l
 *   if    showNew
 *   if    isCurl
 *   if    isJs
 *   if    isPhp
 *   value create, false, hideNew, isCurl, isJs, isPhp, keyCount, keyW, keys, langs, showNew, true
 * sl-wrap extras in the design: style="max-width:1180px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:20px">API Access</h1><p class="sl-lede">Connect your store, ERP or app to Swift Lane · REST API at <span class="mono">/api/v1</span></p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('ApiAccess') ?>"><i class="ic ic-sm i-book" aria-hidden="true"></i>API reference (OpenAPI)</a><button class="sl-btn" data-sl-on="click:create"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Create API key</button></div></div>
<div class="grid cols3">
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-gauge" aria-hidden="true"></i>Requests this month</span></div><div class="fig">48,210 <i>/ 100,000 included</i></div><div class="sl-ticks" role="img" aria-label="48%"><i style="width:48%"></i></div><div class="ft">Resets Dec 1</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-clock" aria-hidden="true"></i>Rate limit</span></div><div class="fig">120 <i>requests / minute per key</i></div><div class="sl-ticks y" role="img" aria-label="Peak 72%"><i style="width:72%"></i></div><div class="ft">Peak today 86 / min</div></div>
<div class="ov-card"><div class="hd"><span><i class="ic ic-sm i-key" aria-hidden="true"></i>Active keys</span></div><div class="fig"><?= sl_v($d, 'keyCount') ?> <i>/ 5 allowed</i></div><div class="sl-ticks" role="img" aria-label="keys"><i style="<?= sl_v($d, 'keyW') ?>"></i></div><div class="ft">Unused keys expire after 90 days</div></div>
</div>
<?php if (sl_get($d, 'showNew')): ?><section class="ap-new" aria-live="polite"><div class="row between wrap g8"><div><h2 class="sl-ct">Your new key is ready</h2><p class="mut sm">Copy it now. For safety we only show it once.</p></div><span class="ov-pill ok">Created</span></div><div class="ap-secret"><span class="grow">sk_live_7fa2·····························e91c</span><button class="sl-btn sm"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy</button></div><div class="row g8 wrap sm mut"><span>Scopes:</span><span class="ap-scope">shipments:read</span><span class="ap-scope">packages:write</span><span class="ap-scope">tracking:read</span></div><button class="sl-btn ghost" style="align-self:flex-start" data-sl-on="click:hideNew">Done</button></section><?php endif; ?>
<div class="split">
<section class="a sl-card col g8"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Your API keys</h2><span class="mut sm">Owner: Efua Owusu · Locker SL-3318</span></div>
<?php foreach ((array) sl_get($d, 'keys', []) as $__i => $k): ?><div class="ap-key"><span class="sl-tile"><i class="ic ic-sm i-key" aria-hidden="true"></i></span><div class="grow col g4"><div class="row g8 wrap"><b class="fw5"><?= sl_v($k, 'name') ?></b><span class="ov-pill <?= sl_v($k, 'cls') ?>"><?= sl_v($k, 'state') ?></span></div><span class="mono mut sm"><?= sl_v($k, 'mask') ?> · last used <?= sl_v($k, 'used') ?></span><div class="row g6 wrap"><?= sl_v($k, 'scopes') ?></div></div><span class="mono sm"><?= sl_v($k, 'calls') ?></span><button class="sl-btn sm ghost" data-sl-on="click:revoke"><?= sl_v($k, 'action') ?></button></div><?php endforeach; ?>
</section>
<section class="b sl-card col g12"><h2 class="sl-ct">Quick start</h2><div class="sl-seg boxed" style="align-self:flex-start"><?php foreach ((array) sl_get($d, 'langs', []) as $__i => $l): ?><button class="<?= sl_v($l, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($l, 'label') ?></button><?php endforeach; ?></div>
<?php if (sl_get($d, 'isCurl')): ?><div class="ap-code"><span class="c"># Track a parcel</span>
curl https://swiftlane.gh/api/v1/tracking/<span class="s">SL-100480</span> \
  -H <span class="s">"Authorization: Bearer $SWIFTLANE_KEY"</span>

<span class="c"># Create a pre-alert</span>
curl -X POST https://swiftlane.gh/api/v1/prealerts \
  -H <span class="s">"Authorization: Bearer $SWIFTLANE_KEY"</span> \
  -d <span class="s">'{"carrier":"ups","tracking":"1Z999AA1..."}'</span></div><?php endif; ?>
<?php if (sl_get($d, 'isJs')): ?><div class="ap-code"><span class="k">const</span> res = <span class="k">await</span> fetch(<span class="s">"https://swiftlane.gh/api/v1/shipments?status=in_transit"</span>, {
  headers: { Authorization: <span class="s">`Bearer ${key}`</span> }
});
<span class="k">const</span> { data, meta } = <span class="k">await</span> res.json();</div><?php endif; ?>
<?php if (sl_get($d, 'isPhp')): ?><div class="ap-code">$ch = curl_init(<span class="s">"https://swiftlane.gh/api/v1/packages"</span>);
curl_setopt($ch, CURLOPT_HTTPHEADER, [<span class="s">"Authorization: Bearer $key"</span>]);
$packages = json_decode(curl_exec($ch), <span class="k">true</span>);</div><?php endif; ?>
<p class="mut sm">Every response is JSON: <span class="mono">data</span>, <span class="mono">meta</span> for paging (<span class="mono">page</span>, <span class="mono">per_page</span>, <span class="mono">sort</span>) and <span class="mono">error</span> on failure.</p></section>
</div>
<section class="sl-card col g4"><div class="sl-ch"><h2 class="sl-ct">What you can do</h2><div class="sl-search" style="max-width:260px"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search endpoints" placeholder="Search endpoints"></div></div>
<div class="ap-ep"><span class="ap-m post">POST</span><span><span class="mono">/auth/login</span> <span class="mut sm">· Sign in and get a token</span></span><span class="ap-scope">public</span></div><div class="ap-ep"><span class="ap-m post">POST</span><span><span class="mono">/auth/otp/verify</span> <span class="mut sm">· Confirm the one-time code</span></span><span class="ap-scope">public</span></div><div class="ap-ep"><span class="ap-m get">GET</span><span><span class="mono">/auth/me</span> <span class="mut sm">· Your profile and permissions</span></span><span class="ap-scope">me</span></div><div class="ap-ep"><span class="ap-m get">GET</span><span><span class="mono">/tracking/{number}</span> <span class="mut sm">· Track any parcel</span></span><span class="ap-scope">tracking:read</span></div><div class="ap-ep"><span class="ap-m get">GET</span><span><span class="mono">/shipments</span> <span class="mut sm">· Your shipments, with filters</span></span><span class="ap-scope">shipments:read</span></div><div class="ap-ep"><span class="ap-m post">POST</span><span><span class="mono">/prealerts</span> <span class="mut sm">· Tell us a parcel is coming</span></span><span class="ap-scope">prealerts:write</span></div><div class="ap-ep"><span class="ap-m get">GET</span><span><span class="mono">/packages</span> <span class="mut sm">· Your registered packages</span></span><span class="ap-scope">packages:read</span></div><div class="ap-ep"><span class="ap-m post">POST</span><span><span class="mono">/pickups</span> <span class="mut sm">· Book a pickup</span></span><span class="ap-scope">pickups:write</span></div><div class="ap-ep"><span class="ap-m get">GET</span><span><span class="mono">/payments</span> <span class="mut sm">· Bills and receipts</span></span><span class="ap-scope">payments:read</span></div><div class="ap-ep"><span class="ap-m patch">PATCH</span><span><span class="mono">/notifications/settings</span> <span class="mut sm">· Choose e-mail, SMS or WhatsApp</span></span><span class="ap-scope">me</span></div></section>
