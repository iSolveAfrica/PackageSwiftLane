<?php
/**
 * M-MsgLogs: every SMS, WhatsApp and e-mail. Same $d as the web page (views/sl/data/message_logs.php):
 * channel tabs are the web ?filter=, search ?q=, pages ?page=.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'staff';
$slPhone['tab'] = 'Menu';
$pages = max(1, (int) ceil(($d['count'] ?? 0) / max(1, $d['per'])));
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-settings.css')) ?>">
<?= sl_phoneTop('Message logs', $d['ready'] ? sl_num($d['month']) . ' this month · ' . $d['rate'] . '% sent' : '', ['back' => 'menu.php']) ?>
<main class="mx-body">
<?php if (!$d['ready']): ?>
<section class="mx-card"><?= sl_mxEmpty('message', 'Message log not installed', 'Run the database upgrade to start recording messages.') ?></section>
<?php else: ?>
<form method="get" role="search"><div class="mx-srch"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" aria-label="Search messages" placeholder="Search name, number or tracking"></label></div></form>
<nav class="mx-seg" aria-label="Channel"><?php foreach ($d['filters'] as $x): $on = $x['f'] === $d['filter']; ?><a class="<?= $on ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= h($x['label']) ?><?= $x['f'] === 'failed' && $d['failed'] ? ' ' . (int) $d['failed'] : '' ?></a><?php endforeach; ?></nav>
<section class="mx-card mx-list">
<?php foreach ($d['rows'] as $r): $tag = $r['link'] !== '' ? 'a' : 'div'; ?>
<<?= $tag ?> class="mx-row"<?= $r['link'] !== '' ? ' href="' . h($r['link']) . '"' : '' ?>><span class="mx-li"><i class="ic <?= $r['ch'] === 'Email' || stripos($r['ch'], 'mail') !== false ? 'i-mail' : 'i-message' ?>" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h(($r['name'] !== '—' ? $r['name'] : $r['to']) . ' · ' . $r['ch']) ?></b><small><?= h($r['tpl'] . ($r['trk'] !== '—' ? ' · ' . $r['trk'] : '') . ($r['cls'] === 'overdue' && $r['short'] !== '' ? ' · ' . $r['short'] : '')) ?></small></span><span class="mx-rr"><span class="sl-pill <?= h($r['cls']) ?>" title="<?= h($r['detail']) ?>"><?= h($r['st']) ?></span><small><?= h($r['t']) ?></small></span></<?= $tag ?>>
<?php endforeach; ?>
<?php if (!$d['rows']): ?><?= sl_mxEmpty('message', 'No messages', 'Nothing matches this filter.') ?><?php endif; ?>
</section>
<?php if ($pages > 1): ?>
<div class="ph-pager"><a class="mx-btn sec" href="<?= h(sl_url(['page' => $d['page'] - 1])) ?>"<?= $d['page'] <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Newer</a><span>Page <?= (int) $d['page'] ?> of <?= (int) $pages ?></span><a class="mx-btn sec" href="<?= h(sl_url(['page' => $d['page'] + 1])) ?>"<?= $d['page'] >= $pages ? ' aria-disabled="true" tabindex="-1"' : '' ?>>Older</a></div>
<?php endif; ?>
<?php endif; ?>
</main>
