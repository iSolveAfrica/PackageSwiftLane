<?php
/**
 * SecurityCenter - from docs/handover/artifact-source/SecurityCenter.dc.html.
 * Data: views/sl/data/security.php (sl_securityData). Writes and the per-person detail:
 * ajax/sl/security_ajax.php. Behaviour: dataJs/sl/security_center.js (staff search / filters /
 * show more, the person pop-up, deep links #sc-d-<check> and #user-<id>).
 *
 * Every check, KPI tile, policy row and coverage item leads somewhere: a pop-up that explains the
 * check (what it looks at, the value now, why it matters, how to fix it, where to change it), a
 * section of this page, or the page where the setting lives. Policy states are read-only copies of
 * the real settings.
 */
if (!isset($d)) { $d = []; }
$can = !empty($d['canAct']);
$EP = 'ajax/sl/security_ajax.php';

/** One table cell of a check pop-up. $c: string, or [t, sub, b, href, code, num, mono, pill, act, user]. */
$scCell = function ($c, string $label) use ($EP): string {
    if (!is_array($c)) $c = ['t' => (string) $c];
    $cls = trim((!empty($c['num']) ? 'num ' : '') . (!empty($c['mono']) ? 'mono' : ''));
    if (isset($c['act'])) {
        $a = $c['act'];
        $in = '<button type="button" class="sl-btn ghost sm" data-sl-action="' . h($EP) . '" data-p-op="' . h($a['op']) . '" data-p-id="' . (int) $a['id'] . '"' . (!empty($a['confirm']) ? ' data-sl-confirm="' . h($a['confirm']) . '"' : '') . '>' . h($a['label']) . '</button>';
    } elseif (isset($c['user'])) {
        $in = '<button type="button" class="sl-btn ghost sm" data-sc-user="' . (int) $c['user'] . '"><i class="ic ic-sm i-monitor" aria-hidden="true"></i>' . h($c['t'] ?? 'Details') . '</button>';
    } elseif (isset($c['pill'])) {
        $in = sl_pill($c['t'] ?? '', $c['pill']);
    } else {
        $in = h($c['t'] ?? '');
        if (!empty($c['code'])) $in = '<code class="sc-code">' . $in . '</code>';
        if (!empty($c['b'])) $in = '<b class="fw5">' . $in . '</b>';
        if (!empty($c['href']) && sl_secNeeds((string) $c['href']) === '') $in = '<a class="sc-a" href="' . h($c['href']) . '">' . $in . '</a>';
        if (!empty($c['sub'])) $in .= '<span class="sub">' . h($c['sub']) . '</span>';
    }
    return '<td data-l="' . h($label) . '"' . ($cls !== '' ? ' class="' . $cls . '"' : '') . '><div class="sc-cv">' . $in . '</div></td>';
};

/** The pop-up of one check (or of an explanation without a status). */
$scDialog = function (array $c, bool $status = true) use ($scCell): string {
    $id = 'sc-d-' . $c['k'];
    $state = $c['d'] === 'e' ? ['Problem', 'pending', 'bad'] : ($c['d'] === 'w' ? ['Look at this', 'onway', ''] : ['Passing', 'delivered', 'ok']);
    ob_start(); ?>
<div class="sl-dialog" id="<?= h($id) ?>" hidden><div class="sl-modal sc-modal<?= !empty($c['wide']) ? ' wide' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="<?= h($id) ?>-t">
  <div class="sc-mh"><span class="sc-mi <?= h($c['d']) ?>"><i class="ic <?= h($c['icon'] ?? 'i-shield') ?>" aria-hidden="true"></i></span><div class="col grow g4"><h2 class="sc-mt" id="<?= h($id) ?>-t"><?= h($c['t']) ?></h2><?php if ($status): ?><span class="row g6 wrap"><?= sl_pill($state[0], $state[1]) ?></span><?php endif; ?></div><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <div class="sc-now"><span class="up">Right now</span><b class="fw5"><?= h($c['s']) ?></b></div>
  <div class="sc-ex">
    <div><span class="sc-exi"><i class="ic ic-sm i-search" aria-hidden="true"></i></span><div><h3 class="up">What it looks at</h3><p><?= h($c['what']) ?></p></div></div>
    <div><span class="sc-exi"><i class="ic ic-sm i-info" aria-hidden="true"></i></span><div><h3 class="up">Why it matters</h3><p><?= h($c['why']) ?></p></div></div>
    <div class="fix <?= $status ? h($state[2] ?: 'warn') : 'ok' ?>"><span class="sc-exi"><i class="ic ic-sm <?= $status && $c['d'] !== '' ? 'i-wand' : 'i-check' ?>" aria-hidden="true"></i></span><div><h3 class="up">How to fix</h3><p><?= h($c['fix']) ?></p><?php if (!empty($c['fixCode'])): ?><pre><?= h($c['fixCode']) ?></pre><?php endif; ?></div></div>
  </div>
  <?php if (!empty($c['facts'])): ?><dl class="sc-facts"><?php foreach ($c['facts'] as $f): ?><div><dt><?= h($f[0]) ?></dt><dd><?= h($f[1]) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
  <?php foreach ((array) ($c['tables'] ?? []) as $tb): ?>
  <div class="col g6"><?php if (!empty($tb['title'])): ?><h3 class="up"><?= h($tb['title']) ?></h3><?php endif; ?>
    <?php if ($tb['rows']): ?><div class="sl-tw sc-tw"><table class="sl-table sc-table"><thead><tr><?php foreach ($tb['head'] as $hd): ?><th scope="col"><?= h($hd) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($tb['rows'] as $row): ?><tr><?php foreach (array_values($row) as $i => $cv): ?><?= $scCell($cv, (string) ($tb['head'][$i] ?? '')) ?><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
    <?php else: ?><span class="mut sm"><?= h($tb['empty'] ?? 'Nothing to show.') ?></span><?php endif; ?></div>
  <?php endforeach; ?>
  <?php if (!empty($c['note'])): ?><p class="sc-note"><i class="ic ic-sm i-info" aria-hidden="true"></i><span><?= h($c['note']) ?></span></p><?php endif; ?>
  <?php if (!empty($c['links'])): ?><div class="row g8 wrap sc-links"><?php foreach ($c['links'] as $l): ?><?php if (!empty($l[3])): ?><span class="sc-nolink" title="<?= h('Your role cannot open this page: ' . $l[3]) ?>"><i class="ic ic-sm i-lock" aria-hidden="true"></i><?= h($l[0]) ?> <em>(<?= h($l[3]) ?>)</em></span><?php else: ?><a class="sl-btn ghost sm" href="<?= h($l[1]) ?>"><i class="ic ic-sm <?= h($l[2] ?? 'i-right') ?>" aria-hidden="true"></i><?= h($l[0]) ?></a><?php endif; ?><?php endforeach; ?></div><?php endif; ?>
</div></div>
<?php return (string) ob_get_clean();
};
$sum = (array) sl_get($d, 'peopleSum', []);
$people = (array) sl_get($d, 'people', []);
$pageSize = (int) sl_get($d, 'pageSize', 25);
?>
<div class="sl-head"><div><h1 class="sl-title" style="font-size:22px">Security and reliability</h1><p class="sl-lede">The checks behind the system: sign-in, permissions, tokens, backups, versions, headers, cron and queues. Click any check to see what it means and how to fix it.</p></div><div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('SystemLogs') ?>">System logs</a><?php if (sl_secNeeds(sl_route('StaffPermissions')) === ''): ?><a class="sl-btn" href="<?= sl_route('StaffPermissions') ?>">Permissions</a><?php endif; ?></div></div>
<?php if (sl_get($d, 'flash')): ?><div class="fr-ok" role="status"><i class="ic ic-sm i-check" aria-hidden="true"></i><?= sl_v($d, 'flash') ?></div><?php endif; ?>

<div class="hb-ks sc-kpis"><?php foreach ((array) sl_get($d, 'kpis', []) as $k): ?>
<?php $tag = !empty($k['open']) ? 'button' : 'a'; ?>
<<?= $tag ?> class="hb-k sc-k" <?= $tag === 'button' ? 'type="button" data-sl-open="' . h($k['open']) . '" aria-haspopup="dialog"' : 'href="' . h($k['href']) . '"' ?><?= !empty($k['filter']) ? ' data-sc-filter="' . h($k['filter']) . '"' : '' ?> aria-label="<?= h($k['aria'] . ': ' . $k['v']) ?>">
  <span class="hb-kh"><span class="hb-ki"><i class="ic ic-sm <?= sl_v($k, 'icon') ?>" aria-hidden="true"></i></span><span class="hb-kl"><?= sl_v($k, 'l') ?></span></span>
  <b class="hb-kv"><?= sl_v($k, 'v') ?></b>
  <span class="hb-kf"><span class="hb-ks2"><?= sl_v($k, 's') ?></span><?php if (sl_get($k, 'sp')): ?><span class="hb-sp" aria-hidden="true"><?php foreach ((array) sl_get($k, 'sp', []) as $hgt): ?><i title="<?= h($hgt['t']) ?>" style="<?= h($hgt['s']) ?>"></i><?php endforeach; ?></span><?php endif; ?></span>
</<?= $tag ?>><?php endforeach; ?></div>

<section class="sl-card sc-checks" id="sc-checks" data-sl-scope aria-labelledby="sc-checks-t">
  <div class="sl-ch"><div><h2 class="sl-ct" id="sc-checks-t">Security checks</h2><span class="mut sm"><?= sl_v($d, 'passN') ?> passing · <?= sl_v($d, 'warnN') ?> to look at · checked at <?= sl_v($d, 'ran') ?></span></div>
    <div class="row g8 wrap"><div class="sl-seg boxed" data-sl-filter role="group" aria-label="Show checks"><button type="button" class="on" data-f="all">All <?= count((array) sl_get($d, 'chk', [])) ?></button><button type="button" data-f="warn">To look at <?= sl_v($d, 'warnN') ?></button><button type="button" data-f="ok">Passing <?= sl_v($d, 'passN') ?></button></div><a class="sl-btn ghost sm" href="<?= h(sl_self()) ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Run checks now</a></div></div>
  <div class="sc-grid"><?php foreach ((array) sl_get($d, 'chk', []) as $c): ?>
    <?php $f = $c['d'] === '' ? 'ok' : 'warn'; ?>
    <?php if ($c['jump'] !== ''): ?><a class="hb-i sc-chk" href="<?= h($c['jump']) ?>" data-sl-row data-f="<?= $f ?>"><?php else: ?><button type="button" class="hb-i sc-chk" data-sl-open="sc-d-<?= h($c['k']) ?>" aria-haspopup="dialog" data-sl-row data-f="<?= $f ?>"><?php endif; ?>
      <span class="hb-dot <?= h($c['d']) ?>" style="margin-top:6px"><span class="sc-sr"><?= $c['d'] === 'e' ? 'Problem: ' : ($c['d'] === 'w' ? 'Look at this: ' : 'Passing: ') ?></span></span><span class="col grow"><b class="fw5 sm"><?= h($c['t']) ?></b><span class="mut sc-cs"><?= h($c['s']) ?></span></span><i class="ic ic-sm <?= $c['jump'] !== '' ? 'i-down' : 'i-right' ?> sc-go" aria-hidden="true"></i>
    <?= $c['jump'] !== '' ? '</a>' : '</button>' ?>
  <?php endforeach; ?></div>
  <p class="mut sm" data-sl-empty hidden>No checks in this group.</p>
</section>

<section class="sl-card sc-people" id="sc-devices" data-page-size="<?= $pageSize ?>" aria-labelledby="sc-devices-t">
  <div class="sc-ph">
    <div class="col g4"><h2 class="sl-ct" id="sc-devices-t">Devices and log-ins</h2><span class="mut sm">Every active staff account. Open a person to see each remembered device, their passkeys and their last sign-ins<?= $can ? ', and to forget devices or ask for a new password' : '' ?>.</span></div>
    <div class="sc-stats">
      <div><b><?= (int) ($sum['staff'] ?? 0) ?></b><span>Staff</span></div>
      <div><b><?= (int) ($sum['devices'] ?? 0) ?></b><span>Remembered devices</span></div>
      <div><b><?= (int) ($sum['today'] ?? 0) ?></b><span>Signed in today</span></div>
      <div class="<?= !empty($sum['reset']) ? 'warn' : '' ?>"><b><?= (int) ($sum['reset'] ?? 0) ?></b><span>Must reset</span></div>
    </div>
  </div>
  <div class="sc-tools">
    <label class="sl-search sc-q"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" data-sc-q placeholder="Search name or username" aria-label="Search staff by name or username" autocomplete="off"></label>
    <div class="sl-seg boxed sc-fs" role="group" aria-label="Filter staff">
      <button type="button" class="on" data-sc-f="all" aria-pressed="true">All <em><?= (int) ($sum['staff'] ?? 0) ?></em></button>
      <button type="button" data-sc-f="dev" aria-pressed="false">With remembered devices <em><?= (int) ($sum['withDev'] ?? 0) ?></em></button>
      <button type="button" data-sc-f="reset" aria-pressed="false">Must reset password <em><?= (int) ($sum['reset'] ?? 0) ?></em></button>
      <button type="button" data-sc-f="idle" aria-pressed="false">Not signed in for 30 days <em><?= (int) ($sum['idle'] ?? 0) ?></em></button>
    </div>
  </div>
  <div class="sc-list">
    <div class="sc-lh" aria-hidden="true"><span>Person</span><span>Remembered</span><span>Last sign-in</span><span class="tr">Failed 30 d</span><span>Status</span><span></span></div>
    <ul class="sc-ul" aria-label="Staff accounts"><?php foreach ($people as $i => $p): ?>
      <li data-sc-li data-tags="<?= h($p['tags']) ?>" data-q="<?= h($p['q']) ?>">
        <button type="button" class="sc-row" data-sc-user="<?= (int) $p['id'] ?>" aria-haspopup="dialog" aria-label="<?= h($p['name'] . ', ' . $p['role'] . ': ' . $p['st'] . '. Open devices and sign-ins') ?>">
          <span class="sc-c-who"><span class="sl-av<?= $p['super'] ? ' dark' : '' ?>" aria-hidden="true"><?= h($p['ini']) ?></span><span class="col sc-min"><b class="fw5 sc-ell"><?= h($p['name']) ?></b><span class="mut sm sc-ell"><?= h($p['role']) ?> · <?= h($p['user']) ?></span></span></span>
          <span class="sc-c-dev"><i class="ic ic-sm <?= $p['devs'] ? 'i-monitor' : 'i-minus' ?>" aria-hidden="true"></i><span><b class="fw5"><?= (int) $p['devs'] ?></b> <span class="mut"><?= $p['devs'] === 1 ? 'device' : 'devices' ?></span><?php if ($p['pk']): ?><span class="mut"> · <?= (int) $p['pk'] ?> passkey<?= $p['pk'] === 1 ? '' : 's' ?></span><?php endif; ?></span></span>
          <span class="sc-c-last col sc-min"><span title="<?= h($p['atFull']) ?>"><?= h($p['at']) ?></span><span class="mut sm sc-ell"><?= h(implode(' · ', array_filter([$p['dev'], $p['ip']])) ?: '—') ?></span></span>
          <span class="sc-c-fail<?= $p['f30'] >= 5 ? ' bad' : '' ?>"><span class="sc-ml">Failed 30 d </span><?= (int) $p['f30'] ?></span>
          <span class="sc-c-st"><?= sl_pill($p['st'], $p['pc']) ?></span>
          <i class="ic ic-sm i-right sc-go" aria-hidden="true"></i>
        </button>
      </li>
    <?php endforeach; ?></ul>
  </div>
  <?php if (!$people): ?><div class="sl-empty">No active staff accounts.</div><?php endif; ?>
  <div class="sl-empty" data-sc-empty hidden><i class="ic i-search" aria-hidden="true"></i><span>No staff match this search or filter.</span></div>
  <div class="sc-foot"><span class="mut sm" data-sc-shown aria-live="polite"></span><button type="button" class="sl-btn ghost sm" data-sc-more hidden>Show all</button></div>
</section>

<div class="sc-two">
  <section class="sl-card col g10 sc-recent" aria-labelledby="sc-recent-t">
    <div class="sl-ch" style="margin:0"><h2 class="sl-ct" id="sc-recent-t">Latest sign-ins</h2><?php if (sl_get($d, 'logsHref')): ?><a class="sl-btn ghost sm" href="<?= h($d['logsHref']) ?>">All sign-ins</a><?php endif; ?></div>
    <div class="sc-rl-list"><?php foreach ((array) sl_get($d, 'recent', []) as $r): ?>
      <?php $inner = '<span class="sc-ri ' . ($r['ok'] ? 'ok' : 'bad') . '"><i class="ic ic-sm ' . ($r['ok'] ? 'i-check' : 'i-x') . '" aria-hidden="true"></i></span><span class="col grow sc-min"><span class="row g6 wrap"><b class="fw5">' . h($r['t']) . '</b>' . sl_pill($r['ok'] ? 'Signed in' : 'Failed', $r['ok'] ? 'delivered' : 'pending') . '</span><span class="mut sm sc-ell">' . h(implode(' · ', array_filter([$r['dev'], $r['ip'], $r['why']])) ?: '—') . '</span></span><span class="mut sm nw" title="' . h($r['at']) . '">' . h($r['ago']) . '</span>'; ?>
      <?php if ($r['user']): ?><button type="button" class="sc-rl" data-sc-user="<?= (int) $r['user'] ?>" aria-haspopup="dialog"><?= $inner ?></button>
      <?php elseif ($r['cust']): ?><a class="sc-rl" href="<?= h(sl_route('CustomerProfile', ['id' => $r['cust']])) ?>"><?= $inner ?></a>
      <?php else: ?><button type="button" class="sc-rl" data-sl-open="sc-d-fails" aria-haspopup="dialog"><?= $inner ?></button><?php endif; ?>
    <?php endforeach; ?><?php if (!sl_get($d, 'recent')): ?><span class="mut sm">No sign-ins logged.</span><?php endif; ?></div>
  </section>
  <div class="col g16">
    <section class="sl-card col g10" aria-labelledby="sc-burst-t">
      <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct" id="sc-burst-t">Failed sign-ins by address</h2><span class="mut sm">Last 7 days</span></div><button type="button" class="sl-btn ghost sm" data-sl-open="sc-d-fails" aria-haspopup="dialog">Latest failures</button></div>
      <div class="sc-rl-list"><?php foreach ((array) sl_get($d, 'burst', []) as $bu): ?>
        <<?= $bu['href'] ? 'a class="sc-rl" href="' . h($bu['href']) . '"' : 'div class="sc-rl na"' ?>><span class="sc-ri bad"><i class="ic ic-sm i-globe" aria-hidden="true"></i></span><span class="col grow sc-min"><span class="mono fw5"><?= h($bu['ip']) ?></span><span class="mut sm sc-ell">Tried: <?= h($bu['who']) ?></span></span><span class="col sc-rn"><b class="fw5"><?= (int) $bu['n'] ?></b><span class="mut sm nw"><?= h($bu['last']) ?></span></span></<?= $bu['href'] ? 'a' : 'div' ?>>
      <?php endforeach; ?><?php if (!sl_get($d, 'burst')): ?><span class="mut sm">No failed sign-ins in the last 7 days.</span><?php endif; ?></div>
    </section>
    <section class="sl-card col g10" aria-labelledby="sc-policy-t">
      <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct" id="sc-policy-t">Sign-in policy</h2><span class="mut sm">Read from the live settings; open a line to see or change it</span></div></div>
      <div class="sc-rl-list"><?php foreach ((array) sl_get($d, 'policy', []) as $p): ?>
        <?php $inner = '<span class="col grow sc-min"><span>' . h($p['t']) . '</span><span class="mut sm">' . h($p['d']) . '</span></span><span class="sl-toggle ' . ($p['on'] ? 'on' : '') . '" aria-hidden="true"></span><span class="sc-sr">' . ($p['on'] ? 'On' : 'Off') . '</span><i class="ic ic-sm i-right sc-go" aria-hidden="true"></i>'; ?>
        <?php if (!empty($p['open'])): ?><button type="button" class="sc-rl" data-sl-open="<?= h($p['open']) ?>" aria-haspopup="dialog"><?= $inner ?></button><?php elseif (!empty($p['needs'])): ?><div class="sc-rl na" title="<?= h('Your role cannot open this setting: ' . $p['needs']) ?>"><?= str_replace('<i class="ic ic-sm i-right sc-go" aria-hidden="true"></i>', '<i class="ic ic-sm i-lock sc-go" aria-hidden="true"></i>', $inner) ?></div><?php else: ?><a class="sc-rl" href="<?= h($p['href']) ?>"><?= $inner ?></a><?php endif; ?>
      <?php endforeach; ?></div>
    </section>
  </div>
</div>

<section class="col g10" id="sc-cov" data-sl-scope aria-labelledby="sc-cov-t"><div class="row between wrap g8"><h2 class="sl-ct" id="sc-cov-t">Security features and where they live</h2><div class="sl-seg boxed" data-sl-filter role="group" aria-label="Show features"><?php foreach ((array) sl_get($d, 'cf', []) as $i => $f): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-f="<?= h($f['f']) ?>"><?= h($f['label']) ?> <?= (int) $f['n'] ?></button><?php endforeach; ?></div></div>
<div class="hb-cov"><?php foreach ((array) sl_get($d, 'cov', []) as $c): ?>
  <?php $inner = '<span class="n">' . (int) $c['n'] . '</span><span class="col grow sc-min"><b class="fw5 sm">' . h($c['t']) . '</b><span class="mut sc-cs">' . h($c['where'] . ($c['kind'] === 'locked' ? ' · ' . $c['needs'] : '')) . '</span>'
      . ($c['st'] !== '' ? '<span class="sc-cst">' . sl_pill($c['st'], $c['pc']) . '</span>' : '') . '</span>'
      . ($c['kind'] === 'locked' ? '<i class="ic ic-sm i-lock sc-go" aria-hidden="true" title="Your role cannot open this page"></i>' : '<i class="ic ic-sm ' . ($c['kind'] === 'here' ? 'i-down' : 'i-right') . ' sc-go" aria-hidden="true"></i>'); ?>
  <?php if ($c['kind'] === 'locked'): ?><div class="hb-i sc-cov na" data-sl-row data-f="<?= h($c['group']) ?>"><?= $inner ?></div>
  <?php elseif ($c['kind'] === 'page' || $c['kind'] === 'here'): ?><a class="hb-i sc-cov" href="<?= h($c['target']) ?>" data-sl-row data-f="<?= h($c['group']) ?>"><?= $inner ?></a>
  <?php else: ?><button type="button" class="hb-i sc-cov<?= $c['kind'] === 'none' ? ' nb' : '' ?>" data-sl-open="<?= h($c['target']) ?>" aria-haspopup="dialog" data-sl-row data-f="<?= h($c['group']) ?>"><?= $inner ?></button><?php endif; ?>
<?php endforeach; ?></div>
<p class="mut sm" data-sl-empty hidden>Nothing in this group.</p></section>

<?php
foreach ((array) sl_get($d, 'chk', []) as $c) {
    if ($c['jump'] === '') echo $scDialog($c);
}
if (sl_get($d, 'ratelimit')) echo $scDialog($d['ratelimit'], false);
foreach ((array) sl_get($d, 'info', []) as $c) {
    echo $scDialog($c + ['d' => '', 'facts' => [], 'tables' => [], 'links' => [], 'note' => '', 'wide' => false], false);
}
?>
<div class="sl-dialog" id="sc-user" hidden data-ep="<?= h($EP) ?>"><div class="sl-modal sc-modal wide sc-user" role="dialog" aria-modal="true" aria-labelledby="sc-user-t">
  <div class="sc-mh"><span class="sl-av lg" data-u-ini aria-hidden="true">?</span><div class="col grow g4 sc-min"><h2 class="sc-mt" id="sc-user-t" data-u-name>Staff member</h2><span class="mut sm" data-u-sub></span><span class="row g6 wrap" data-u-pills></span></div><button type="button" class="sl-ib" data-sl-close aria-label="Close"><i class="ic i-x" aria-hidden="true"></i></button></div>
  <div class="col g16" data-u-body aria-live="polite"><div class="sc-load"><i></i><i></i><i></i></div></div>
</div></div>
