<?php
/**
 * MessageLogs - generated from docs/handover/artifact-source/MessageLogs.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 *   loop  rows                         as $r
 *   value rows
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Message Logs</h1><p class="sl-lede">Every SMS, WhatsApp and email sent to customers</p></div>
          <a class="sl-btn" href="<?= sl_route('ComposeMessage') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send Message</a>
        </div>
        <div class="grid cols3">
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Messages this month</span></div><div class="sl-mv">8,420 <i>/ 10,000 plan</i></div><div class="sl-ticks"><i style="width: 84%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Delivery rate</span></div><div class="sl-mv">98.1%</div><div class="sl-ticks v"><i style="width: 98%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Failed</span></div><div class="sl-mv">160 <i>(1.9%)</i></div><div class="sl-ticks r"><i style="width: 2%"></i></div></div>
        </div>
        <section class="sl-card flush">
          <div class="row wrap g12" style="padding: 14px 16px"><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input aria-label="Search messages" placeholder="Search recipient or tracking number"></div><div class="sl-seg boxed"><button class="on">All</button><button>SMS</button><button>WhatsApp</button><button>Email</button></div></div>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Sent</th><th>To</th><th>Channel</th><th>Template</th><th>Tracking</th><th>Status</th></tr></thead><tbody>
            <?php foreach ((array) sl_get($d, 'rows', []) as $__i => $r): ?><tr><td class="mono sm"><?= sl_v($r, 't') ?></td><td><div class="sl-cell"><span class="sl-av sm"><?= sl_v($r, 'ini') ?></span><span><span class="fw5"><?= sl_v($r, 'name') ?></span><span class="sub"><?= sl_v($r, 'to') ?></span></span></div></td><td><span class="sl-badge"><?= sl_v($r, 'ch') ?></span></td><td><?= sl_v($r, 'tpl') ?></td><td><a class="id" href="<?= sl_route('ShipmentDetail') ?>"><?= sl_v($r, 'trk') ?></a></td><td><span class="sl-pill <?= sl_v($r, 'cls') ?>"><?= sl_v($r, 'st') ?></span></td></tr><?php endforeach; ?>
          </tbody></table></div>
          <div class="sl-pager"><span>Showing 1–8 of 8,420 messages</span><div class="sl-pg"><a class="on" href="<?= sl_route('MessageLogs') ?>">1</a><a href="<?= sl_route('MessageLogs') ?>">2</a><a href="<?= sl_route('MessageLogs') ?>">3</a></div></div>
        </section>
      
