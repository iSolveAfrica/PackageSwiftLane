<?php
/**
 * The customer app on a computer (helpers/sl_appframe.php): the page is shown in an app window at phone
 * width, so it looks and works exactly like the phone app. The window keeps the browser's address bar and
 * title in step with the page inside (dataJs/sl/appframe.js posts them up).
 * Variables from views/sl/inc/shell_vars.php.
 */
$afUrl = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$afUrl = preg_replace('/([?&])appview=(on|off)(&|$)/', '$1', $afUrl);
$afUrl = rtrim($afUrl, '?&');
$afSite = (string) ($core->site_name ?? 'Swift Lane');
$afTitle = trim((string) ($slPage['title'] ?? '')) !== '' ? $slPage['title'] . ' · ' . $afSite : $afSite;
$afFull = $afUrl . (str_contains($afUrl, '?') ? '&' : '?') . 'appview=off';
header('Cache-Control: private, no-store');
?><!DOCTYPE html>
<html lang="<?= h($config_lang ?? 'en') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($afTitle) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/swiftlane.css')) ?>">
<link rel="icon" href="<?= h(cdp_asset('assets/swiftlane/pwa/customer-192.png')) ?>">
<script>(function(){try{var t=localStorage.getItem('sl.theme');if(t==='dark'||(t==='auto'&&matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('sl-dark');}}catch(e){}})();</script>
<style>
html, body { margin: 0; height: 100%; }
body { font-family: var(--font); color: var(--ink); background: radial-gradient(1200px 700px at 15% 10%, color-mix(in srgb, #123d35 16%, transparent), transparent 60%),
  radial-gradient(900px 600px at 90% 90%, color-mix(in srgb, #b9edbb 30%, transparent), transparent 60%), var(--bg); overflow: hidden; }
.af { height: 100%; display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: center; gap: 48px; padding: 24px 40px; box-sizing: border-box; }
.af-side { display: flex; flex-direction: column; gap: 14px; max-width: 360px; justify-self: end; }
.af-side .logo img, .af-side .logo svg { height: 40px; width: auto; }
.af-side h1 { margin: 8px 0 0; font-size: 34px; line-height: 1.1; letter-spacing: -.03em; font-weight: 600; }
.af-side p { margin: 0; font-size: 15.5px; line-height: 1.55; color: var(--muted); }
.af-tips { list-style: none; margin: 6px 0 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.af-tips li { display: flex; gap: 10px; align-items: flex-start; font-size: 14.5px; color: var(--ink-2, var(--ink)); }
.af-tips b { flex: none; width: 24px; height: 24px; border-radius: 8px; display: grid; place-items: center; background: #123d35; color: #fff; font-size: 12px; }
.af-win { position: relative; width: 430px; height: min(932px, calc(100dvh - 48px)); border-radius: 36px; overflow: hidden; background: var(--bg);
  box-shadow: 0 0 0 1px var(--line), 0 0 0 10px color-mix(in srgb, var(--ink) 92%, transparent), 0 40px 90px rgba(10, 20, 18, .35); }
.af-win iframe { display: block; width: 100%; height: 100%; border: 0; background: var(--bg); }
.af-load { position: absolute; inset: 0; display: grid; place-items: center; color: var(--muted); font-size: 14px; pointer-events: none; transition: opacity .2s; }
.af-ready .af-load { opacity: 0; }
.af-aside { display: flex; flex-direction: column; gap: 10px; justify-self: start; font-size: 14px; color: var(--muted); max-width: 260px; }
.af-aside a { color: var(--ink); font-weight: 500; text-decoration: underline; text-underline-offset: 3px; }
@media (max-width: 1180px) { .af { grid-template-columns: 1fr; justify-items: center; } .af-side, .af-aside { display: none; } }
@media (max-height: 700px) { .af-win { height: calc(100dvh - 24px); } .af { padding: 12px; } }
</style>
</head>
<body>
<div class="af">
  <div class="af-side">
    <span class="logo"><?= function_exists('sl_brandLogo') ? sl_brandLogo('color', 40, '') : h($afSite) ?></span>
    <h1>Your parcels, in the app</h1>
    <p>Hi <?= h($slFirst ?? '') ?>, this is the <?= h($afSite) ?> app on your computer: the same screens as on your phone.</p>
    <ul class="af-tips">
      <li><b>1</b><span><b style="all:unset;font-weight:600">Home</b> shows what needs you now: parcels ready, pickup codes, bills.</span></li>
      <li><b>2</b><span><b style="all:unset;font-weight:600">Parcels</b> tracks everything on its way, air or sea.</span></li>
      <li><b>3</b><span>The <b style="all:unset;font-weight:600">menu</b> in the middle opens every page of your account.</span></li>
    </ul>
  </div>
  <div class="af-win" id="af-win">
    <div class="af-load">Opening <?= h($afSite) ?>…</div>
    <iframe id="af-frame" title="<?= h($afSite) ?> app" src="<?= h($afUrl) ?>" allow="clipboard-write; publickey-credentials-get *; publickey-credentials-create *; geolocation; camera"></iframe>
  </div>
  <div class="af-aside">
    <span>Prefer the wide website?</span>
    <a href="<?= h($afFull) ?>" target="_top">Open the full website</a>
    <span>Payments and receipts open full screen.</span>
  </div>
</div>
<script>
(function () {
  var f = document.getElementById('af-frame'), w = document.getElementById('af-win');
  f.addEventListener('load', function () { w.classList.add('af-ready'); });
  // the page inside tells us where it is: keep the address bar and the title in step (same origin only)
  addEventListener('message', function (e) {
    if (e.origin !== location.origin || !e.data || e.data.type !== 'sl-app-nav') return;
    try {
      var u = new URL(e.data.url, location.href);
      if (u.origin === location.origin) history.replaceState(null, '', u.pathname + u.search + u.hash);
      if (e.data.title) document.title = e.data.title;
    } catch (er) {}
  });
})();
</script>
</body>
</html>
