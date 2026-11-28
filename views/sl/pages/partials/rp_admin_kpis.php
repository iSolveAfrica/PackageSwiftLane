<?php
/** Route planner KPIs: ready / allocated / on the way / delivered today / failed. Input $d['kpis']. Live values refresh from op=state (data-rp-kpi). */
?>
<div class="rp-kpis" role="list"><?php foreach ((array) ($d['kpis'] ?? []) as $k): ?>
<div class="rp-k rp-k-<?= h($k['k']) ?>" role="listitem"><div class="row g8"><span class="rp-ki"><i class="ic ic-sm <?= h($k['icon']) ?>" aria-hidden="true"></i></span><span class="rp-kl"><?= h($k['l']) ?></span></div><b class="rp-kv mono" data-rp-kpi="<?= h($k['k']) ?>"><?= (int) $k['v'] ?></b><span class="rp-ks mut"><?= h($k['s']) ?></span></div>
<?php endforeach; ?></div>
