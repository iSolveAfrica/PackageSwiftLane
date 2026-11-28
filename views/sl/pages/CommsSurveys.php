<?php
/**
 * CommsSurveys - Communications > Surveys (comms_surveys.php).
 * Results: KPIs, trend, every question's answers, by rider and branch,
 * comments and low scores, export. Surveys: the forms (questions, when they
 * are sent, channels), edited in the builder (dataJs/sl/comms_surveys.js),
 * with King writing questions. Pop-up & invitation: the in-app pop-up
 * settings and the invitation text (shared editor, key "survey").
 * Data: views/sl/data/comms_surveys.php. Saves: ajax/comms/surveys_ajax.php.
 * Styles: pages/CommsSurveys.css (+ the question styles of helpers/comms_surveys.php).
 */
if (!isset($d)) { $d = []; }
$canEdit = !empty($d['canEdit']);
$ai = $d['ai'] ?? ['ok' => false, 'name' => 'King', 'message' => '', 'setup_url' => '', 'tones' => [], 'code' => ''];
$res = $d['results'] ?? null;
$k = $res['kpi'] ?? [];
$tab = $d['tab'] ?? 'results';
$chIcons = ['inapp' => 'i-bell', 'whatsapp' => 'i-wa', 'sms' => 'i-message', 'email' => 'i-mail'];
$fmt = function ($v, $suffix = '') { return $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . $suffix; };
$when = function ($s) { return $s ? date('M j, Y H:i', strtotime($s)) : ''; };
// Every link names the page: in the phone staff app (<base href="apps/staff/app.php/">) a bare "?..." would open Overview.
$here = $d['here'] ?? 'comms_surveys.php';
$q = ['range' => $d['preset'] ?? '30', 'form' => $d['formId'] ?: null];
if (($d['preset'] ?? '') === 'custom') { $q['from'] = $d['from']; $q['to'] = $d['to']; }
$exportUrl = function ($fmtx) use ($q, $d) { return ($d['exportBase'] ?? 'comms_surveys.php') . '?' . http_build_query(array_filter($q + ['export' => $fmtx], function ($v) { return $v !== null && $v !== ''; })); };
$cfg = ['ajax' => 'ajax/comms/surveys_ajax.php', 'tplAjax' => 'ajax/comms/automation_ajax.php', 'canEdit' => $canEdit, 'ai' => $ai,
    'forms' => array_values(array_map(function ($f) { unset($f['counts']); return $f; }, $d['forms'] ?? [])),
    'statuses' => $d['statuses'] ?? [], 'types' => $d['types'] ?? [], 'channels' => $d['channels'] ?? [], 'invite' => $d['inviteRules'] ?? [], 'edit' => (int) ($_GET['edit'] ?? 0),
    'here' => $here, 'automationsUrl' => $d['automationsUrl'] ?? 'comms_automations.php', 'popupOn' => !empty($d['settings']['popup'])];
$stars = function ($n) { $n = (int) $n; return '<span class="sv-stars" aria-label="' . $n . ' of 5">' . str_repeat('★', max(0, $n)) . '<i>' . str_repeat('★', max(0, 5 - $n)) . '</i></span>'; };
?>
<div class="ops sv-page" id="sv-root">
<?php $commsTab = 'commssurveys'; include __DIR__ . '/partials/comms_tabs.php'; ?>
<style><?= cdp_surveyQuestionsCss() ?></style>
<div class="sl-head">
  <div>
    <h1 class="sl-title">Surveys</h1>
    <p class="sl-lede">Ask customers how it went after a delivery or pickup: by WhatsApp, SMS, e-mail, and a friendly pop-up when they come back to the portal.</p>
  </div>
  <div class="row g8 wrap">
    <a class="sl-btn ghost" href="<?= h($d['automationsUrl'] ?? 'comms_automations.php') ?>"><i class="ic ic-sm i-bolt" aria-hidden="true"></i>Automations</a>
    <?php if ($canEdit): ?><button type="button" class="sl-btn" data-sv-new><i class="ic ic-sm i-plus" aria-hidden="true"></i>New survey</button><?php endif; ?>
  </div>
</div>

<?php if (empty($d['ready'])): ?>
<div class="sl-banner bad">The survey tables could not be created. The database user needs the CREATE privilege once.</div>
<?php else: ?>

<?php if (empty($d['engineOn'])): ?>
<div class="sl-banner"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>Automations are switched off, so no new survey is sent after a delivery. Answers to surveys already sent still arrive here. <a href="<?= h($d['automationsUrl'] ?? 'comms_automations.php') ?>">Open Automations</a></span></div>
<?php endif; ?>

<div class="sv-tabs" data-sl-tabs>
  <div class="sl-seg boxed" role="tablist" aria-label="Surveys">
    <?php foreach (['results' => 'Results', 'forms' => 'Surveys', 'settings' => 'Pop-up & invitation'] as $t => $tl): ?>
    <button type="button" role="tab" id="sv-tab-<?= $t ?>" data-sl-tab="<?= $t ?>" data-sl-tab-url="<?= h($here . '?' . http_build_query(array_filter($q + ['tab' => $t]))) ?>" class="<?= $tab === $t ? 'on' : '' ?>" aria-selected="<?= $tab === $t ? 'true' : 'false' ?>" aria-controls="sv-p-<?= $t ?>"><?= h($tl) ?><?php if ($t === 'forms'): ?> <span class="mut">(<?= count($d['forms']) ?>)</span><?php endif; ?></button>
    <?php endforeach; ?>
  </div>

  <!-- ================================================================ Results -->
  <section class="sv-panel" id="sv-p-results" role="tabpanel" aria-labelledby="sv-tab-results" data-sl-tabpanel="results" <?= $tab === 'results' ? '' : 'hidden' ?>>
    <?php if (!empty($d['badDates'])): ?><div class="sl-banner" role="status"><i class="ic ic-sm i-alert" aria-hidden="true"></i><span>The dates could not be read; showing the last 30 days.</span></div><?php endif; ?>
    <form method="get" action="<?= h($here) ?>" class="sl-card sv-filter" aria-label="Period and survey">
      <input type="hidden" name="tab" value="results">
      <div class="sl-seg boxed" role="group" aria-label="Period">
        <?php foreach ($d['presets'] as $pk => $pl): if ($pk === 'custom') continue; ?>
        <a class="<?= $d['preset'] === (string) $pk ? 'on' : '' ?>" href="<?= h($here . '?' . http_build_query(array_filter(['tab' => 'results', 'range' => $pk, 'form' => $d['formId'] ?: null]))) ?>"<?= $d['preset'] === (string) $pk ? ' aria-current="true"' : '' ?>><?= h(str_replace('Last ', '', $pl)) ?></a>
        <?php endforeach; ?>
      </div>
      <input type="hidden" name="range" value="custom">
      <label class="sv-date"><span class="sl-lab">From</span><input class="sl-in" type="date" name="from" value="<?= h($d['from']) ?>" max="<?= h(date('Y-m-d')) ?>"></label>
      <label class="sv-date"><span class="sl-lab">To</span><input class="sl-in" type="date" name="to" value="<?= h($d['to']) ?>" max="<?= h(date('Y-m-d')) ?>"></label>
      <label class="sv-date sv-fsel"><span class="sl-lab">Survey</span>
        <span class="sl-sel native"><select name="form" aria-label="Survey"><option value="0">Every survey</option><?php foreach ($d['forms'] as $f): ?><option value="<?= (int) $f['id'] ?>" <?= $f['id'] === $d['formId'] ? 'selected' : '' ?>><?= h($f['name']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>
      </label>
      <button type="submit" class="sl-btn ghost">Apply</button>
      <span class="grow"></span>
      <a class="sl-btn ghost" href="<?= h($exportUrl('csv')) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>CSV</a>
      <a class="sl-btn ghost" href="<?= h($exportUrl('xlsx')) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Excel</a>
    </form>
    <p class="mut sm sv-period"><?= h(date('M j, Y', strtotime($d['from']))) ?> – <?= h(date('M j, Y', strtotime($d['to']))) ?><?= $d['formId'] ? ' · ' . h(array_values(array_filter($d['forms'], function ($f) use ($d) { return $f['id'] === $d['formId']; }))[0]['name'] ?? '') : ' · every survey' ?></p>

    <div class="sv-kpis">
      <div class="sl-card sl-metric"><div class="sl-ml"><span>Sent</span></div><div class="big"><?= number_format((int) $k['sent']) ?></div><div class="sl-mf"><?= (int) $k['waiting'] ?> waiting for an answer</div></div>
      <div class="sl-card sl-metric"><div class="sl-ml"><span>Answered</span></div><div class="big"><?= number_format((int) $k['answered']) ?></div><div class="sl-mf"><?= $k['rate'] === null ? 'No survey sent yet' : $fmt($k['rate'], '%') . ' response rate' ?></div></div>
      <div class="sl-card sl-metric"><div class="sl-ml"><span>Average rating</span></div><div class="big"><?= $fmt($k['avg']) ?><?php if ($k['avg'] !== null): ?> <i class="sv-of">/ 5</i><?php endif; ?></div><div class="sl-mf"><?= $k['avg'] !== null ? $stars((int) round($k['avg'])) : 'No rating yet' ?></div></div>
      <div class="sl-card sl-metric"><div class="sl-ml"><span>Satisfied (CSAT)</span></div><div class="big"><?= $fmt($k['csat'], '%') ?></div><div class="sl-mf">Rated 4 or 5<?= (int) $k['unhappy'] ? ' · <b class="sv-bad">' . (int) $k['unhappy'] . ' unhappy</b>' : '' ?></div></div>
      <div class="sl-card sl-metric"><div class="sl-ml"><span>Net Promoter Score</span></div><div class="big"><?= $k['nps'] === null ? '—' : ($k['nps'] > 0 ? '+' : '') . (int) $k['nps'] ?></div><div class="sl-mf"><?= $k['nps_n'] ? (int) $k['nps_n'] . ' answered "recommend us"' : 'Add a recommend question' ?></div></div>
    </div>

    <?php $tr = $res['trend'] ?? []; $maxSent = max(1, max(array_merge([1], array_column($tr, 'sent'), array_column($tr, 'answered')))); ?>
    <section class="sl-card sv-trend" aria-labelledby="sv-trend-h">
      <header class="sv-ch"><h2 class="sl-ct" id="sv-trend-h">Trend</h2><span class="mut sm">Per <?= h($res['trend_unit'] ?? 'day') ?>: surveys sent, answered, and the average rating</span>
        <span class="sv-legend" aria-hidden="true"><i class="sv-lg-s"></i>Sent <i class="sv-lg-a"></i>Answered <i class="sv-lg-r"></i>Avg rating</span></header>
      <?php if (!array_sum(array_column($tr, 'sent')) && !array_sum(array_column($tr, 'answered'))): ?>
      <div class="sl-empty sv-empty">No survey was sent in this period.</div>
      <?php else: ?>
      <div class="sv-bars" role="img" aria-label="Surveys sent and answered per <?= h($res['trend_unit'] ?? 'day') ?>; the table below has the numbers">
        <?php foreach ($tr as $p): $hs = round(100 * $p['sent'] / $maxSent); $ha = round(100 * $p['answered'] / $maxSent); ?>
        <div class="sv-bar" title="<?= h($p['label'] . ': ' . $p['sent'] . ' sent, ' . $p['answered'] . ' answered' . ($p['avg'] !== null ? ', average ' . $p['avg'] . '/5' : '')) ?>">
          <span class="sv-b-s" style="height:<?= (int) $hs ?>%"></span><span class="sv-b-a" style="height:<?= (int) $ha ?>%"></span>
          <?php if ($p['avg'] !== null): ?><span class="sv-b-r" style="bottom:<?= (int) round(($p['avg'] - 1) / 4 * 100) ?>%"></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="sv-axis" aria-hidden="true"><span><?= h($tr[0]['label'] ?? '') ?></span><span><?= h($tr[(int) floor(count($tr) / 2)]['label'] ?? '') ?></span><span><?= h(end($tr)['label'] ?? '') ?></span></div>
      <details class="sv-tdata"><summary class="sm">Show as a table</summary>
        <div class="sv-tw"><table class="sv-table"><thead><tr><th scope="col"><?= h(ucfirst($res['trend_unit'] ?? 'day')) ?></th><th scope="col" class="n">Sent</th><th scope="col" class="n">Answered</th><th scope="col" class="n">Average</th></tr></thead><tbody>
        <?php foreach ($tr as $p): if (!$p['sent'] && !$p['answered']) continue; ?><tr><td><?= h($p['label']) ?></td><td class="n"><?= (int) $p['sent'] ?></td><td class="n"><?= (int) $p['answered'] ?></td><td class="n"><?= $fmt($p['avg']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      </details>
      <?php endif; ?>
    </section>

    <section class="sl-card sv-qcard" aria-labelledby="sv-q-h">
      <header class="sv-ch"><h2 class="sl-ct" id="sv-q-h">Answers per question</h2><span class="mut sm">Answered in this period</span></header>
      <?php if (empty($res['questions'])): ?>
      <div class="sl-empty sv-empty">No answers in this period yet.</div>
      <?php else: ?>
      <div class="sv-qs">
      <?php $lastForm = null; foreach ($res['questions'] as $qq): ?>
        <?php if (!$d['formId'] && $lastForm !== $qq['form']): $lastForm = $qq['form']; ?><h3 class="sv-qform"><?= h($qq['form']) ?></h3><?php endif; ?>
        <article class="sv-q">
          <div class="sv-qh"><b class="fw5"><?= h($qq['label']) ?></b><span class="sl-badge"><?= h(($d['types'][$qq['type']][0] ?? $qq['type'])) ?></span><span class="mut sm"><?= (int) $qq['n'] ?> answer<?= $qq['n'] === 1 ? '' : 's' ?><?= isset($qq['people']) ? ' from ' . (int) $qq['people'] . ' people' : '' ?></span></div>
          <?php if ($qq['type'] === 'rating' || $qq['type'] === 'nps'): $mx = max(1, max($qq['dist'])); ?>
            <div class="sv-qstat"><span class="sv-qbig"><?= $qq['type'] === 'nps' ? (($qq['nps'] ?? 0) > 0 ? '+' : '') . (int) ($qq['nps'] ?? 0) : $fmt($qq['avg']) ?></span><span class="mut sm"><?= $qq['type'] === 'nps' ? 'NPS · average ' . $fmt($qq['avg']) . ' / 10' : 'average out of 5' ?></span></div>
            <div class="sv-dist <?= $qq['type'] ?>">
              <?php foreach (($qq['type'] === 'rating' ? array_reverse($qq['dist'], true) : $qq['dist']) as $v => $c): $cls = $qq['type'] === 'nps' ? ((int) $v >= 9 ? 'g' : ((int) $v <= 6 ? 'r' : 'a')) : ((int) $v >= 4 ? 'g' : ((int) $v <= 2 ? 'r' : 'a')); ?>
              <div class="sv-dr"><span class="sv-dl"><?= $qq['type'] === 'rating' ? (int) $v . ' ★' : (int) $v ?></span><span class="sv-dt"><i class="<?= $cls ?>" style="width:<?= (int) round(100 * $c / $mx) ?>%;--h:<?= (int) round(100 * $c / $mx) ?>%"></i></span><span class="sv-dn"><?= (int) $c ?></span></div>
              <?php endforeach; ?>
            </div>
          <?php elseif ($qq['type'] === 'choice' || $qq['type'] === 'multi'): $tot = max(1, $qq['type'] === 'multi' ? ($qq['people'] ?? $qq['n']) : $qq['n']); ?>
            <div class="sv-dist choice">
              <?php foreach ($qq['dist'] as $o => $c): ?>
              <div class="sv-dr"><span class="sv-dl"><?= h($o) ?></span><span class="sv-dt"><i class="v" style="width:<?= (int) round(100 * $c / $tot) ?>%"></i></span><span class="sv-dn"><?= (int) $c ?> · <?= (int) round(100 * $c / $tot) ?>%</span></div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="mut sm sv-qtext"><?= (int) $qq['n'] ?> written answer<?= $qq['n'] === 1 ? '' : 's' ?>. Read them under Comments below.</p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </section>

    <div class="grid cols2">
      <?php foreach (['riders' => ['By rider', 'Surveys about deliveries a rider made', 'No delivery with a rider in this period.'], 'branches' => ['By branch', 'Where the last status was recorded', 'No survey in this period.']] as $rk => $rl): ?>
      <section class="sl-card flush sv-by" aria-labelledby="sv-<?= $rk ?>-h">
        <header class="sv-ch sv-chp"><h2 class="sl-ct" id="sv-<?= $rk ?>-h"><?= h($rl[0]) ?></h2><span class="mut sm"><?= h($rl[1]) ?></span></header>
        <?php if (empty($res[$rk])): ?><div class="sl-empty sv-empty"><?= h($rl[2]) ?></div><?php else: ?>
        <div class="sv-tw"><table class="sv-table">
          <thead><tr><th scope="col"><?= $rk === 'riders' ? 'Rider' : 'Branch' ?></th><th scope="col" class="n">Sent</th><th scope="col" class="n">Answered</th><th scope="col" class="n">Average</th><th scope="col" class="n">Unhappy</th></tr></thead>
          <tbody><?php foreach ($res[$rk] as $r): ?>
            <tr><th scope="row"><?= h($r['name']) ?></th><td class="n"><?= (int) $r['sent'] ?></td><td class="n"><?= (int) $r['answered'] ?></td>
              <td class="n"><?php if ($r['avg'] !== null): ?><span class="sv-avg <?= $r['avg'] >= 4 ? 'g' : ($r['avg'] < 3 ? 'r' : 'a') ?>"><?= $fmt($r['avg']) ?></span><?php else: ?><span class="mut">—</span><?php endif; ?></td>
              <td class="n"><?= (int) $r['low'] ? '<span class="sv-bad">' . (int) $r['low'] . '</span>' : '<span class="mut">0</span>' ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>

    <section class="sl-card flush sv-comments" aria-labelledby="sv-c-h" data-sl-tabs>
      <header class="sv-ch sv-chp"><h2 class="sl-ct" id="sv-c-h">What customers said</h2>
        <div class="sl-seg boxed sm" role="tablist" aria-label="Show">
          <button type="button" role="tab" class="on" aria-selected="true" data-sl-tab="c-all">Comments (<?= count($res['comments'] ?? []) ?>)</button>
          <button type="button" role="tab" aria-selected="false" data-sl-tab="c-low">Low scores (<?= count($res['low'] ?? []) ?>)</button>
        </div></header>
      <?php foreach (['c-all' => $res['comments'] ?? [], 'c-low' => $res['low'] ?? []] as $pk => $list): ?>
      <div data-sl-tabpanel="<?= $pk ?>" role="tabpanel" <?= $pk === 'c-all' ? '' : 'hidden' ?>>
        <?php if (!$list): ?><div class="sl-empty sv-empty"><?= $pk === 'c-all' ? 'No written comment in this period.' : 'No low score in this period. ' ?></div><?php else: ?>
        <ul class="sv-clist">
          <?php foreach ($list as $r): ?>
          <li class="sv-c">
            <div class="sv-cs"><?php if ($r['score'] !== null): ?><?= $stars($r['score']) ?><?php endif; ?><?php if ($r['nps'] !== null): ?><span class="sl-badge <?= $r['nps'] >= 9 ? 'ok' : ($r['nps'] <= 6 ? 'bad' : 'warn') ?>">Recommend <?= (int) $r['nps'] ?>/10</span><?php endif; ?></div>
            <?php if ($r['comment'] !== ''): ?><blockquote class="sv-cq"><?= nl2br(h($r['comment'])) ?></blockquote><?php else: ?><p class="mut sm sv-cq">No comment written.</p><?php endif; ?>
            <div class="sv-cm mut sm">
              <a href="<?= h($r['customer_link']) ?>"><?= h($r['customer']) ?></a><?= $r['locker'] !== '' ? ' · ' . h($r['locker']) : '' ?>
              <?php if ($r['ref'] !== ''): ?> · <?php if ($r['record_link'] !== ''): ?><a href="<?= h($r['record_link']) ?>"><?= h($r['ref']) ?></a><?php else: ?><?= h($r['ref']) ?><?php endif; ?><?php endif; ?>
              <?= $r['rider'] !== '' ? ' · Rider ' . h($r['rider']) : '' ?> · <?= h($when($r['responded_at'])) ?>
              <?php if (!empty($r['ticket_link'])): ?><a class="sl-btn ghost sm sv-cbtn" href="<?= h($r['ticket_link']) ?>"><i class="ic ic-sm i-alert" aria-hidden="true"></i>Follow-up ticket</a><?php endif; ?>
              <button type="button" class="sl-btn ghost sm sv-cbtn" data-sv-resp="<?= (int) $r['id'] ?>">All answers</button>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </section>
  </section>

  <!-- ================================================================ Surveys (forms) -->
  <section class="sv-panel" id="sv-p-forms" role="tabpanel" aria-labelledby="sv-tab-forms" data-sl-tabpanel="forms" <?= $tab === 'forms' ? '' : 'hidden' ?>>
    <p class="mut sv-intro">Each survey is sent after the statuses you choose, on the channels you choose, at most once per customer in the period you set. When several surveys match a status, the first one in this list is sent. <?= $canEdit ? '' : 'Only an administrator changes surveys.' ?></p>
    <?php if (!$d['forms']): ?>
    <div class="sl-card sl-empty sv-empty">No survey yet.<?php if ($canEdit): ?> <button type="button" class="sl-btn sm" data-sv-new>New survey</button><?php endif; ?></div>
    <?php endif; ?>
    <div class="sv-forms">
      <?php foreach ($d['forms'] as $f): $c = $f['counts']; $arch = !empty($f['archived']) && !$f['active']; ?>
      <article class="sl-card sv-form<?= $arch ? ' archived' : '' ?>" data-sv-form="<?= (int) $f['id'] ?>">
        <div class="sv-fh">
          <button type="button" class="sl-toggle <?= $f['active'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $f['active'] ? 'true' : 'false' ?>" aria-label="<?= h('Send ' . $f['name']) ?>"
            <?= $canEdit ? 'data-sl-save="ajax/comms/surveys_ajax.php" data-p-action="form_toggle" data-p-id="' . (int) $f['id'] . '"' : 'data-static' ?>></button>
          <div class="col grow sv-fn">
            <h3><?= h($f['name']) ?><?php if ($arch): ?> <span class="sl-badge" title="Switched off; its answers are kept. Switch it on to send it again.">Archived</span><?php endif; ?></h3>
            <span class="mut sm"><?= h($f['when']) ?> · <?= $f['frequency_days'] > 0 ? 'once per customer every ' . (int) $f['frequency_days'] . ' day' . ($f['frequency_days'] === 1 ? '' : 's') : 'after every matching status' ?></span>
          </div>
        </div>
        <div class="sv-fchs">
          <?php foreach ($d['channels'] as $ck => $cl): $on = in_array($ck, $f['channels'], true); ?>
          <span class="sv-chip <?= $on ? 'on' : '' ?>" title="<?= h($cl . ($on ? ': on' : ': off')) ?>"><i class="ic ic-sm <?= $chIcons[$ck] ?>" aria-hidden="true"></i><?= h($cl) ?><span class="ca-vh"><?= $on ? ' on' : ' off' ?></span></span>
          <?php endforeach; ?>
        </div>
        <ol class="sv-fqs">
          <?php foreach (array_slice($f['questions'], 0, 4) as $qq): ?><li><span class="sv-qt"><?= h($d['types'][$qq['type']][0] ?? $qq['type']) ?></span> <?= h($qq['label']) ?><?= $qq['required'] ? '' : ' <span class="mut">(optional)</span>' ?></li><?php endforeach; ?>
          <?php if (count($f['questions']) > 4): ?><li class="mut">+<?= count($f['questions']) - 4 ?> more</li><?php endif; ?>
        </ol>
        <div class="sv-fstats sm">
          <span><b><?= number_format($c['sent']) ?></b> sent</span><span><b><?= number_format($c['answered']) ?></b> answered</span>
          <span><b><?= $fmt($c['avg']) ?></b> average</span>
          <?php if ($c['last'] !== ''): ?><span class="mut">last answer <?= h(date('M j', strtotime($c['last']))) ?></span><?php endif; ?>
        </div>
        <div class="sv-fact">
          <button type="button" class="sl-btn ghost sm" data-sv-edit="<?= (int) $f['id'] ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i><?= $canEdit ? 'Edit' : 'View' ?></button>
          <a class="sl-btn ghost sm" href="survey.php?preview=<?= (int) $f['id'] ?>" target="_blank" rel="noopener"><i class="ic ic-sm i-eye" aria-hidden="true"></i>Preview<span class="ca-vh"> (opens in a new tab)</span></a>
          <a class="sl-btn ghost sm" href="<?= h($here . '?' . http_build_query(['tab' => 'results', 'form' => (int) $f['id'], 'range' => '90'])) ?>"><i class="ic ic-sm i-chart" aria-hidden="true"></i>Results</a>
          <?php if ($canEdit): ?>
          <button type="button" class="sl-btn ghost sm" data-sv-act="form_duplicate" data-sv-id="<?= (int) $f['id'] ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Duplicate</button>
          <?php if (!$arch): ?>
          <button type="button" class="sl-btn ghost sm sv-del" data-sv-act="form_delete" data-sv-id="<?= (int) $f['id'] ?>"
            data-sv-confirm="<?= h($c['sent'] ? 'This survey was already sent. It will be switched off and archived; its answers are kept. Continue?' : 'Delete this survey?') ?>"><i class="ic ic-sm i-trash" aria-hidden="true"></i><?= $c['sent'] ? 'Archive' : 'Delete' ?></button>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ================================================================ Pop-up & invitation -->
  <section class="sv-panel" id="sv-p-settings" role="tabpanel" aria-labelledby="sv-tab-settings" data-sl-tabpanel="settings" <?= $tab === 'settings' ? '' : 'hidden' ?>>
    <div class="grid cols2">
      <form class="sl-card sv-set" data-sl-form action="ajax/comms/surveys_ajax.php" method="post" aria-labelledby="sv-pop-h">
        <input type="hidden" name="action" value="settings_save">
        <h2 class="sl-ct" id="sv-pop-h">In-app pop-up</h2>
        <p class="mut sm">When a customer with an unanswered survey opens the portal, a short pop-up asks the questions. "Ask me later" waits, and the pop-up stops after the number of times below. Surveys with "In-app pop-up" switched on use it.</p>
        <?php $s = $d['settings']; ?>
        <div class="sv-sw">
          <button type="button" class="sl-toggle <?= $s['popup'] ? 'on' : '' ?>" role="switch" aria-checked="<?= $s['popup'] ? 'true' : 'false' ?>" aria-labelledby="sv-pop-l" <?= $canEdit ? '' : 'data-static' ?>><input type="hidden" name="popup" value="<?= $s['popup'] ? '1' : '0' ?>"></button>
          <span id="sv-pop-l">Show the pop-up to customers</span>
        </div>
        <div class="sv-g3">
          <label class="sl-field"><span class="sl-lab">Ask at most</span><span class="sv-unit"><input class="sl-in" type="number" name="max_prompts" min="1" max="10" value="<?= (int) $s['max_prompts'] ?>" <?= $canEdit ? '' : 'disabled' ?>><span>times</span></span></label>
          <label class="sl-field"><span class="sl-lab">Ask again after</span><span class="sv-unit"><input class="sl-in" type="number" name="gap_hours" min="1" max="720" value="<?= (int) $s['gap_hours'] ?>" <?= $canEdit ? '' : 'disabled' ?>><span>hours</span></span></label>
          <label class="sl-field"><span class="sl-lab">Keep a survey open</span><span class="sv-unit"><input class="sl-in" type="number" name="window_days" min="1" max="90" value="<?= (int) $s['window_days'] ?>" <?= $canEdit ? '' : 'disabled' ?>><span>days</span></span></label>
        </div>
        <?php if ($canEdit): ?><div><button type="submit" class="sl-btn">Save pop-up settings</button></div><?php else: ?><p class="mut sm">Only an administrator changes these.</p><?php endif; ?>
      </form>

      <section class="sl-card sv-set" aria-labelledby="sv-inv-h">
        <h2 class="sl-ct" id="sv-inv-h">Invitation message</h2>
        <p class="mut sm">The WhatsApp, SMS and e-mail message that brings the survey link (<code>{survey_url}</code>). One text for every survey; each survey chooses its own channels.</p>
        <ul class="sv-inv">
          <?php foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => 'E-mail'] as $ck => $cl): $on = !empty($d['inviteRules'][$ck]); ?>
          <li><i class="ic ic-sm <?= $chIcons[$ck] ?>" aria-hidden="true"></i><?= h($cl) ?> <span class="sl-badge <?= $on ? 'ok' : '' ?>"><?= $on ? 'Allowed' : 'Off on Automations' ?></span></li>
          <?php endforeach; ?>
        </ul>
        <div class="row g8 wrap">
          <button type="button" class="sl-btn" data-sv-invite><i class="ic ic-sm i-edit" aria-hidden="true"></i><?= $canEdit ? 'Edit invitation message' : 'View invitation message' ?></button>
          <a class="sl-btn ghost" href="<?= h(($d['automationsUrl'] ?? 'comms_automations.php')) ?>">Channel switches</a>
        </div>
        <p class="mut sm">Sent invitations appear in the <a href="<?= h($d['outboxUrl'] ?? 'comms_outbox.php') ?>">Outbox</a>. The survey link opens a page with the survey's questions and stays open for the days set on the left. Customers can also answer a WhatsApp invitation by replying with a number from 1 to 5 (on surveys with a rating question). A low score opens a follow-up ticket in Customer Care.</p>
      </section>
    </div>
  </section>
</div>

<!-- Builder (dataJs/sl/comms_surveys.js fills it) -->
<div class="sl-dialog sv-bdialog" id="sv-builder" hidden>
  <div class="sl-modal sv-bmodal" role="dialog" aria-modal="true" aria-labelledby="sv-b-title" data-sv-builder></div>
</div>
<!-- One survey's answers -->
<div class="sl-dialog" id="sv-resp" hidden>
  <div class="sl-modal sv-rmodal" role="dialog" aria-modal="true" aria-labelledby="sv-r-title">
    <div class="row g8"><h2 class="sl-ct grow" id="sv-r-title">Answers</h2><button type="button" class="sl-btn ghost sm" data-sl-close aria-label="Close">&times;</button></div>
    <div data-sv-resp-body><p class="mut">Loading…</p></div>
  </div>
</div>
<?php endif; ?>
</div>
<?php $ec = ['template' => true, 'tabs' => ['blocks', 'tpl', 'vars']]; include CDP_ROOT . '/views/sl/inc/email_canvas.php'; ?>
<script type="application/json" id="sv-cfg"><?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
