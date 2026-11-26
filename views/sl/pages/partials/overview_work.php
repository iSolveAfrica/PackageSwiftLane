<?php if ($d['queues'] || $d['deskStats']): ?>
<section class="sl-card dk-desk solo" aria-label="Your desk">
          <div class="dk-qs">
            <div class="row between" style="margin-bottom:6px"><div class="up row g8"><i class="ic ic-sm i-grid" aria-hidden="true"></i>Your desk · <?= (int) $d['queueTotal'] ?> items in additional queues</div><span class="mut sm">More work queues and daily totals</span></div>
            <div class="dk-grid"><?php foreach ($d['queues'] as $q): ?><a class="dk-q" href="<?= h($q['href']) ?>"><span class="sl-tile"><i class="ic ic-sm <?= h($q['icon']) ?>" aria-hidden="true"></i></span><span class="grow col"><span class="fw5"><?= h($q['label']) ?></span><span class="sm" style="color:<?= h($q['tone']) ?>"><?= h($q['sub']) ?></span></span><b class="mono"><?= (int) $q['n'] ?></b><i class="ic ic-sm i-right mut" aria-hidden="true"></i></a><?php endforeach; ?><?php if (!$d['queues']): ?><div class="sl-empty">No queues are assigned to your role.</div><?php endif; ?></div>
            <?php include __DIR__ . '/overview_desk_stats.php'; ?>
          </div>
        </section>
<?php endif; ?>
