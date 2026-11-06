<?php
/**
 * Notifications - generated from docs/handover/artifact-source/Notifications.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  items                        as $n
 *   value items
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Notifications</h1><p class="sl-lede">3 unread · choose what reaches you and how</p></div>
          <button class="sl-btn ghost">Mark All Read</button>
        </div>
        <div class="split">
          <section class="a sl-card flush">
            <div class="row between wrap g12" style="padding: 14px 16px"><div class="sl-seg boxed"><button class="on">All</button><button>Unread</button><button>Mentions</button></div></div>
            <?php foreach ((array) sl_get($d, 'items', []) as $__i => $n): ?><div class="row g12 top" style="padding: 14px 16px; border-top: 1px solid var(--line)"><span class="sl-tile"><i class="ic <?= sl_v($n, 'ic') ?>" aria-hidden="true"></i></span><div class="grow"><div class="fw5"><?= sl_v($n, 't') ?></div><div class="mut sm"><?= sl_v($n, 'd') ?></div></div><div class="col g6 end"><span class="mut sm"><?= sl_v($n, 'w') ?></span><span class="sl-dot <?= sl_v($n, 'c') ?>"></span></div></div><?php endforeach; ?>
          </section>
          <aside class="b sl-card col g12"><h2 class="sl-ct">Delivery preferences</h2>
            <div class="row between"><span>New pre-alert</span><span class="sl-toggle on"></span></div>
            <div class="row between"><span>Package received at warehouse</span><span class="sl-toggle on"></span></div>
            <div class="row between"><span>Payment received</span><span class="sl-toggle on"></span></div>
            <div class="row between"><span>Overdue invoice</span><span class="sl-toggle"></span></div>
            <div class="row between"><span>Failed sign-in</span><span class="sl-toggle on"></span></div>
            <hr class="hr">
            <h2 class="sl-ct">Channels</h2>
            <div class="sl-seg boxed"><button class="on">Email</button><button class="on">Push</button><button>SMS</button></div>
          </aside>
        </div>
      
