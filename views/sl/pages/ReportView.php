<?php
/** One report (Swift Lane design): filter bar, results table (from the legacy report endpoint), exports. */
$rep = $d['rep'];
$p = $rep['params'];
$from = date('Y-m-01');
$to = date('Y-m-d');
?>
        <div class="sl-head">
          <div><h1 class="sl-title"><?= h($rep['title']) ?></h1><p class="sl-lede"><?= h($rep['group']) ?> report</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_route('Reports')) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>All reports</a>
            <?php if ($rep['excel']): ?><button type="button" class="sl-btn ghost" data-rv-export="<?= h($rep['excel']) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Excel</button><?php endif; ?>
            <?php if ($rep['print']): ?><button type="button" class="sl-btn" data-rv-export="<?= h($rep['print']) ?>"><i class="ic ic-sm i-print" aria-hidden="true"></i>Print / PDF</button><?php endif; ?></div>
        </div>
        <?php if (count($d['siblings']) > 1): ?>
        <nav class="sl-tabs" aria-label="<?= h($rep['group']) ?> reports"><?php foreach ($d['siblings'] as $k => $r): ?><a class="<?= $k === $d['key'] ? 'on' : '' ?>" href="report_view.php?r=<?= h($k) ?>"><?= h($r['title']) ?></a><?php endforeach; ?></nav>
        <?php endif; ?>
        <section class="sl-card">
          <form class="row wrap g12" id="rv-filters" data-ajax="<?= h($rep['ajax']) ?>" style="align-items:flex-end">
            <?php if (in_array('range', $p, true)): ?>
            <div class="sl-field"><label class="sl-lab" for="rv-from">From</label><input class="sl-in" type="date" id="rv-from" value="<?= h($from) ?>"></div>
            <div class="sl-field"><label class="sl-lab" for="rv-to">To</label><input class="sl-in" type="date" id="rv-to" value="<?= h($to) ?>"></div>
            <?php endif; ?>
            <?php if (in_array('status_courier', $p, true)): ?>
            <div class="sl-field" style="min-width:180px"><label class="sl-lab" for="rv-status">Status</label><label class="sl-sel native"><select id="rv-status" name="status_courier"><option value="0">All statuses</option><?php foreach ($d['statuses'] as $s): ?><option value="<?= (int) $s->id ?>"><?= h($s->mod_style) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></div>
            <?php endif; ?>
            <?php foreach (['agency' => 'Agency', 'agency_courier' => 'Agency'] as $pk => $pl): if (in_array($pk, $p, true)): ?>
            <div class="sl-field" style="min-width:180px"><label class="sl-lab" for="rv-<?= $pk ?>"><?= $pl ?></label><label class="sl-sel native"><select id="rv-<?= $pk ?>" name="<?= $pk ?>"><option value="0">All agencies</option><?php foreach ($d['agencies'] as $a): ?><option value="<?= (int) $a->id ?>"><?= h($a->name_branch) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></div>
            <?php endif; endforeach; ?>
            <?php foreach (['method' => 'Payment method', 'pay_mode' => 'Payment method'] as $pk => $pl): if (in_array($pk, $p, true)): ?>
            <div class="sl-field" style="min-width:180px"><label class="sl-lab" for="rv-<?= $pk ?>"><?= $pl ?></label><label class="sl-sel native"><select id="rv-<?= $pk ?>" name="<?= $pk ?>"><option value="0">All methods</option><?php foreach ($d['methods'] as $m): ?><option value="<?= (int) $m->id ?>"><?= h($m->name_pay) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></div>
            <?php endif; endforeach; ?>
            <?php if (in_array('customer_id', $p, true)): ?>
            <div class="sl-field" style="min-width:240px"><label class="sl-lab" for="rv-cust">Customer</label><label class="sl-sel native"><select id="rv-cust" name="customer_id"><option value="0">All customers</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->n) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></div>
            <?php endif; ?>
            <?php if (in_array('employee_id', $p, true)): ?>
            <div class="sl-field" style="min-width:220px"><label class="sl-lab" for="rv-emp"><?= $rep['drivers'] ? 'Driver' : 'Employee' ?></label><label class="sl-sel native"><select id="rv-emp" name="employee_id"><option value="0">All</option><?php foreach ($d['employees'] as $e): ?><option value="<?= (int) $e->id ?>"><?= h($e->n) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label></div>
            <?php endif; ?>
            <button type="submit" class="sl-btn"><i class="ic ic-sm i-search" aria-hidden="true"></i>Run report</button>
          </form>
        </section>
        <section class="sl-card flush">
          <div id="rv-out" class="rv-out" aria-live="polite"><div class="sl-empty">Loading…</div></div>
        </section>
