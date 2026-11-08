        <section class="sl-card" aria-label="Daily intake">
          <div class="sl-ch" style="margin-bottom: 2px"><h2 class="sl-ct">Daily Intake</h2><div class="sl-peak" style="margin: 0">Peak <b><?= (int) $d['yearPeak']['n'] ?> a day</b></div></div>
          <p class="mut" style="margin-bottom: 12px">Shipments registered per day — rolling year</p>
          <p class="sl-chart-hint">Hover or tap a day to see its shipment count</p>
          <div class="sl-heatbox">
            <div class="sl-heatdays" aria-hidden="true"><span></span><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span><span></span></div>
            <div class="sl-heatcol">
              <div class="sl-heatmonths" aria-hidden="true"><?php foreach ($d['yearMonths'] as $m): ?><span><?= h($m) ?></span><?php endforeach; ?></div>
              <div class="sl-heat" style="grid-template-columns: repeat(52, minmax(0, 1fr))" role="img" aria-label="Shipments registered per day over the last year">
                <?php foreach ($d['year'] as $c): ?><b title="<?= h($c['t']) ?>" class="<?= h($c['c']) ?>"></b><?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="sl-legend" style="justify-content: space-between"><span class="row g6">Quiet <b style="background: var(--heat-0)"></b><b style="background: var(--heat-1)"></b><b style="background: var(--heat-2)"></b><b style="background: var(--heat-3)"></b> Busy</span><span>Busiest day: <?= h($d['yearPeak']['day'] ?: '—') ?></span></div>
        </section>
