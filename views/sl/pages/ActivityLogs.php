<?php
/**
 * ActivityLogs - from docs/handover/artifact-source/ActivityLogs.dc.html.
 * Data: views/sl/data/activity_logs.php (sl_activityLogsData).
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Activity Logs</h1><p class="sl-lede">Who did what, and when, across the whole workspace</p></div>
          <?php if ($d['canExport']): ?><a class="sl-btn ghost" href="<?= h(sl_url(['export' => 'csv', 'page' => null])) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export Log</a><?php endif; ?>
        </div>
        <?php if (!$d['ready']): ?><div class="sl-card"><div class="sl-empty"><span class="sl-tile"><i class="ic i-clock" aria-hidden="true"></i></span>The activity log table is not installed yet. Run the database upgrade (migrations/2026_11_06_swiftlane_ui_core.sql) to start recording actions.</div></div>
        <?php else: ?>
        <div class="split">
          <section class="a sl-card flush">
            <form method="get" class="row wrap g12" style="padding: 14px 16px"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search activity" placeholder="Search action, user or tracking number"></div><label class="sl-sel native" style="width: 170px"><select name="user_id" aria-label="User" onchange="this.form.submit()"><option value="">All users</option><?php foreach ($d['users'] as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $u['id'] === $d['user_id'] ? 'selected' : '' ?>><?= h($u['name']) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label><div class="sl-seg boxed"><?php foreach ($d['filters'] as $x): ?><a class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div></form>
            <div class="sl-tw"><table class="sl-table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Target</th><th>IP address</th></tr></thead><tbody>
              <?php foreach ($d['rows'] as $r): ?><tr title="<?= h($r['module'] . ' · ' . $r['summary']) ?>"><td class="mono sm"><?= h($r['t']) ?></td><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><?php if ($r['uid'] && $user->cdp_hasPermission('view_user_list')): ?><a href="<?= h(sl_route('StaffActivity', ['id' => $r['uid']])) ?>"><?= h($r['u']) ?></a><?php else: ?><?= h($r['u']) ?><?php endif; ?></div></td><td><span class="sl-badge <?= h($r['b']) ?>"><?= h($r['a']) ?></span></td><td class="mono sm"><?= h($r['tg']) ?></td><td class="mono sm mut"><?= h($r['ip']) ?></td></tr><?php endforeach; ?>
              <?php if (!$d['rows']): ?><tr><td colspan="5"><div class="sl-empty"><span class="sl-tile"><i class="ic i-clock" aria-hidden="true"></i></span>No events match this filter.</div></td></tr><?php endif; ?>
            </tbody></table></div>
            <?= sl_pager($d['page'], $d['per'], $d['count'], 'events') ?>
          </section>
          <aside class="b sl-card col g12"><h2 class="sl-ct">Live feed</h2>
            <div class="sl-feed">
              <?php foreach ($d['feed'] as $f): ?><div class="row g12 top"><span class="sl-dot <?= h($f['c']) ?>"></span><div class="grow"><div class="sm"><span class="fw5"><?= h($f['u']) ?></span> <?= h($f['a']) ?></div><div class="mut sm"><?= h($f['w']) ?></div></div></div><?php endforeach; ?>
              <?php if (!$d['feed']): ?><div class="mut sm">Nothing recorded yet.</div><?php endif; ?>
            </div>
          </aside>
        </div>
        <?php endif; ?>
