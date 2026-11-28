<?php
/** M-DevCosts: what SMS, WhatsApp and e-mail cost. Data: sl_messageCostsData (same as the web MessageCosts, ?range=). Unit prices are edited on the full page. */
require_once CDP_ROOT . '/views/sl/data/dev_phone.php';
$slPhone['cls'] = 'dk';
if (is_file(CDP_ROOT . '/views/sl/data/dev_observe.php')) {
    require_once CDP_ROOT . '/views/sl/data/dev_observe.php';
    // developer tab bar only for someone who may open the console; everyone else keeps their own
    if (isset($user) && $user->cdp_hasPermission('view_developer') && sl_dvxAllowed($user, 'overview')) {
        $slPhone['nav'] = 'dev';
        $slPhone['tab'] = 'Console';
    }
}
$rangeLabel = ['today' => 'Today', '7d' => 'Last 7 days', 'month' => 'This month', 'year' => 'This year', 'all' => 'All time'][$d['range'] ?? 'month'] ?? 'This month';
$series = (array) ($d['series'] ?? []);
$labels = (array) ($d['seriesLabels'] ?? []);
$cur = (string) ($d['currency'] ?? '');
$bars = sl_phDevBars(array_slice($series, -7), array_map(fn($l) => strlen((string) $l) === 10 ? sl_date((string) $l, 'D j') : (string) $l, array_slice($labels, -7)), 7, 'sum');
$back = $slPhone['nav'] === 'dev' ? sl_route('DevOverview') : 'menu.php';
?>
<?= sl_phoneTop('Message costs', $rangeLabel, ['back' => $back], sl_phoneIcon('more', 'Unit costs and full report', sl_url(['view' => 'web']) . '#unit-costs')) ?>
<main class="mx-body">
<?php if (empty($d['ok'])): ?><div class="mx-ban warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The message log is not set up on this database yet.</span></div><?php endif; ?>
<div class="mx-seg" role="group" aria-label="Period"><form method="get" style="display:contents"><?php if (!empty($_GET['group'])): ?><input type="hidden" name="group" value="<?= h((string) $_GET['group']) ?>"><?php endif; ?>
<?php foreach ($d['ranges'] ?? [] as $x): $on = $x['k'] === ($d['range'] ?? 'month'); ?><button type="submit" name="range" value="<?= h($x['k']) ?>" class="<?= $on ? 'on' : '' ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"><?= h($x['l']) ?></button><?php endforeach; ?>
</form></div>
<section class="mx-k c2">
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-dollar" aria-hidden="true"></i></span><small><?= h($rangeLabel) ?></small></div><b><?= h((string) ($d['grand'] ?? '—')) ?></b><?= sl_phDevSparkHtml(sl_phDevSpark($series)) ?></div>
<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-box" aria-hidden="true"></i></span><small>Per <?= h((string) ($d['group'] ?? 'day')) ?></small></div><b><?= h((string) ($d['avg'] ?? '—')) ?></b><small style="color:var(--muted);font-size:11.5px">Average</small></div>
</section>
<?= sl_mxSec('By provider') ?>
<section class="mx-card mx-mts">
<?php foreach ($d['cards'] ?? [] as $c): ?>
<a class="mx-mt" href="<?= h($c['href']) ?>" style="color:inherit"><div><span><?= h($c['label']) ?></span><b><?= h($c['fig']) ?></b></div><span class="mx-trk" role="img" aria-label="<?= (int) $c['share'] ?>% of spend"><i style="width:<?= max(1, (int) $c['share']) ?>%"></i></span><small style="display:block;margin-top:5px;color:var(--muted);font-size:11.5px"><?= h($c['ft']) ?></small></a>
<?php endforeach; ?>
</section>
<section class="mx-card mx-bars"><div class="mx-bh"><b>Spend per <?= h((string) ($d['group'] ?? 'day')) ?></b></div>
<?php if ($bars): ?><?= sl_phDevBarsHtml($bars, fn($v) => $cur . ' ' . number_format((float) $v, 2)) ?><?php else: ?><p style="margin:8px 0 0;color:var(--muted);font-size:13px">No priced messages in this period.</p><?php endif; ?>
</section>
<?php if (!empty($d['topSrc']) && $d['topSrc'] !== '—'): ?>
<section class="mx-card mx-kv"><div><span>Most spent on</span><b><?= h($d['topSrc']) ?></b></div><?php foreach (['sms' => 'Per SMS', 'whatsapp' => 'Per WhatsApp', 'email' => 'Per e-mail'] as $k => $l): ?><div><span><?= h($l) ?></span><b><?= h($cur . ' ' . ($d['current'][$k] ?? '0')) ?></b></div><?php endforeach; ?></section>
<?php endif; ?>
</main>
