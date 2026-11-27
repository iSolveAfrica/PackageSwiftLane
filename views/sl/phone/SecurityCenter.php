<?php
/**
 * M-Security: security checks, policy states and recent sign-ins. Same $d as the web page
 * (views/sl/data/security.php sl_securityData). Read-only: policy rows are copies of the real settings and link
 * to where each one is changed (only links the viewer's role may open, as on the web page).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$n = count($d['chk']);
$pct = $n ? (int) round($d['passN'] / $n * 100) : 0;
$pill = ['' => ['OK', 'delivered'], 'w' => ['Review', 'onway'], 'e' => ['Fix', 'overdue']];
$firstLink = function (array $c): string {
    foreach ($c['links'] as $l) if (($l[3] ?? '') === '' && ($l[1] ?? '') !== '' && $l[1][0] !== '#') return (string) $l[1];
    return '';
};
// Checks to look at first, then the passing ones.
$chk = $d['chk'];
usort($chk, fn($a, $b) => ['e' => 0, 'w' => 1, '' => 2][$a['d']] <=> ['e' => 0, 'w' => 1, '' => 2][$b['d']]);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-centres.css')) ?>">
<?= sl_phoneTop('Security center', 'Checked at ' . $d['ran'], ['back' => 'menu.php'], sl_phoneIcon('refresh', 'Run the checks again', sl_route('SecurityCenter'))) ?>
<main class="mx-body">
<section class="mx-hero">
  <div class="mx-hk">Checks passing<span class="mx-tag"><?= $d['warnN'] ? 'Look at ' . (int) $d['warnN'] : 'All clear' ?></span></div>
  <b class="mx-hv"><?= (int) $d['passN'] ?> of <?= $n ?></b>
  <small><?= $d['warnN'] ? (int) $d['warnN'] . ' thing' . ($d['warnN'] === 1 ? '' : 's') . ' to look at' : 'Nothing to fix' ?> · checked <?= h($d['ran']) ?></small>
  <div class="mx-prog"><i style="width:<?= $pct ?>%"></i></div>
</section>
<section class="mx-card mx-list pc-wrap">
<?php foreach ($chk as $c): $href = $firstLink($c); $tag = $href !== '' ? 'a' : 'div'; ?>
<<?= $tag ?> class="mx-row"<?= $href !== '' ? ' href="' . h($href) . '"' : '' ?>><span class="mx-li"><i class="ic <?= h($c['icon']) ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c['t']) ?></b><small><?= h($c['s']) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($pill[$c['d']][1] ?? '') ?>"><?= h($pill[$c['d']][0] ?? '') ?></span></span></<?= $tag ?>>
<?php endforeach; ?>
</section>
<?= sl_mxSec('Policies') ?>
<section class="mx-card mx-tgs pc-wrap">
<?php foreach ($d['policy'] as $p): $href = ($p['href'] ?? '') !== '' && $p['href'][0] !== '#' && ($p['needs'] ?? '') === '' ? $p['href'] : ''; ?>
  <div class="mx-tg"><span class="mx-rm"><b><?php if ($href !== ''): ?><a href="<?= h($href) ?>" style="color:inherit"><?= h($p['t']) ?></a><?php else: ?><?= h($p['t']) ?><?php endif; ?></b><small><?= h($p['d']) ?></small></span><button type="button" class="mx-sw<?= $p['on'] ? ' on' : '' ?>" role="switch" aria-checked="<?= $p['on'] ? 'true' : 'false' ?>" aria-label="<?= h($p['t']) ?> (read-only)" disabled aria-disabled="true"></button></div>
<?php endforeach; ?>
</section>
<p class="mx-note">These show the real settings. Change them where each one lives (tap its name).</p>
<?php if ($d['recent']): ?>
<?= sl_mxSec('Recent sign-ins', $d['logsHref'] !== '' ? 'All' : '', $d['logsHref']) ?>
<section class="mx-card mx-list pc-wrap">
<?php foreach (array_slice($d['recent'], 0, 8) as $r): ?>
<div class="mx-row"><span class="mx-li"><i class="ic i-<?= $r['ok'] ? 'check' : 'x' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($r['t']) ?></b><small><?= h(implode(' · ', array_filter([$r['dev'], $r['ip'], $r['why']]))) ?></small></span><span class="mx-rr"><small><?= h($r['ago']) ?></small></span></div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<div class="mx-btns"><a class="mx-btn sec" href="security_center.php?view=web">Full security center</a></div>
</main>
