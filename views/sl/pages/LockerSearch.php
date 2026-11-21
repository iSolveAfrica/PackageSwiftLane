<?php
/**
 * Smart Search: one box for everything (customers and lockers, shipments, packages, pickups,
 * consolidations and containers, pre-alerts, recipients, tickets, staff, pages), with the AI
 * assistant one click away. Data: views/sl/data/smart_search.php (sl_smartSearchData).
 */
if (!isset($d)) { $d = []; }
$c = $d['cust'];
$kindL = ['tracking' => 'Looks like a tracking number', 'phone' => 'Looks like a phone number', 'email' => 'Looks like an e-mail address', 'locker' => 'Looks like a locker number', 'text' => ''][$d['kind']] ?? '';
?>
<div class="ss-hero">
  <h1 class="sl-title row g8"><i class="ic i-search" aria-hidden="true"></i>Smart Search</h1>
  <p class="sl-lede">Search anything: a tracking or store number, a locker, a customer, a phone, a container, a ticket, a person or a page.</p>
  <form method="get" class="ss-box" role="search">
    <i class="ic i-search" aria-hidden="true"></i>
    <input name="q" value="<?= h($d['q']) ?>" aria-label="Search everything" placeholder="Search shipments, customers, lockers, containers, tickets…" autofocus autocomplete="off">
    <?php if ($d['q'] !== ''): ?><a class="ss-x" href="<?= sl_route('LockerSearch') ?>" aria-label="Clear"><i class="ic ic-sm i-x" aria-hidden="true"></i></a><?php endif; ?>
    <button class="sl-btn" type="submit">Search</button>
  </form>
  <?php if ($d['q'] !== ''): ?>
  <div class="ss-scope">
    <a class="<?= $d['in'] === 'all' ? 'on' : '' ?>" href="<?= h(sl_url(['in' => null, 'cust' => null])) ?>">Everything <b><?= sl_num($d['total']) ?></b></a>
    <?php foreach ($d['counts'] ?? [] as $g): ?><a class="<?= $d['in'] === $g['key'] ? 'on' : '' ?>" href="<?= h(sl_url(['in' => $g['key'], 'cust' => null])) ?>"><?= h($g['label']) ?> <b><?= sl_num($g['n']) ?></b></a><?php endforeach; ?>
    <?php if ($kindL): ?><span class="ss-kind"><i class="ic ic-sm i-spark" aria-hidden="true"></i><?= h($kindL) ?></span><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($d['q'] === ''): ?>
<section class="ss-tips"><span class="mut sm">Try</span><?php foreach ($d['tips'] as [$tq, $tl]): ?><a href="<?= h(sl_url(['q' => $tq])) ?>"><b><?= h($tq) ?></b><span><?= h($tl) ?></span></a><?php endforeach; ?></section>
<?php if ($d['canAi']): ?><a class="ss-ai" href="<?= sl_route('StaffAI') ?>"><span class="ss-ai-ic"><i class="ic i-ai" aria-hidden="true"></i></span><span class="col"><b>Ask <?= h(cdp_kingName()) ?></b><span class="mut sm">“How many parcels are waiting more than 30 days?” · “Which customers owe the most?” · “What landed yesterday?”</span></span><i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?>
<?php else: ?>

<?php if ($d['best']): $b = $d['best']; ?>
<a class="ss-best" href="<?= h($b['href']) ?>"><span class="ss-best-ic"><i class="ic <?= h($b['gicon']) ?>" aria-hidden="true"></i></span><span class="col grow"><span class="up">Exact match · <?= h($b['group']) ?></span><b class="<?= !empty($b['mono']) ? 'mono' : '' ?>"><?= h($b['t']) ?></b><span class="mut sm"><?= h($b['s'] ?? '') ?></span></span><?php if (!empty($b['st'])): ?><span class="sl-pill <?= h($b['cls'] ?? '') ?>"><?= h($b['st']) ?></span><?php endif; ?><?php if (!empty($b['track'])): ?><span class="sl-btn ghost sm" onclick="event.preventDefault();location.href='<?= h($b['track']) ?>'"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track</span><?php endif; ?><span class="sl-btn sm">Open<i class="ic ic-sm i-right" aria-hidden="true"></i></span></a>
<?php endif; ?>

<?php if ($c): ?>
<section class="ss-cust">
  <div class="ss-ch">
    <span class="sl-av lg dark"><?= h($c['ini']) ?></span>
    <div class="col grow" style="min-width:0"><div class="row g8 wrap"><h2 class="sl-ct" style="font-size:20px"><?= h($c['name']) ?></h2><span class="sl-badge <?= $c['ok'] ? 'ok' : 'warn' ?>"><?= $c['ok'] ? 'Verified' : 'Not active' ?></span></div>
      <span class="mut">Locker <b class="mono" style="color:var(--ink)"><?= h($c['lock']) ?></b><?= $c['phone'] ? ' · ' . h($c['phone']) : '' ?><?= $c['email'] ? ' · ' . h($c['email']) : '' ?><?= $c['since'] ? ' · customer since ' . h($c['since']) : '' ?></span></div>
    <div class="row g8 wrap"><?php if ($c['canProfile']): ?><a class="sl-btn ghost" href="<?= sl_route('CustomerProfile', ['id' => $c['id']]) ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i>Profile</a><?php endif; ?><a class="sl-btn ghost" href="<?= sl_route('LiveTracking', ['q' => $c['lock'] !== '—' ? $c['lock'] : $c['name']]) ?>"><i class="ic ic-sm i-route" aria-hidden="true"></i>Track parcels</a><a class="sl-btn" href="<?= sl_route('HandOut', ['customer' => $c['id']]) ?>"><i class="ic ic-sm i-box" aria-hidden="true"></i>Hand out</a></div>
  </div>
  <div class="ss-stats"><?php foreach ([['At the warehouse', $c['inWh']], ['On the way', $c['way']], ['Not paid', $c['unpaid']], ['Open weight', $c['weight']], ['Shipped all time', $c['lifetime']], ['Last parcel', $c['last']]] as [$sl, $sv]): ?><div><small><?= h($sl) ?></small><b><?= is_int($sv) ? sl_num($sv) : h($sv) ?></b></div><?php endforeach; ?></div>
  <div class="ss-cgrid">
    <div class="sl-card flush"><div class="row between" style="padding:14px 18px"><h3 class="sl-ct">Open parcels</h3><span class="mut sm"><?= count($c['parcels']) ?> not handed over</span></div>
      <div class="sl-tw"><table class="sl-table"><thead><tr><th>Parcel</th><th>Carrier / store tracking</th><th>Bay</th><th class="num">Weight</th><th>Payment</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($c['parcels'] as $p): ?><tr><td><a class="id" href="<?= h($p['href']) ?>"><?= h($p['id']) ?></a><span class="mut sm" style="display:block"><?= $p['mode'] === 'sea' ? 'Sea' : 'Air' ?> · <?= h($p['age']) ?></span></td><td class="mono sm mut"><?= h($p['post']) ?></td><td class="mono sm"><?= h($p['bay']) ?></td><td class="num"><?= h($p['w']) ?></td><td><span class="sl-pill <?= h($p['payCls']) ?>"><?= h($p['pay']) ?></span></td><td><span class="sl-pill <?= h($p['cls']) ?>"><?= h($p['st']) ?></span></td></tr><?php endforeach; ?>
      <?php if (!$c['parcels']): ?><tr><td colspan="6"><div class="sl-empty">Nothing open: every parcel has been handed over.</div></td></tr><?php endif; ?>
      </tbody></table></div></div>
    <div class="sl-card col g12"><div class="row between"><h3 class="sl-ct">Virtual mailbox</h3><button class="sl-ib sm" type="button" aria-label="Copy every address" data-sl-copy="<?= h($c['copy']) ?>"><i class="ic i-copy" aria-hidden="true"></i></button></div>
      <?php foreach ($c['mailbox'] as $blk): ?><div class="ss-addr"><?php foreach ($blk as $li => $line): ?><span class="<?= $li === 0 ? 'fw5' : '' ?>"><?= h($line) ?></span><?php endforeach; ?></div><?php endforeach; ?>
      <?php if (!$c['mailbox']): ?><span class="mut sm">No locker address set. Add it in Settings.</span><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="ss-groups">
<?php foreach ($d['groups'] as $g): ?>
  <section class="ss-g">
    <div class="ss-gh"><span class="ss-gi"><i class="ic ic-sm <?= h($g['icon']) ?>" aria-hidden="true"></i></span><b><?= h($g['label']) ?></b><span class="mut sm"><?= sl_num($g['total']) ?></span><span class="grow"></span><?php if ($d['in'] === 'all' && $g['total'] > count($g['items'])): ?><a class="sm" href="<?= h(sl_url(['in' => $g['key'], 'cust' => null])) ?>">See all<i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?></div>
    <?php foreach ($g['items'] as $it): ?>
    <a class="ss-r<?= !empty($it['exact']) ? ' ex' : '' ?>" href="<?= h($it['href']) ?>">
      <?php if (isset($it['ini'])): ?><span class="sl-av sm"><?= h($it['ini']) ?></span><?php elseif (isset($it['icon'])): ?><span class="ss-gi"><i class="ic ic-sm <?= h($it['icon']) ?>" aria-hidden="true"></i></span><?php endif; ?>
      <span class="col grow" style="min-width:0"><b class="<?= !empty($it['mono']) ? 'mono' : '' ?>"><?= h($it['t']) ?></b><span class="mut sm ss-s"><?= h($it['s'] ?? '') ?></span></span>
      <?php if (!empty($it['badge'])): ?><span class="sl-badge warn"><?= h($it['badge']) ?></span><?php endif; ?>
      <?php if (!empty($it['m'])): ?><span class="mut sm"><?= h($it['m']) ?></span><?php endif; ?>
      <?php if (!empty($it['st'])): ?><span class="sl-pill <?= h($it['cls'] ?? '') ?>"><?= h($it['st']) ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
</div>
<?php if (!$d['groups']): ?><div class="sl-card"><div class="sl-empty"><span class="sl-tile"><i class="ic i-search" aria-hidden="true"></i></span>Nothing found for “<?= h($d['q']) ?>”. Check the spelling, try part of the number, or ask the AI assistant.</div></div><?php endif; ?>
<?php if ($d['canAi']): ?><a class="ss-ai" href="<?= sl_route('StaffAI', ['q' => $d['q']]) ?>"><span class="ss-ai-ic"><i class="ic i-ai" aria-hidden="true"></i></span><span class="col"><b>Ask <?= h(cdp_kingName()) ?> about “<?= h($d['q']) ?>”</b><span class="mut sm"><?= h(cdp_kingName()) ?> reads shipments, payments, riders and messages, and answers in plain words with links.</span></span><i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?>
<?php endif; ?>
<style>
.ss-hero{display:flex;flex-direction:column;gap:12px;padding:26px;border-radius:var(--r-xl);background:var(--panel);border:1px solid var(--line)}
.ss-box{display:flex;align-items:center;gap:12px;height:60px;padding:0 8px 0 20px;border-radius:18px;background:var(--field);border:1px solid var(--line-2);box-shadow:0 10px 30px -22px rgba(0,0,0,.35)}
.ss-box:focus-within{border-color:var(--ink)}
.ss-box>.ic{width:22px;height:22px;color:var(--muted)}
.ss-box input{all:unset;flex:1;font-size:18px;color:var(--ink);min-width:0}
.ss-box .sl-btn{height:44px;padding:0 24px}
.ss-x{color:var(--muted);display:inline-grid;place-items:center}
.ss-scope{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.ss-scope a{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;border:1px solid var(--line-2);background:var(--field);font-size:13.5px;color:var(--ink-2)}
.ss-scope a b{font-size:12px;font-weight:600;padding:0 6px;border-radius:99px;background:var(--hover);color:var(--ink)}
.ss-scope a.on{background:var(--ink);color:var(--inverse);border-color:var(--ink)}
.ss-scope a.on b{background:rgba(255,255,255,.2);color:inherit}
.ss-kind{margin-left:auto;display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--violet-ink)}
.ss-tips{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.ss-tips a{display:flex;flex-direction:column;padding:10px 14px;border-radius:14px;background:var(--card);border:1px solid var(--line);font-size:13px}
.ss-tips a span{color:var(--muted);font-size:12px}
.ss-ai{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:var(--r-lg);border:1px solid color-mix(in srgb,var(--violet) 30%,var(--line));background:color-mix(in srgb,var(--violet) 7%,var(--card));color:var(--ink)}
.ss-ai-ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:var(--violet);color:#fff;flex:none}
.ss-ai .col{flex:1}
.ss-best{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:16px 18px;border-radius:var(--r-lg);background:var(--card);border:2px solid var(--ink);color:var(--ink)}
.ss-best b{font-size:20px}
.ss-best-ic{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:var(--ink);color:var(--inverse)}
.ss-cust{display:flex;flex-direction:column;gap:14px;padding:18px;border-radius:var(--r-xl);background:var(--card);border:1px solid var(--line)}
.ss-ch{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.ss-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px}
.ss-stats div{padding:10px 12px;border-radius:12px;background:var(--hover);display:flex;flex-direction:column;gap:2px}
.ss-stats small{font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)}
.ss-stats b{font-size:18px;font-weight:600}
.ss-cgrid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(280px,1fr);gap:14px;align-items:start}
.ss-addr{display:flex;flex-direction:column;gap:2px;padding:10px 12px;border-radius:12px;background:var(--hover);font-size:13.5px;line-height:1.45;word-break:break-word}
.ss-groups{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(420px,100%),1fr));gap:14px;align-items:start}
.ss-g{display:flex;flex-direction:column;padding:8px;border-radius:var(--r-lg);background:var(--card);border:1px solid var(--line)}
.ss-gh{display:flex;align-items:center;gap:8px;padding:8px 10px 10px}
.ss-gh a{display:inline-flex;align-items:center;gap:4px;color:var(--ink-2)}
.ss-gi{width:28px;height:28px;border-radius:9px;display:grid;place-items:center;background:var(--hover);color:var(--ink-2);flex:none}
.ss-r{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:12px;color:var(--ink)}
.ss-r:hover{background:var(--hover)}
.ss-r.ex{background:color-mix(in srgb,var(--green) 10%,var(--card))}
.ss-r b{font-weight:500}
.ss-s{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media (max-width:900px){.ss-cgrid{grid-template-columns:1fr}.ss-kind{margin-left:0}}
@media (max-width:600px){.ss-hero{padding:16px}.ss-box{height:52px}.ss-box input{font-size:16px}.ss-box .sl-btn{display:none}}
</style>
