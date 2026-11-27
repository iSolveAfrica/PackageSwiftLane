<?php
/**
 * DevAlerts - alerts management (dark DevOps theme, assets/swiftlane/dev_theme.css).
 * Data: views/sl/data/dev_infra.php sl_dinfAlertsData: cdb_dev_alerts (built-in service checks of
 * helpers/devops.php + rules of cdb_dev_alert_rules evaluated by cron/dev_alerts.php), e-mail state in
 * cdb_dev_state. Writes: ajax/sl/dev_infra_ajax.php. Detail drawer and rule dialog: dataJs/sl/dev_infra.js.
 */
if (!isset($d)) { $d = []; }
$dvxActive = 'alerts';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
$ns = $d['notify'];
$ajax = $d['ajax'];
?>
<div class="dvx-page dal" data-dinf-page="alerts" data-dinf-ajax="<?= h($ajax) ?>">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">Alerts</h1><p class="dvx-lede"><?= (int) $d['openN'] ?> open · scheduled evaluation <?= $d['lastEval'] ? 'last ran ' . h(sl_ago($d['lastEval'])) : 'never recorded: schedule <span class="mono">cron/dev_alerts.php</span> every 5 minutes' ?><?= $d['lastManual'] ? ' · evaluated by hand ' . h(sl_ago($d['lastManual'])) : '' ?> · e-mails <?= $ns['on'] ? 'on' : 'off' ?></p></div>
  <div class="row g8 wrap"><button type="button" class="dvx-btn" data-sl-action="<?= h($ajax) ?>" data-p-op="evaluate"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Evaluate rules now</button><?php if ($d['rulesReady']): ?><button type="button" class="dvx-btn primary" data-dal-rule="{}"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New rule</button><?php endif; ?></div>
</header>

<section class="dal-totals" aria-label="Alert totals">
<?php foreach ($d['totals'] as $t): ?>
  <a class="dal-tot <?= h($t['tone']) ?><?= $d['sev'] === $t['k'] ? ' on' : '' ?>" href="<?= h($t['href']) ?>"><span class="dal-tot-h"><span class="dal-tot-i"><i class="ic ic-sm <?= h($t['icon']) ?>" aria-hidden="true"></i></span><?= h($t['t']) ?></span><b class="mono"><?= h((string) $t['n']) ?></b><small><?= h($t['sub']) ?></small></a>
<?php endforeach; ?>
</section>

<?php if (!empty($d['selOutside'])): ?><div class="dvx-note" role="note"><i class="ic i-info" aria-hidden="true"></i><span>The alert you opened is not in this filter. <a class="dvx-link" href="<?= h($d['selOutside']) ?>">Show all alerts</a></span></div><?php endif; ?>

<section class="dvx-card dvx-flush" aria-labelledby="dal-list" data-sl-scope>
  <div class="dvx-ch pad">
    <div><h2 class="dvx-ct" id="dal-list">Alerts</h2><p class="dvx-cs">Click an alert for its detail and timeline · acknowledge to take ownership</p></div>
    <div class="row g8 wrap">
      <nav class="dvx-seg boxed" aria-label="Status"><?php foreach ($d['tabs'] as $t): ?><a class="<?= h($t['cls']) ?>" href="<?= h($t['href']) ?>"<?= $t['cls'] ? ' aria-current="page"' : '' ?>><span><?= h($t['label']) ?> <span class="dvx-mut"><?= (int) $t['n'] ?></span></span></a><?php endforeach; ?></nav>
      <label class="dvx-sel"><span class="sr-only">Severity</span><select data-dinf-nav><?php foreach ($d['sevs'] as $s): ?><option value="<?= h($s['href']) ?>"<?= $s['cls'] ? ' selected' : '' ?>><?= h($s['label']) ?></option><?php endforeach; ?></select></label>
      <form class="dvx-search" method="get" role="search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="hidden" name="tab" value="<?= h($d['tab']) ?>"><?php if ($d['sev']): ?><input type="hidden" name="sev" value="<?= h($d['sev']) ?>"><?php endif; ?><input name="q" value="<?= h($d['q']) ?>" placeholder="Search alerts" aria-label="Search alerts"></form>
    </div>
  </div>
  <div class="dvx-tw"><table class="dvx-table dal-table">
    <thead><tr><th scope="col">Alert · service</th><th scope="col">Status</th><th scope="col">Severity</th><th scope="col">Triggered</th><th scope="col">Duration</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($d['alerts'] as $a): ?>
      <tr class="dal-row<?= $a['sel'] ? ' on' : '' ?>" tabindex="0" data-dinf-alert="<?= h(json_encode($a)) ?>" aria-label="<?= h($a['t'] . ', ' . $a['stLabel'] . ', ' . $a['sev']) ?>">
        <td><span class="row g10 dvx-min"><span class="dvx-dot <?= $a['st'] === 'resolved' ? 'ok' : h($a['sevCls']) ?>" aria-hidden="true"></span><span class="col dvx-min"><b class="fw5 dal-t"><?= h($a['t']) ?></b><small class="dvx-mut dal-sub"><span class="dal-svc"><?= h($a['svc']) ?></span> · <?= h($a['value'] !== '' ? $a['value'] . ($a['limit'] ? ' · limit ' . $a['limit'] : '') : $a['detail']) ?></small></span></span></td>
        <td><span class="dvx-pill <?= h($a['stCls']) ?>"><?= h($a['stLabel']) ?></span></td>
        <td><span class="dvx-sev <?= h($a['sevCls']) ?>"><?= h(ucfirst($a['sev'])) ?></span></td>
        <td class="mono" title="<?= h($a['first']) ?>"><?= h($a['firstAgo']) ?></td>
        <td class="mono"><?= h($a['dur']) ?></td>
        <td class="num"><span class="row g6 dal-acts"><?php if ($a['st'] === 'active'): ?><button type="button" class="dvx-ib" title="Acknowledge: take ownership" aria-label="Acknowledge <?= h($a['t']) ?>" data-sl-action="<?= h($ajax) ?>" data-p-op="alert_ack" data-p-id="<?= (int) $a['id'] ?>"><i class="ic ic-sm i-user" aria-hidden="true"></i></button><?php endif; ?><?php if ($a['st'] !== 'resolved'): ?><button type="button" class="dvx-ib" title="Resolve" aria-label="Resolve <?= h($a['t']) ?>" data-sl-action="<?= h($ajax) ?>" data-p-op="alert_resolve" data-p-id="<?= (int) $a['id'] ?>"><i class="ic ic-sm i-check" aria-hidden="true"></i></button><?php endif; ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$d['alerts']): ?><div class="dvx-empty"><i class="ic i-check" aria-hidden="true"></i><?= $d['tab'] === 'open' && !$d['sev'] && $d['q'] === '' ? 'No open alerts. Everything watched is within its limits.' : 'No alert matches this filter.' ?></div><?php endif; ?></div>
</section>

<div class="dvx-grid dal-g">
  <section class="dvx-card" aria-labelledby="dal-trend">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dal-trend">Alerts opened per day</h2><p class="dvx-cs">Last 14 days, by severity</p></div><span class="dvx-legend"><span><i style="background:var(--red)"></i>Critical</span><span><i style="background:var(--yellow)"></i>Warning</span><span><i style="background:var(--sky)"></i>Info</span></span></div>
    <div class="dal-bars" role="img" aria-label="Alerts opened per day, last 14 days">
      <?php foreach ($d['perDay'] as $p): ?><a class="dal-bar" href="<?= h(sl_url(['tab' => 'all', 'q' => null, 'alert' => null])) ?>" title="<?= h($p['l'] . ': ' . $p['n'] . ' (' . $p['c'] . ' critical, ' . $p['w'] . ' warning, ' . $p['i'] . ' info)') ?>"><span class="dal-stack"><i class="i" style="height:<?= $p['hi'] ?>%"></i><i class="w" style="height:<?= $p['hw'] ?>%"></i><i class="c" style="height:<?= $p['hc'] ?>%"></i></span><small><?= h($p['short']) ?></small></a><?php endforeach; ?>
    </div>
    <div class="row between dvx-mut sm"><span><?= h($d['perDay'][0]['l']) ?></span><span>peak <?= (int) $d['perDayMax'] ?> / day</span><span>Today</span></div>
    <h3 class="dvx-h3">Most frequent sources · 14 days</h3>
    <div class="dvx-bars"><?php foreach ($d['sources'] as $x): ?><a class="dvx-bar" href="<?= h($x['href']) ?>"><span class="dvx-ell"><?= h($x['svc']) ?></span><span class="dvx-bar-t"><i style="width:<?= max(2, $x['pct']) ?>%"></i></span><b class="mono"><?= (int) $x['n'] ?></b><small><?= (int) $x['n'] === 1 ? 'alert' : 'alerts' ?></small></a><?php endforeach; ?><?php if (!$d['sources']): ?><span class="dvx-mut sm">No alert in 14 days.</span><?php endif; ?></div>
  </section>
  <section class="dvx-card" aria-labelledby="dal-ntf">
    <div class="dvx-ch"><div><h2 class="dvx-ct" id="dal-ntf"><i class="ic ic-sm i-mail" aria-hidden="true"></i>Notification channel</h2><p class="dvx-cs">E-mail to the developers assigned to this console when an alert opens (sent after each rule evaluation)</p></div></div>
    <form class="col g10" data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post">
      <input type="hidden" name="op" value="notify_save">
      <label class="row g8 dal-check"><input type="checkbox" name="on" value="1"<?= $ns['on'] ? ' checked' : '' ?>><span>Send alert e-mails</span></label>
      <label class="row g8 dal-check"><input type="checkbox" name="builtin" value="1"<?= $ns['builtin'] ? ' checked' : '' ?>><span>Include built-in service checks (web, API, database, e-mail, WhatsApp...)</span></label>
      <label class="sl-field"><span class="sl-lab">Minimum severity</span><span class="dvx-sel"><select name="min"><option value="warning"<?= $ns['min'] === 'warning' ? ' selected' : '' ?>>Warning and critical</option><option value="critical"<?= $ns['min'] === 'critical' ? ' selected' : '' ?>>Critical only</option></select></span></label>
      <label class="sl-field"><span class="sl-lab">Extra addresses (optional, comma separated)</span><input class="sl-in" name="extra" value="<?= h(implode(', ', $ns['extra'])) ?>" placeholder="oncall@example.com"></label>
      <div class="dal-rcpt"><span class="dvx-mut sm">Recipients now</span><?php foreach ($d['recipients'] as $r): ?><span class="dvx-pill idle" title="<?= h($r['email']) ?>"><?= h($r['name']) ?></span><?php endforeach; ?><?php if (!$d['recipients']): ?><span class="dvx-pill warn">nobody: no developer has an e-mail address</span><?php endif; ?></div>
      <p class="dvx-cs">Mailer: <?= h($d['mailer'] ?: 'not set') ?> (Settings › E-mail). Rules choose e-mail or no e-mail one by one.</p>
      <div class="row g8 wrap"><button type="submit" class="dvx-btn primary">Save</button><button type="button" class="dvx-btn ghost" data-sl-action="<?= h($ajax) ?>" data-p-op="notify_test">Send a test e-mail</button></div>
    </form>
  </section>
</div>

<section class="dvx-card dvx-flush" aria-labelledby="dal-rules" id="rules">
  <div class="dvx-ch pad"><div><h2 class="dvx-ct" id="dal-rules">Alert rules</h2><p class="dvx-cs"><?= count($d['rules']) ?> rules on measured metrics, plus the built-in service checks that run with every server sample · value now is measured as you load this page</p></div><?php if ($d['rulesReady']): ?><button type="button" class="dvx-btn sm" data-dal-rule="{}"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New rule</button><?php endif; ?></div>
  <div class="dvx-tw"><table class="dvx-table dal-rules">
    <thead><tr><th scope="col">Rule</th><th scope="col">Condition</th><th scope="col">Value now</th><th scope="col">Severity</th><th scope="col">Notify</th><th scope="col">On</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php foreach ($d['rules'] as $r): ?>
      <tr>
        <td><b class="fw5"><?= h($r['label']) ?></b></td>
        <td class="mono sm"><?= h($r['mlabel'] . ' ' . $r['cmp'] . ' ' . $r['th'] . ($r['unit'] ? ' ' . $r['unit'] : '') . ($r['windowed'] && $r['win'] ? ' over ' . $r['win'] . ' min' : '')) ?></td>
        <td><span class="row g8"><span class="dvx-dot <?= !$r['on'] ? 'off' : (!$r['measured'] ? 'off' : ($r['fires'] ? 'bad' : 'ok')) ?>" aria-hidden="true"></span><span class="mono <?= $r['fires'] ? 'dvx-bad' : (!$r['measured'] ? 'dvx-mut' : '') ?>"><?= h($r['now']) ?></span></span></td>
        <td><span class="dvx-sev <?= $r['sev'] === 'critical' ? 'bad' : ($r['sev'] === 'info' ? 'info' : 'warn') ?>"><?= h(ucfirst($r['sev'])) ?></span></td>
        <td class="sm"><?= $r['notify'] === 'email' ? 'E-mail' : '<span class="dvx-mut">None</span>' ?></td>
        <td><button class="sl-toggle <?= $r['on'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $r['on'] ? 'true' : 'false' ?>" aria-label="<?= h($r['label']) ?> on or off" data-sl-save="<?= h($ajax) ?>" data-p-op="rule_toggle" data-p-id="<?= (int) $r['id'] ?>"></button></td>
        <td class="num"><span class="row g6 dal-acts"><button type="button" class="dvx-btn sm" data-dal-rule="<?= h($r['json']) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</button><button type="button" class="dvx-ib" aria-label="Delete rule <?= h($r['label']) ?>" data-sl-action="<?= h($ajax) ?>" data-p-op="rule_delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete the rule &quot;<?= h($r['label']) ?>&quot;? Its open alert is closed."><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$d['rulesReady']): ?><div class="dvx-empty"><i class="ic i-info" aria-hidden="true"></i>The rules table is missing: run migrations/2026_11_06_freight_devops.sql.</div><?php elseif (!$d['rules']): ?><div class="dvx-empty"><i class="ic i-info" aria-hidden="true"></i>No rules yet. Add one with New rule.</div><?php endif; ?></div>
</section>

<?php if ($d['rulesReady']): ?>
<div class="sl-dialog" id="dal-rule-dlg" hidden><form class="sl-modal dinf-modal" data-sl-form data-sl-reload action="<?= h($ajax) ?>" method="post" aria-label="Alert rule">
  <input type="hidden" name="op" value="rule_save"><input type="hidden" name="id" value="">
  <h2 class="dvx-ct" data-dal-t>New alert rule</h2>
  <label class="sl-field"><span class="sl-lab">Name</span><input class="sl-in" name="label" required maxlength="150" placeholder="Error rate above 5% for 15 minutes"></label>
  <label class="sl-field"><span class="sl-lab">Watch</span><span class="dvx-sel"><select name="metric"><?php foreach ($d['metrics'] as $mk => $m): ?><option value="<?= h($mk) ?>" data-unit="<?= h($m[1]) ?>" data-win="<?= $m[2] ? '1' : '0' ?>" data-desc="<?= h($m[3]) ?>"><?= h($m[0]) ?></option><?php endforeach; ?></select></span><small class="dvx-cs" data-dal-desc></small></label>
  <div class="row g10 dal-cond"><label class="sl-field" style="width:96px"><span class="sl-lab">When</span><span class="dvx-sel"><select name="comparator"><option value="&gt;">&gt;</option><option value="&gt;=">&gt;=</option><option value="&lt;">&lt;</option><option value="&lt;=">&lt;=</option><option value="=">=</option></select></span></label><label class="sl-field grow"><span class="sl-lab">Limit <span data-dal-unit class="dvx-mut"></span></span><input class="sl-in mono" name="threshold" inputmode="decimal" required></label></div>
  <label class="sl-field" data-dal-win><span class="sl-lab">Over the last (minutes)</span><input class="sl-in mono" name="window_min" type="number" min="1" max="10080" value="60"></label>
  <div class="row g10"><label class="sl-field grow"><span class="sl-lab">Severity</span><span class="dvx-sel"><select name="severity"><option value="warning">Warning</option><option value="critical">Critical</option><option value="info">Info</option></select></span></label><label class="sl-field grow"><span class="sl-lab">Notify</span><span class="dvx-sel"><select name="notify"><option value="email">E-mail the developers</option><option value="none">No e-mail</option></select></span></label></div>
  <div class="row g8"><button type="submit" class="dvx-btn primary">Save rule</button><button type="button" class="dvx-btn ghost" data-sl-close>Cancel</button></div>
</form></div>
<?php endif; ?>
</div>
<?= sl_dvxSubnavEnd() ?>
