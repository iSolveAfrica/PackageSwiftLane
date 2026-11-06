<?php
/**
 * SecurityCenter - generated from docs/handover/artifact-source/SecurityCenter.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  kpis                         as $k
 *   loop  k.sp                         as $h
 *   loop  chk                          as $c
 *   loop  dev                          as $d
 *   loop  pol                          as $p
 *   loop  cf                           as $f
 *   loop  cov                          as $c
 *   if    flash
 *   value cf, chk, cov, dev, false, flash, h, kpis, passN, pol, ran, run, runL, warnN
 * sl-wrap extras in the design: style="max-width:1440px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Security and reliability</h1><p class="sl-lede">The checks behind the system: sign-in, encryption, audit logs, backups, monitoring and releases, with what each one shows right now.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('SystemLogs') ?>">System logs</a><a class="sl-btn" href="<?= sl_route('StaffPermissions') ?>">Permissions</a></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks"><?php foreach ((array) sl_get($d, 'kpis', []) as $__i => $k): ?><div class="hb-k"><div class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></div><b class="hb-kv <?= sl_v($k, 'anim') ?>"><?= sl_v($k, 'v') ?></b><div class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $__i => $h): ?><i style="<?= sl_v($h, '') ?>"></i><?php endforeach; ?></span></div></div><?php endforeach; ?></div>
<div class="hb-g"><section class="hb-p" style="grid-column:1/-1"><div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Security checks · run every hour</h2><span class="mut sm"><?= sl_v($d, 'passN') ?> passing · <?= sl_v($d, 'warnN') ?> to look at · last run <?= sl_v($d, 'ran') ?></span></div><button class="sl-btn ghost sm" data-sl-on="click:run"><?= sl_v($d, 'runL') ?></button></div><div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(min(300px,100%),1fr));gap:8px"><?php foreach ((array) sl_get($d, 'chk', []) as $__i => $c): ?><div class="hb-i" style="cursor:default"><span class="hb-dot <?= sl_v($c, 'd') ?>" style="margin-top:5px"></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 's') ?></span></span></div><?php endforeach; ?></div></section>
<section class="hb-p"><h2 class="sl-ct">Devices and log-ins · Henry Cobblah</h2><?php foreach ((array) sl_get($d, 'dev', []) as $__i => $it_d): ?><div class="hb-l"><i class="ic ic-sm <?= sl_v($it_d, 'icon') ?>" aria-hidden="true"></i><span class="col grow"><b class="fw5"><?= sl_v($it_d, 't') ?></b><span class="mut" style="font-size:12px"><?= sl_v($it_d, 's') ?></span></span><button class="sl-btn ghost sm" data-sl-on="click:go"><?= sl_v($it_d, 'l') ?></button></div><?php endforeach; ?></section>
<section class="hb-p"><h2 class="sl-ct">Sign-in policy</h2><?php foreach ((array) sl_get($d, 'pol', []) as $__i => $p): ?><div class="hb-l"><span class="col grow"><span><?= sl_v($p, 't') ?></span><span class="mut" style="font-size:12px"><?= sl_v($p, 'd') ?></span></span><button class="sl-toggle <?= sl_v($p, 'cls') ?>" role="switch" aria-checked="<?= sl_v($p, 'aria') ?>" aria-label="<?= sl_v($p, 't') ?>" data-sl-on="click:flip"></button></div><?php endforeach; ?></section></div>
<section class="col g10"><div class="row between wrap g8"><h2 class="sl-ct">All 25 features and where they live</h2><div class="sl-seg boxed"><?php foreach ((array) sl_get($d, 'cf', []) as $__i => $f): ?><button class="<?= sl_v($f, 'cls') ?>" data-sl-on="click:pick"><?= sl_v($f, 'label') ?></button><?php endforeach; ?></div></div><div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $__i => $c): ?><a class="hb-i <?= sl_v($c, 'off') ?>" href="<?= sl_v($c, 'href') ?>"><span class="n"><?= sl_v($c, 'n') ?></span><span class="col grow"><b class="fw5 sm"><?= sl_v($c, 't') ?></b><span class="mut" style="font-size:11.5px"><?= sl_v($c, 'where') ?></span></span><span class="sl-pill <?= sl_v($c, 'pc') ?>"><?= sl_v($c, 'st') ?></span></a><?php endforeach; ?></div></section>
