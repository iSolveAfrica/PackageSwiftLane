<?php
/**
 * M-Billing: Billing & finance hub on a phone (financial_sheet.php). Same $d as the web page
 * (views/sl/data/finance.php sl_billingHubData). The finance switch saves through ajax/sl/finance_switch_ajax.php
 * like the web switch; while finance is off only the switch shows (as on the web page).
 */
require_once CDP_ROOT . '/views/sl/data/phone_people.php';
$slPhone['nav'] = 'staff';
$k = $d['kpis'] ?? [];
$tile = function (array $t, string $note = '', string $cls = '') {
    $sp = '';
    foreach ($t['sp'] ?? [] as $i => $b) $sp .= '<i class="' . ($i === count($t['sp']) - 1 ? 'now' : '') . '" style="' . h($b['s']) . '"></i>';
    return '<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm ' . h($t['icon']) . '" aria-hidden="true"></i></span><small>' . h($t['l']) . '</small></div><b>' . h($t['v']) . '</b><em class="' . h($cls) . '">' . h($note !== '' ? $note : $t['s']) . '</em>' . ($sp !== '' ? '<div class="mx-sp" aria-hidden="true">' . $sp . '</div>' : '') . '</div>';
};
$links = [
    ['Transactions', 'activity', sl_route('Transactions'), 'view_transactions'],
    ['Receivable', 'receipt', sl_route('AccountsReceivable'), 'view_receivable_accounts'],
    ['Send prices', 'send', sl_route('SendPrices'), ['send_prices', 'push_notifications']],
    ['Record pay', 'plus', sl_route('RecordPayment'), null],
    ['Overview', 'chart', sl_route('FinancialOverview'), 'view_financial_overview'],
    ['Rates', 'tag', sl_route('Tariffs'), null],
    ['Costs', 'dollar', sl_route('CostSettings'), null],
    ['Gateways', 'card', 'payment_mode_list.php', null],
];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-people.css')) ?>">
<?= sl_phoneTop('Billing & finance', '', ['back' => 'menu.php'], $d['on'] ? sl_phoneIcon('grid', 'Financial sheet', 'financial_sheet_consolidation.php') : '') ?>
<main class="mx-body">
<section class="mx-card mx-tgs pp-wrap">
<?php if ($d['canSwitch']): ?>
  <?= sl_mxToggle('Finance module', $d['mNote'], (bool) $d['on'], 'ajax/sl/finance_switch_ajax.php', 'finance_enabled', [], false) ?>
<?php else: ?>
  <div class="mx-tg"><span class="mx-rm"><b>Finance module</b><small><?= h($d['mNote']) ?></small></span><span class="sl-pill <?= $d['on'] ? 'delivered' : 'plain' ?>"><?= $d['on'] ? 'On' : 'Off' ?></span></div>
<?php endif; ?>
</section>
<?php if ($d['on']): ?>
<?php if (sl_finRate() > 0): ?><div class="mx-fx"><i class="ic ic-sm i-globe" aria-hidden="true"></i><span><?= h($d['fees'][0]['v'] ?? '') ?></span><small>exchange rate</small></div><?php endif; ?>
<section class="mx-k c2">
  <?php if (isset($k[0])) echo $tile($k[0]); ?>
  <?php if (isset($k[1])) echo $tile($k[1], '', 'up'); ?>
  <?php if (isset($k[2])) echo $tile($k[2]); ?>
  <?php if (isset($k[3])) echo $tile($k[3], '', 'warn'); ?>
</section>
<section class="mx-act c4">
<?php foreach ($links as [$label, $icon, $href, $perm]): if ($perm !== null && !$user->cdp_hasPermission($perm)) continue; ?>
  <a href="<?= h($href) ?>"><span><i class="ic i-<?= h($icon) ?>" aria-hidden="true"></i></span><small><?= h($label) ?></small></a>
<?php endforeach; ?>
</section>
<?php if ($d['accts']): ?>
<div class="mx-sec"><h2>Customer accounts</h2><a href="<?= h(sl_route('Customers', ['filter' => 'due'])) ?>">Balances</a></div>
<section class="mx-card mx-list">
<?php foreach ($d['accts'] as $a): ?>
  <a class="mx-row" href="<?= h($a['btn'] === 'Statement' ? sl_route('CustomerProfile', ['id' => $a['id']]) : sl_route('RecordPayment', ['customer' => $a['id']])) ?>"><span class="mx-ava"><?= h(sl_initials($a['n'])) ?></span><span class="mx-rm"><b><?= h($a['n']) ?></b><small><?= h($a['type']) ?> · <?= h($a['lim']) ?></small></span><span class="mx-rr"><b><?= h($a['bal']) ?></b><small><?= h($a['note']) ?></small></span></a>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php endif; ?>
</main>
