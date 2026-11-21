<?php
/**
 * Documents: every invoice, receipt, label, manifest and export anyone printed or downloaded,
 * with an advanced search (type, person, record or customer, dates). Full width.
 * Data: views/sl/data/documents.php (sl_documentsData). Exports themselves are made from Reports.
 */
if (!isset($d)) { $d = []; }
$fc = $d['facets'];
$adv = $d['by'] || $d['from'] !== '' || $d['to'] !== '';
?>
<div class="sl-head"><div><h1 class="sl-title row g8"><i class="ic i-file" aria-hidden="true"></i>Documents</h1><p class="sl-lede">Every invoice, receipt, label, manifest and export <?= $d['all'] ? 'anyone on the team' : 'you' ?> printed or downloaded. Search by tracking number, customer, person or date and open any of them again.</p></div>
<div class="row g8 wrap"><?php if ($d['canLabels']): ?><a class="sl-btn ghost" href="<?= sl_route('LabelStudio') ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print labels</a><?php endif; ?><?php if ($d['canReports']): ?><a class="sl-btn ghost" href="<?= sl_route('Reports') ?>"><i class="ic ic-sm i-chart" aria-hidden="true"></i>Reports</a><?php endif; ?></div></div>

<?php if ($d['tiles']): ?><div class="dc-tiles"><?php foreach ($d['tiles'] as [$tl, $tn, $ti]): ?><div class="dc-tile"><i class="ic <?= h($ti) ?>" aria-hidden="true"></i><span class="col"><b><?= sl_num($tn) ?></b><small><?= h($tl) ?></small></span></div><?php endforeach; ?></div><?php endif; ?>

<form method="get" class="dc-search" role="search">
  <input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
  <label class="dc-q"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search documents" placeholder="Tracking number, customer, locker, person or document name"></label>
  <?php if ($d['all'] && $d['people']): ?><label class="dc-f"><span>Created by</span><select name="by"><option value="0">Anyone</option><?php foreach ($d['people'] as $p): ?><option value="<?= (int) $p->id ?>"<?= (int) $p->id === $d['by'] ? ' selected' : '' ?>><?= h($p->name ?: 'User ' . $p->id) ?> · <?= (int) $p->n ?></option><?php endforeach; ?></select></label><?php endif; ?>
  <label class="dc-f"><span>From</span><input type="date" name="from" value="<?= h($d['from']) ?>" max="<?= date('Y-m-d') ?>"></label>
  <label class="dc-f"><span>To</span><input type="date" name="to" value="<?= h($d['to']) ?>" max="<?= date('Y-m-d') ?>"></label>
  <button class="sl-btn" type="submit">Search</button>
  <?php if ($d['q'] !== '' || $adv || $d['filter'] !== 'all'): ?><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>">Clear</a><?php endif; ?>
</form>

<nav class="dc-types" aria-label="Document type"><?php foreach ($d['filters'] as $f): ?><a class="<?= $f['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $f['f'] === 'all' ? null : $f['f'], 'page' => null])) ?>"><?= h($f['label']) ?> <b><?= sl_num($fc[$f['f']] ?? 0) ?></b></a><?php endforeach; ?>
<span class="grow"></span><span class="mut sm"><?= sl_num($d['count']) ?> document<?= $d['count'] === 1 ? '' : 's' ?><?= $d['q'] !== '' ? ' for “' . h($d['q']) . '”' : '' ?></span></nav>

<section class="sl-card flush">
<div class="sl-tw"><table class="sl-table dc-table"><thead><tr><th>Document</th><th>Records</th><th>Customer</th><th>Created by</th><th>When</th><th></th></tr></thead><tbody>
<?php foreach ($d['rows'] as $r): ?><tr>
  <td><div class="sl-cell"><span class="dc-ic <?= h($r['cat']) ?>"><i class="ic ic-sm <?= h($r['icon']) ?>" aria-hidden="true"></i></span><span class="col"><?php if ($r['href']): ?><a class="fw5" href="<?= h($r['href']) ?>" target="_blank" rel="noopener"><?= h($r['title']) ?></a><?php else: ?><span class="fw5"><?= h($r['title']) ?></span><?php endif; ?><span class="mut sm"><?= h(ucfirst($r['cat'])) ?><?= $r['n'] > 1 ? ' · ' . $r['n'] . ' records' : '' ?><?= $r['range'] !== '' ? ' · ' . h($r['range']) : '' ?></span></span></div></td>
  <td><?php if ($r['recs']): ?><span class="dc-recs"><?php foreach ($r['recs'] as $rc): ?><a class="mono" href="<?= h(sl_url(['q' => $rc, 'page' => null])) ?>"><?= h($rc) ?></a><?php endforeach; ?><?php if ($r['more']): ?><span class="mut sm">+<?= (int) $r['more'] ?></span><?php endif; ?></span><?php else: ?><span class="mut">—</span><?php endif; ?></td>
  <td><?= $r['who'] !== '' ? h($r['who']) : '<span class="mut">—</span>' ?></td>
  <td><?= h($r['by']) ?></td>
  <td><span class="col"><span><?= h($r['date']) ?></span><span class="mut sm"><?= h($r['ago']) ?></span></span></td>
  <td><?php if ($r['href']): ?><a class="sl-btn ghost sm" href="<?= h($r['href']) ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-download" aria-hidden="true"></i>Open</a><?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$d['rows']): ?><tr><td colspan="6"><div class="sl-empty"><span class="sl-tile"><i class="ic i-file" aria-hidden="true"></i></span><?= $d['q'] !== '' || $adv ? 'No documents match this search.' : 'No documents yet. Labels, receipts and exports appear here as soon as someone prints or downloads one.' ?></div></td></tr><?php endif; ?>
</tbody></table></div><?= sl_pager($d['page'], $d['per'], $d['count'], 'documents') ?></section>

<section class="sl-card col g12"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Where to print</h2><span class="mut sm">Each document is opened from the record it belongs to</span></div><div class="dc-prints"><?php foreach ($d['prints'] as $p): ?><div><span class="dc-ic <?= h($p['cat']) ?>"><i class="ic ic-sm <?= h($p['icon']) ?>" aria-hidden="true"></i></span><span class="col"><b class="fw5"><?= h($p['label']) ?></b><span class="mut sm"><?= h($p['from']) ?></span></span></div><?php endforeach; ?></div></section>
<style>
.dc-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.dc-tile{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:var(--r-lg);background:var(--card);border:1px solid var(--line)}
.dc-tile .ic{width:22px;height:22px;color:var(--muted)}
.dc-tile b{font-size:22px;font-weight:600;line-height:1.1}
.dc-tile small{color:var(--muted);font-size:13px}
.dc-search{display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;padding:14px;border-radius:var(--r-lg);background:var(--card);border:1px solid var(--line)}
.dc-q{flex:1 1 320px;display:flex;align-items:center;gap:10px;height:42px;padding:0 14px;border-radius:12px;border:1px solid var(--line-2);background:var(--field);color:var(--muted)}
.dc-q input{all:unset;flex:1;color:var(--ink);font-size:15px;min-width:0}
.dc-f{display:flex;flex-direction:column;gap:4px;font-size:12px;color:var(--muted)}
.dc-f select,.dc-f input{font:inherit;font-size:14px;color:var(--ink);height:42px;padding:0 10px;border-radius:12px;border:1px solid var(--line-2);background:var(--field);min-width:150px}
.dc-search .sl-btn{height:42px}
.dc-types{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.dc-types a{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:999px;border:1px solid var(--line-2);background:var(--field);font-size:14px;color:var(--ink-2)}
.dc-types a b{font-weight:600;font-size:12px;padding:1px 7px;border-radius:99px;background:var(--hover);color:var(--ink)}
.dc-types a.on{background:var(--ink);color:var(--inverse);border-color:var(--ink)}
.dc-types a.on b{background:rgba(255,255,255,.18);color:inherit}
.dc-types .grow{flex:1}
.dc-ic{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;flex:none;background:var(--hover);color:var(--ink-2)}
.dc-ic.invoices{background:var(--sky-bg);color:var(--sky-ink)}.dc-ic.receipts{background:var(--green-bg);color:var(--green-ink)}.dc-ic.labels{background:var(--amber-bg);color:var(--amber-ink)}.dc-ic.manifests{background:var(--violet-bg);color:var(--violet-ink)}
.dc-recs{display:flex;flex-wrap:wrap;gap:4px;align-items:center}
.dc-recs a{font-size:12.5px;padding:2px 7px;border-radius:6px;background:var(--hover);color:var(--ink)}
.dc-recs a:hover{background:var(--active)}
.dc-prints{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px}
.dc-prints>div{display:flex;gap:10px;align-items:center;padding:10px;border:1px solid var(--line);border-radius:12px}
@media (max-width:680px){.dc-f{flex:1 1 45%}.dc-f select,.dc-f input{min-width:0;width:100%}.dc-table th:nth-child(3),.dc-table td:nth-child(3){display:none}}
</style>
