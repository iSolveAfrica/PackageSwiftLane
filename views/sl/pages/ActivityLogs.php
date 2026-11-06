<?php
/**
 * ActivityLogs - generated from docs/handover/artifact-source/ActivityLogs.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   loop  feed                         as $f
 *   value feed, rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Activity Logs</h1><p class="sl-lede">Who did what, and when, across the whole workspace</p></div>
          <a class="sl-btn ghost" href="<?= sl_route('Documents') ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export Log</a>
        </div>
        <div class="split">
          <section class="a sl-card flush">
            <div class="row wrap g12" style="padding: 14px 16px"><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search activity" placeholder="Search action, user or tracking number"></div><div class="sl-sel" style="width: 150px"><span>All users</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div><div class="sl-seg boxed"><button class="on">All</button><button>Shipments</button><button>Payments</button><button>Security</button></div></div>
            <div class="sl-tw"><table class="sl-table"><thead><tr><th>Time</th><th>User</th><th>Action</th><th>Target</th><th>IP address</th></tr></thead><tbody>
              <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><tr><td class="mono sm"><?= sl_v($r, 't') ?></td><td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><?= sl_v($r, 'u') ?></div></td><td><span class="sl-badge <?= sl_v($r, 'b') ?>"><?= sl_v($r, 'a') ?></span></td><td class="mono sm"><?= sl_v($r, 'tg') ?></td><td class="mono sm mut"><?= sl_v($r, 'ip') ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <div class="sl-pager"><span>Showing 1–10 of 3,408 events</span><div class="sl-pg"><a class="on" href="<?= sl_route('ActivityLogs') ?>">1</a><a href="<?= sl_route('ActivityLogs') ?>">2</a><a href="<?= sl_route('ActivityLogs') ?>">3</a></div></div>
          </section>
          <aside class="b sl-card col g12"><h2 class="sl-ct">Live feed</h2>
            <div class="sl-feed">
              <?php foreach ((array) sl_get($d, 'feed', []) as $__i => $f): ?><div class="row g12 top"><span class="sl-dot <?= sl_v($f, 'c') ?>"></span><div class="grow"><div class="sm"><span class="fw5"><?= sl_v($f, 'u') ?></span> <?= sl_v($f, 'a') ?></div><div class="mut sm"><?= sl_v($f, 'w') ?></div></div></div><?php endforeach; ?>
            </div>
          </aside>
        </div>
      
