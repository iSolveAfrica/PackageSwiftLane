<?php
/**
 * Campaigns: every bulk e-mail, SMS and WhatsApp send with delivery, opens, clicks and cost.
 * Data: views/sl/data/campaigns.php. Prices per channel are set in Message Costs (Developer).
 */
if (!isset($d)) { $d = []; }
$chL = ['email' => ['E-mail', 'i-mail'], 'sms' => ['SMS', 'i-message'], 'whatsapp' => ['WhatsApp', 'i-phone']];
$dt = $d['detail'] ?? null;
?>
<div class="sl-head"><div><h1 class="sl-title row g8"><i class="ic i-send" aria-hidden="true"></i>Campaigns</h1><p class="sl-lede">Every e-mail, SMS and WhatsApp campaign on the platform: who got it, who opened and clicked, what failed and what it cost.</p></div>
<div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('EmailBuilder') ?>"><i class="ic ic-sm i-mail" aria-hidden="true"></i>New e-mail</a><a class="sl-btn ghost" href="<?= sl_route('CommsCenter') ?>"><i class="ic ic-sm i-message" aria-hidden="true"></i>New SMS / WhatsApp</a><?php if ($d['canCost']): ?><a class="sl-btn ghost" href="<?= sl_route('MessageCosts') ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Message prices</a><?php endif; ?></div></div>
<?php if (!$d['ready']): ?><div class="sl-card"><div class="sl-empty">Message logging is not set up yet. Run the database upgrade.</div></div><?php else: ?>

<nav class="cm-f"><?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => 'Year'] as $k => $v): ?><a class="<?= $d['days'] === $k ? 'on' : '' ?>" href="<?= h(sl_url(['days' => $k === 30 ? null : $k, 'batch' => null])) ?>"><?= $v ?></a><?php endforeach; ?><span class="grow"></span>
<form method="get" class="cm-q"><?php if ($d['days'] !== 30): ?><input type="hidden" name="days" value="<?= (int) $d['days'] ?>"><?php endif; ?><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" placeholder="Find a campaign" aria-label="Find a campaign"></form></nav>

<div class="cm-ch">
  <a class="cm-tot<?= $d['ch'] === '' ? ' on' : '' ?>" href="<?= h(sl_url(['ch' => null, 'batch' => null])) ?>"><small>All channels · cost</small><b><?= h($d['totalCost']) ?></b><span><?= sl_num(array_sum(array_column($d['channels'], 'sent'))) ?> sent · <?= sl_num(array_sum(array_column($d['channels'], 'failed'))) ?> failed</span></a>
  <?php foreach ($chL as $k => [$l, $i]): $c = $d['channels'][$k] ?? ['sent' => 0, 'failed' => 0, 'ok' => 0, 'costL' => h($d['cur']) . ' 0', 'rate' => (float) ($d['rates'][$k] ?? 0)]; ?>
  <a class="cm-c<?= $d['ch'] === $k ? ' on' : '' ?>" href="<?= h(sl_url(['ch' => $k, 'batch' => null])) ?>">
    <div class="row between"><span class="row g6"><i class="ic ic-sm <?= $i ?>" aria-hidden="true"></i><b><?= $l ?></b></span><span class="mut sm"><?= h($d['cur']) ?> <?= h(rtrim(rtrim(number_format((float) $c['rate'], 4), '0'), '.') ?: '0') ?> each</span></div>
    <div class="cm-big"><?= sl_num($c['sent']) ?> <small>sent</small></div>
    <div class="cm-bar"><i style="width:<?= (int) $c['ok'] ?>%"></i></div>
    <div class="cm-kv"><span><?= (int) $c['ok'] ?>% delivered</span><span><?= sl_num($c['failed']) ?> failed</span><b><?= h($c['costL']) ?></b></div>
    <?php if ($k === 'email' && isset($c['opens'])): ?><div class="cm-kv"><span><?= sl_num($c['opens']) ?> opened</span><span><?= sl_num($c['clicks']) ?> clicked</span></div><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($dt): ?>
<section class="sl-card col g16 cm-d" id="campaign">
  <div class="row between wrap g8"><div class="col"><span class="up"><?= h($dt['src']) ?> · <?= h($dt['aud']) ?></span><h2 class="sl-ct" style="font-size:20px"><?= h($dt['title']) ?></h2><span class="mut sm"><?= h($dt['when']) ?> · by <?= h($dt['who'] ?: 'System') ?> · <?= sl_num($dt['n']) ?> recipients</span></div><a class="sl-btn ghost sm" href="<?= h(sl_url(['batch' => null])) ?>">Close</a></div>
  <?php $sent = $dt['agg']['sent']; $op = $dt['agg']['opened']; $cl = $dt['agg']['clicked']; $fl = $dt['agg']['failed']; ?>
  <div class="cm-fun"><?php foreach ([['Sent', $sent, $dt['n']], ['Opened', $op, $sent], ['Clicked', $cl, $sent], ['Failed', $fl, $dt['n']]] as [$fl2, $fv, $fb]): ?><div><small><?= $fl2 ?></small><b><?= sl_num($fv) ?></b><span><?= $fb ? (int) round($fv / $fb * 100) : 0 ?>%</span><i style="width:<?= $fb ? (int) round($fv / $fb * 100) : 0 ?>%"></i></div><?php endforeach; ?></div>
  <div class="cm-dg">
    <div class="col g8"><h3 class="sl-ct">Recipients</h3>
      <div class="sl-tw" style="max-height:460px;overflow:auto"><table class="sl-table"><thead><tr><th>Recipient</th><th>Status</th><th>Opened</th><th>Clicked</th></tr></thead><tbody>
      <?php foreach ($dt['rows'] as $r): ?><tr><td><span class="col"><?php if ($r['uid']): ?><a class="fw5" href="<?= sl_route('CustomerProfile', ['id' => $r['uid']]) ?>"><?= h($r['name']) ?></a><?php else: ?><span class="fw5"><?= h($r['name']) ?></span><?php endif; ?><span class="mut sm"><?= h($r['to']) ?> · <?= h($chL[$r['ch']][0] ?? $r['ch']) ?></span></span></td>
        <td><span class="sl-pill <?= $r['st'] === 'sent' ? 'delivered' : ($r['st'] === 'failed' ? 'overdue' : 'skipped') ?>" title="<?= h($r['why']) ?>"><?= h(ucfirst($r['st'])) ?></span></td><td class="sm"><?= $r['opened'] !== '' ? h($r['opened']) : '<span class="mut">—</span>' ?></td><td class="sm"><?= $r['clicked'] !== '' ? h($r['clicked']) : '<span class="mut">—</span>' ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php if ($dt['n'] > count($dt['rows'])): ?><span class="mut sm">First <?= count($dt['rows']) ?> of <?= sl_num($dt['n']) ?> shown. Every message is in Message Logs.</span><?php endif; ?></div>
    <div class="col g12">
      <?php if ($dt['timeline']): $tp = max(1, max(array_map(fn($t) => max($t['o'], $t['c']), $dt['timeline']))); ?><div class="col g6"><h3 class="sl-ct">Activity by hour</h3><div class="cm-tl"><?php foreach ($dt['timeline'] as $t): ?><span title="<?= h($t['h']) ?>: <?= (int) $t['o'] ?> opens, <?= (int) $t['c'] ?> clicks"><i class="o" style="height:<?= max(2, (int) round($t['o'] / $tp * 100)) ?>%"></i><i class="c" style="height:<?= (int) round($t['c'] / $tp * 100) ?>%"></i></span><?php endforeach; ?></div><span class="mut sm"><i class="cm-dot o"></i> opens <i class="cm-dot c"></i> clicks</span></div><?php endif; ?>
      <?php if ($dt['links']): ?><div class="col g6"><h3 class="sl-ct">Links clicked</h3><?php foreach ($dt['links'] as $lk): ?><div class="cm-link"><span title="<?= h($lk->url) ?>"><?= h(preg_replace('#^https?://#', '', $lk->url)) ?></span><b><?= (int) $lk->n ?></b><small><?= (int) $lk->u ?> people</small></div><?php endforeach; ?></div><?php endif; ?>
      <?php if ($dt['fails']): ?><div class="col g6"><h3 class="sl-ct">Why messages failed</h3><?php foreach ($dt['fails'] as $why => $n): ?><div class="cm-link"><span><?= h($why) ?></span><b><?= (int) $n ?></b></div><?php endforeach; ?></div><?php endif; ?>
      <?php if (!$dt['timeline'] && !$dt['links']): ?><p class="mut sm">No opens or clicks recorded. E-mails sent before tracking was switched on, and SMS or WhatsApp, only show delivery.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sl-card flush">
<div class="sl-tw"><table class="sl-table"><thead><tr><th>Campaign</th><th>Channel</th><th>Audience</th><th class="num">Sent</th><th>Delivered</th><th class="num">Opened</th><th class="num">Clicked</th><th class="num">Failed</th><th class="num">Cost</th></tr></thead><tbody>
<?php foreach ($d['campaigns'] as $c): ?><tr class="<?= $dt && $dt['id'] === $c['id'] ? 'on' : '' ?>">
  <td><a class="col" href="<?= h(sl_url(['batch' => $c['id']])) ?>#campaign"><b class="fw5"><?= h($c['title']) ?><?= $c['running'] ? ' <span class="sl-badge warn">Sending</span>' : '' ?></b><span class="mut sm"><?= h($c['src']) ?> · <?= h($c['started']) ?> · by <?= h($c['who']) ?></span></a></td>
  <td><?php foreach ($c['chs'] as $x): ?><span class="cm-chip <?= h($x) ?>"><?= h($chL[$x][0] ?? $x) ?></span><?php endforeach; ?></td>
  <td class="sm"><?= h($c['aud'] ?: '—') ?></td>
  <td class="num"><?= sl_num($c['sent']) ?></td>
  <td><span class="cm-mini"><i style="width:<?= (int) $c['deliv'] ?>%"></i></span> <span class="sm"><?= (int) $c['deliv'] ?>%</span></td>
  <td class="num"><?= $c['opens'] === null ? '<span class="mut">—</span>' : sl_num($c['opens']) . ' <span class="mut sm">' . (int) $c['openP'] . '%</span>' ?></td>
  <td class="num"><?= $c['clicks'] === null ? '<span class="mut">—</span>' : sl_num($c['clicks']) . ' <span class="mut sm">' . (int) $c['clickP'] . '%</span>' ?></td>
  <td class="num<?= $c['failed'] ? ' bad' : '' ?>"><?= sl_num($c['failed']) ?></td>
  <td class="num"><?= h($c['cost']) ?></td>
</tr><?php endforeach; ?>
<?php if (!$d['campaigns']): ?><tr><td colspan="9"><div class="sl-empty">No campaigns in this period.</div></td></tr><?php endif; ?>
</tbody></table></div></section>
<?php endif; ?>
<style>
.cm-f{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.cm-f>a{padding:7px 14px;border-radius:999px;border:1px solid var(--line-2);background:var(--field);font-size:14px;color:var(--ink-2)}.cm-f>a.on{background:var(--ink);color:var(--inverse);border-color:var(--ink)}.cm-f .grow{flex:1}
.cm-q{display:flex;align-items:center;gap:8px;height:38px;padding:0 12px;border-radius:12px;border:1px solid var(--line-2);background:var(--field);color:var(--muted)}.cm-q input{all:unset;color:var(--ink);font-size:14px;width:200px}
.cm-ch{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.cm-c,.cm-tot{display:flex;flex-direction:column;gap:8px;padding:16px;border-radius:var(--r-lg);background:var(--card);border:1px solid var(--line);color:var(--ink)}
.cm-c.on,.cm-tot.on{border-color:var(--ink);box-shadow:0 0 0 1px var(--ink)}
.cm-tot{background:var(--ink);color:var(--inverse)}.cm-tot small{opacity:.7;font-size:12px;letter-spacing:.06em;text-transform:uppercase}.cm-tot b{font-size:28px;font-weight:600}.cm-tot span{opacity:.75;font-size:13px}
.cm-big{font-size:26px;font-weight:600}.cm-big small{font-size:13px;color:var(--muted);font-weight:400}
.cm-bar,.cm-mini{display:block;height:6px;border-radius:99px;background:var(--red-bg);overflow:hidden}.cm-bar i,.cm-mini i{display:block;height:100%;background:var(--green)}
.cm-mini{display:inline-block;width:70px;vertical-align:middle}
.cm-kv{display:flex;justify-content:space-between;gap:8px;font-size:12.5px;color:var(--muted)}.cm-kv b{color:var(--ink)}
.cm-chip{display:inline-block;font-size:11.5px;font-weight:600;padding:2px 8px;border-radius:99px;margin-right:4px;background:var(--hover)}.cm-chip.email{background:var(--sky-bg);color:var(--sky-ink)}.cm-chip.sms{background:var(--violet-bg);color:var(--violet-ink)}.cm-chip.whatsapp{background:var(--green-bg);color:var(--green-ink)}
.sl-table td.bad{color:var(--red-ink);font-weight:600}.sl-table tr.on td{background:var(--hover)}
.cm-fun{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.cm-fun div{position:relative;overflow:hidden;padding:12px 14px;border-radius:12px;background:var(--hover);display:flex;flex-direction:column}.cm-fun small{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}.cm-fun b{font-size:24px;font-weight:600}.cm-fun span{font-size:13px;color:var(--muted)}.cm-fun i{position:absolute;left:0;bottom:0;height:4px;background:var(--ink)}
.cm-dg{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);gap:18px;align-items:start}
.cm-tl{display:flex;align-items:flex-end;gap:2px;height:90px;padding:6px;border-radius:12px;background:var(--hover)}.cm-tl span{flex:1;height:100%;display:flex;align-items:flex-end;gap:1px}.cm-tl i{flex:1;border-radius:2px 2px 0 0}.cm-tl .o,.cm-dot.o{background:var(--sky)}.cm-tl .c,.cm-dot.c{background:var(--green)}
.cm-dot{display:inline-block;width:8px;height:8px;border-radius:2px;margin:0 3px 0 8px}
.cm-link{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:10px;background:var(--hover);font-size:13px}.cm-link span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cm-link small{color:var(--muted)}
@media (max-width:900px){.cm-dg{grid-template-columns:1fr}.cm-fun{grid-template-columns:repeat(2,1fr)}}
</style>
