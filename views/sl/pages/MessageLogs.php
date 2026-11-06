<?php
/**
 * MessageLogs - from docs/handover/artifact-source/MessageLogs.dc.html.
 * Data: views/sl/data/message_logs.php (sl_messageLogsData).
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Message Logs</h1><p class="sl-lede">Every SMS, WhatsApp and email sent to customers</p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= sl_route('MessageCosts') ?>"><i class="ic ic-sm i-dollar" aria-hidden="true"></i>Costs</a><?php if ($user->cdp_hasPermission('push_notifications')): ?><a class="sl-btn" href="<?= sl_route('ComposeMessage') ?>"><i class="ic ic-sm i-send" aria-hidden="true"></i>Send Message</a><?php endif; ?></div>
        </div>
        <?php if (!$d['ready']): ?><div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i>The message log table is missing. Run the database upgrade to start recording messages.</div><?php endif; ?>
        <div class="grid cols3">
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Messages this month</span></div><div class="sl-mv"><?= sl_num($d['month']) ?> <i>sent, failed and skipped</i></div><div class="sl-ticks"><i style="width: <?= sl_pct($d['sent'], $d['month']) ?>%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Delivery rate</span></div><div class="sl-mv"><?= h($d['rate']) ?>%</div><div class="sl-ticks v"><i style="width: <?= (int) $d['rate'] ?>%"></i></div></div>
          <div class="sl-card sl-metric"><div class="sl-ml"><span>Failed</span></div><div class="sl-mv"><?= sl_num($d['failed']) ?> <i>(<?= h($d['failPct'] ?? 0) ?>%)</i></div><div class="sl-ticks r"><i style="width: <?= (int) ($d['failPct'] ?? 0) ?>%"></i></div></div>
        </div>
        <section class="sl-card flush">
          <form method="get" class="row wrap g12" style="padding: 14px 16px"><input type="hidden" name="filter" value="<?= h($d['filter']) ?>"><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search messages" placeholder="Search recipient, subject or tracking number"></div><div class="sl-seg boxed"><?php foreach ($d['filters'] as $f): ?><a class="<?= $f['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $f['f'], 'page' => null])) ?>"><?= h($f['label']) ?></a><?php endforeach; ?></div></form>
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Sent</th><th>To</th><th>Channel</th><th>Template</th><th>Tracking</th><th>Status</th><th>By</th></tr></thead><tbody>
            <?php foreach ($d['rows'] as $r): ?><tr><td class="mono sm"><?= h($r['t']) ?></td><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><span class="fw5"><?= h($r['name']) ?></span><span class="sub"><?= h($r['to']) ?></span></span></div></td><td><span class="sl-badge"><?= h($r['ch']) ?></span></td><td><?= h($r['tpl']) ?></td><td><?php if ($r['link']): ?><a class="id" href="<?= h($r['link']) ?>"><?= h($r['trk']) ?></a><?php else: ?><span class="mono sm"><?= h($r['trk']) ?></span><?php endif; ?></td><td><span class="col"><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span><?php if ($r['detail']): ?><span class="mut" style="font-size:11.5px"><?= h(mb_substr($r['detail'], 0, 60)) ?></span><?php endif; ?></span></td><td class="sm"><?= h($r['by']) ?></td></tr><?php endforeach; ?>
            <?php if (!$d['rows']): ?><tr><td colspan="7"><div class="sl-empty"><span class="sl-tile"><i class="ic i-archive" aria-hidden="true"></i></span>No messages match this search.</div></td></tr><?php endif; ?>
          </tbody></table></div>
          <?= sl_pager($d['page'], $d['per'], $d['count'], 'messages') ?>
        </section>
