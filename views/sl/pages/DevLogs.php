<?php
/**
 * DevLogs - log explorer (dark DevOps theme). Sources: PHP error log (CDP_ERROR_LOG), activity log,
 * message log, cron runs, request metrics, AI runs, alerts (views/sl/data/dev_observe.php sl_dvxLogs).
 * Filters work by GET; live tail, pause, detail panel (raw / JSON) and copy by dataJs/sl/dev_observe.js
 * through ajax/sl/dev_observe_ajax.php (read-only).
 */
if (!isset($d)) { $d = []; }
$sel = $d['sel'];
$dvxActive = 'logs';
include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php';
?>
<div class="dvx-page" data-dvx-page="logs" data-dvx-ajax="<?= h($d['ajax']) ?>">
<header class="dvx-head">
  <div class="col g4"><h1 class="dvx-title">Logs explorer</h1><p class="dvx-lede">PHP errors, application activity, messages, cron runs, requests, AI and alerts in one stream</p></div>
  <div class="row g8 wrap"><span class="dvx-live off" data-dvx-live><i class="dvx-pulse" aria-hidden="true"></i><span data-dvx-live-t>Live tail off</span></span><button class="dvx-btn" type="button" data-dvx-tailbtn aria-pressed="false"><i class="ic ic-sm i-play" aria-hidden="true"></i><span>Start live tail</span></button></div>
</header>

<section class="dvx-card">
  <form class="dvx-filters" method="get" data-dvx-logform>
    <label class="dvx-search grow"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($d['q']) ?>" placeholder="Search messages, routes, jobs…" aria-label="Search logs" maxlength="120"></label>
    <label class="dvx-sel"><span class="sr-only">Source</span><select name="source" aria-label="Source" onchange="this.form.submit()"><option value="">All sources</option><?php foreach ($d['sources'] as $k => $l): ?><option value="<?= h($k) ?>"<?= $d['source'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
    <label class="dvx-sel"><span class="sr-only">Level</span><select name="level" aria-label="Level" onchange="this.form.submit()"><?php foreach ($d['levels'] as $l): ?><option value="<?= h($l['v']) ?>"<?= $l['cls'] ? ' selected' : '' ?>><?= h($l['label']) ?></option><?php endforeach; ?></select></label>
    <label class="dvx-sel"><span class="sr-only">Time</span><select name="window" aria-label="Time window" onchange="this.form.submit()"><?php foreach ($d['windows'] as $k => $l): ?><option value="<?= (int) $k ?>"<?= (int) $d['window'] === (int) $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
    <button class="dvx-btn primary" type="submit"><i class="ic ic-sm i-filter" aria-hidden="true"></i>Apply</button>
    <?php if ($d['view'] !== ''): ?><input type="hidden" name="view" value="<?= h($d['view']) ?>"><?php endif; ?>
    <?php if ($d['q'] !== '' || $d['source'] !== '' || $d['level'] !== '' || $d['view'] !== '' || (int) $d['window'] !== 60): ?><a class="dvx-btn ghost" href="<?= h(sl_self()) ?>">Clear</a><?php endif; ?>
  </form>
  <?php if ($d['view'] !== ''): ?>
  <div class="dvx-chips"><a class="dvx-chip on" href="<?= h(sl_url(['view' => null, 'row' => null])) ?>" title="Remove this saved filter"><span class="dvx-src s-<?= h($d['source']) ?>" aria-hidden="true"></span><?= h($d['viewLabel']) ?> <b class="mono"><?= h(sl_num($d['total'])) ?></b><i class="ic ic-sm i-x" aria-hidden="true"></i></a></div>
  <?php else: ?>
  <div class="dvx-chips"><?php foreach ($d['sources'] as $k => $l): ?><a class="dvx-chip<?= $d['source'] === $k ? ' on' : '' ?>" href="<?= h(sl_url(['source' => $d['source'] === $k ? null : $k, 'row' => null])) ?>"><span class="dvx-src s-<?= h($k) ?>" aria-hidden="true"></span><?= h($l) ?><?php if ($d['bySource'][$k] !== null): ?> <b class="mono"><?= h(sl_num($d['bySource'][$k])) ?></b><?php endif; ?></a><?php endforeach; ?></div>
  <?php endif; ?>
  <div class="dvx-hist" role="img" aria-label="<?= (int) $d['total'] ?> events, <?= (int) $d['nWarn'] ?> warnings, <?= (int) $d['nErr'] ?> errors over the window"><?php foreach ($d['hist'] as $b): ?><span title="<?= h($b['title']) ?>"><i class="e" style="height:<?= (int) $b['e'] ?>%"></i><i class="w" style="height:<?= (int) $b['w'] ?>%"></i><i class="i" style="height:<?= (int) $b['i'] ?>%"></i></span><?php endforeach; ?></div>
  <div class="dvx-hist-x"><span><?= h($d['histFrom']) ?></span><span><?= h(sl_num($d['total'])) ?> events in the window · <span class="dvx-warn"><?= h(sl_num($d['nWarn'])) ?> warnings</span> · <span class="dvx-bad"><?= h(sl_num($d['nErr'])) ?> errors</span><?php if ($d['phpCapped']): ?> <small class="dvx-mut">(PHP log: last 3,000 lines)</small><?php endif; ?></span><span>now</span></div>
</section>

<?php if (($d['source'] === 'php' || $d['source'] === '') && !$d['phpReadable']): ?><div class="dvx-note" role="note"><i class="ic i-info" aria-hidden="true"></i><span><?= $d['phpPath'] === '' ? 'No PHP error log is configured: set <span class="mono">CDP_ERROR_LOG</span> in config/config.php to a file outside the web root.' : 'The PHP error log <span class="mono">' . h(basename($d['phpPath'])) . '</span> is not readable by PHP.' ?></span></div><?php endif; ?>
<?php if ($d['source'] === 'cron' && !$d['cronReady']): ?><div class="dvx-note" role="note"><i class="ic i-info" aria-hidden="true"></i><span>The cron ledger table <span class="mono">cdb_cron_runs</span> is missing; cron scripts record runs through helpers/cron_run.php once it exists.</span></div><?php endif; ?>

<div class="dvx-split">
<section class="dvx-card dvx-flush dvx-logs" aria-labelledby="dvx-ev-h">
  <div class="dvx-ch pad"><h2 class="dvx-ct" id="dvx-ev-h">Events</h2><span class="dvx-mut sm" data-dvx-tailinfo><?= count($d['rows']) >= $d['listCap'] ? 'Latest ' . (int) $d['listCap'] . ' of ' . h(sl_num($d['total'])) . ', newest first' : 'Newest first' ?></span></div>
  <div class="dvx-term tall" data-dvx-list data-dvx-since="<?= h($d['newest']) ?>" data-dvx-max="300" data-dvx-q="<?= h($d['q']) ?>" data-dvx-source="<?= h($d['source']) ?>" data-dvx-level="<?= h($d['level']) ?>" data-dvx-view="<?= h($d['view']) ?>" role="log" aria-label="Log events">
  <?php foreach ($d['rows'] as $r): ?>
    <a class="dvx-ln<?= $sel && $sel['id'] === $r['id'] ? ' on' : '' ?>" data-dvx-id="<?= h($r['id']) ?>" href="<?= h(sl_url(['row' => $r['id']])) ?>#dvx-detail" data-dvx-ev><span class="ts"><?= h($r['time']) ?></span><span class="lv <?= h($r['lc']) ?>"><?= h($r['level']) ?></span><span class="sv"><span class="dvx-src s-<?= h($r['source']) ?>" aria-hidden="true"></span><?= h($r['service']) ?></span><span class="msg"><?= h($r['message']) ?></span></a>
  <?php endforeach; ?>
  <?php if (!$d['rows']): ?><div class="dvx-ln dvx-ln-empty" data-dvx-empty><span class="msg">No event matches these filters in this window.</span></div><?php endif; ?>
  </div>
</section>
<?php // event data for the detail panel, once per row as plain JSON (no attribute escaping): id => event ?>
<script type="application/json" data-dvx-evdata><?= str_replace(['<', '>', '&'], ['\u003c', '\u003e', '\u0026'], (string) json_encode((object) array_column(array_map(fn($r) => ['k' => $r['id'], 'v' => json_decode(sl_dvxEvAttr($r), true)], $d['rows']), 'v', 'k'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?></script>
<aside class="dvx-card dvx-detail" id="dvx-detail" aria-labelledby="dvx-dt-h" data-dvx-detail>
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-dt-h">Log details<?php if ($sel && empty($_GET['row'])): ?> <small class="dvx-mut sm" data-dvx-selnote>· newest event</small><?php elseif ($d['selMissing']): ?> <small class="dvx-mut sm" data-dvx-selnote>· that event is outside this filter; showing the newest</small><?php endif; ?></h2><div class="dvx-seg" role="tablist" aria-label="View"><button type="button" class="on" role="tab" aria-selected="true" data-dvx-view="raw">Raw</button><button type="button" role="tab" aria-selected="false" data-dvx-view="json">JSON</button></div></div>
  <?php if ($sel): ?>
  <dl class="dvx-dl" data-dvx-meta><dt>Time</dt><dd class="mono" data-f="time"><?= h($sel['ts']) ?></dd><dt>Level</dt><dd data-f="level"><span class="lv <?= h($sel['lc']) ?>"><?= h($sel['level']) ?></span></dd><dt>Source</dt><dd data-f="source"><?= h($sel['srcLabel']) ?></dd><dt>Service</dt><dd data-f="service"><?= h($sel['service']) ?></dd></dl>
  <p class="dvx-p" data-f="message"><?= h($sel['message']) ?></p>
  <pre class="dvx-code wrap" data-dvx-pane="raw"><?= h($sel['raw']) ?></pre>
  <pre class="dvx-code" data-dvx-pane="json" hidden><?= h($sel['json']) ?></pre>
  <div class="row g8 wrap"><button class="dvx-btn sm" type="button" data-dvx-copy="raw"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy raw</button><button class="dvx-btn sm" type="button" data-dvx-copy="json"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy JSON</button><?php if ($d['issuesOk']): ?><a class="dvx-btn sm" data-dvx-ticket href="<?= h(sl_dvxTicketHref($sel)) ?>"><i class="ic ic-sm i-bug" aria-hidden="true"></i>Open ticket</a><?php endif; ?></div>
  <?php else: ?>
  <div class="dvx-empty">Pick an event to see its details.</div>
  <?php endif; ?>
</aside>
</div>

<section class="dvx-card" aria-labelledby="dvx-sv-h">
  <div class="dvx-ch"><h2 class="dvx-ct" id="dvx-sv-h">Saved views · 24 h</h2></div>
  <div class="dvx-saved"><?php foreach ($d['saved'] as $s): ?><a class="dvx-saved-i<?= $s['on'] ? ' on' : '' ?>" href="<?= h($s['href']) ?>"<?= $s['on'] ? ' aria-current="true"' : '' ?>><span class="grow"><?= h($s['t']) ?></span><b class="mono <?= $s['n'] ? 'dvx-warn' : 'dvx-mut' ?>"><?= h(sl_num($s['n'])) ?></b><i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endforeach; ?></div>
</section>
</div>
<?= sl_dvxSubnavEnd() ?>
