<?php
/**
 * LockerSearch - from docs/handover/artifact-source/LockerSearch.dc.html.
 * Data: views/sl/data/warehouse.php (sl_lockerSearchData): customer by locker, name, phone, e-mail or tracking; parcels not yet handed over; virtual mailbox.
 */
if (!isset($d)) { $d = []; }
$u = $d['u'];
?>
        <div class="sl-head"><div><h1 class="sl-title">Locker Search</h1><p class="sl-lede">Find a customer’s locker, virtual mailbox and parcels in one lookup</p></div></div>
        <form class="row wrap g8" method="get">
          <div class="sl-search" style="max-width: none; height: 52px; flex: 1 1 320px"><i class="ic i-search" aria-hidden="true"></i><input name="q" aria-label="Search" value="<?= h($d['q']) ?>" placeholder="Locker, name, phone, e-mail or tracking number" autofocus></div>
          <button class="sl-btn" style="height: 52px; padding: 0 28px">Search</button>
        </form>
        <?php if (count($d['matches']) > 1): ?><div class="row g6 wrap"><?php foreach ($d['matches'] as $m): ?><a class="np-chip" style="padding-right:12px;<?= $m['on'] ? 'background:var(--ink);color:var(--inverse)' : '' ?>" href="<?= h(sl_url(['id' => $m['id']])) ?>"><?= h($m['name']) ?> · <?= h($m['lock']) ?></a><?php endforeach; ?></div><?php endif; ?>
        <?php if (!$u): ?><div class="sl-card"><div class="sl-empty"><span class="sl-tile"><i class="ic i-lock" aria-hidden="true"></i></span><?= $d['q'] === '' ? 'Type a locker number, a name or a tracking number.' : 'No customer matches “' . h($d['q']) . '”.' ?></div></div>
        <?php else: ?>
        <div class="split">
          <div class="a col g16">
            <section class="sl-card row g16 wrap">
              <span class="sl-av lg dark"><?= h($u['ini']) ?></span>
              <div class="grow"><div class="row g8 wrap"><h2 class="sl-ct" style="font-size: 18px"><?= h($u['name']) ?></h2><span class="sl-badge <?= $u['ok'] ? 'ok' : 'warn' ?>"><?= $u['ok'] ? 'Verified' : 'Not active' ?></span></div><div class="mut"><?= h($u['lock']) ?> · <?= h($u['email']) ?> · <?= h($u['phone']) ?></div></div>
              <?php if ($d['canProfile']): ?><a class="sl-btn ghost" href="<?= sl_route('CustomerProfile', ['id' => $u['id']]) ?>">Open profile</a><?php endif; ?>
              <a class="sl-btn ghost" href="<?= sl_route('HandOut', ['customer' => $u['id']]) ?>">Hand out</a>
            </section>
            <section class="sl-card flush">
              <div class="row between" style="padding: 16px 20px"><h2 class="sl-ct">Parcels held for <?= h(explode(' ', $u['name'])[0]) ?></h2><span class="mut sm"><?= (int) $d['inWh'] ?> in warehouse · <?= (int) $d['way'] ?> on the way</span></div>
              <div class="sl-tw"><table class="sl-table"><thead><tr><th>Parcel</th><th>Carrier tracking</th><th>Bay</th><th class="num">Weight</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($d['parcels'] as $p): ?><tr><td><a class="id" href="<?= h($p['href']) ?>"><?= h($p['id']) ?></a></td><td class="mono sm mut"><?= h($p['post']) ?></td><td class="mono sm"><?= h($p['bay']) ?></td><td class="num"><?= h($p['w']) ?></td><td><span class="sl-pill <?= h($p['cls']) ?>"><?= h($p['st']) ?></span></td></tr><?php endforeach; ?>
                <?php if (!$d['parcels']): ?><tr><td colspan="5"><div class="sl-empty">No open parcels.</div></td></tr><?php endif; ?>
              </tbody></table></div>
            </section>
          </div>
          <aside class="b col g16">
            <section class="sl-card col g12"><div class="row between"><h2 class="sl-ct">Virtual mailbox</h2><button class="sl-ib sm" type="button" aria-label="Copy address" data-sl-copy="<?= h($d['copy']) ?>"><i class="ic i-copy" aria-hidden="true"></i></button></div>
              <div class="sl-kv"><?php foreach ($d['mailbox'] as $m): ?><div><span><?= h($m[0]) ?></span><span><?= h($m[1]) ?></span></div><?php endforeach; ?></div>
            </section>
            <section class="sl-card col g10"><div class="row between"><span class="sl-ct">Locker usage</span><span class="sl-badge ok"><?= (int) $d['bayN'] ?> bay<?= $d['bayN'] === 1 ? '' : 's' ?></span></div><div class="sl-mv" style="margin: 0"><?= (int) $d['inWh'] ?> <i>parcels at the warehouse</i></div><div class="sl-ticks"><i style="width: <?= sl_pct($d['inWh'], max(1, $d['inWh'] + $d['way'])) ?>%"></i></div></section>
          </aside>
        </div>
        <?php endif; ?>
