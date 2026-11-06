<?php
/**
 * PreAlerts - from docs/handover/artifact-source/PreAlerts.dc.html.
 * Data: views/sl/data/prealerts.php. The "New pre-alert" card is the add form (prealert_add.php opens it).
 */
if (!isset($d)) { $d = []; }
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Pre-Alerts</h1><p class="sl-lede"><?= $d['isClient'] ? 'Tell us what is coming before it reaches the warehouse' : 'Customers tell us what is coming before it reaches the warehouse' ?> · <?= sl_num($d['openN']) ?> open · <?= sl_num($d['weekN']) ?> this week</p></div>
          <?php if ($d['canAdd']): ?><button type="button" class="sl-btn" data-sl-open="pa-new"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Pre-Alert</button><?php endif; ?>
        </div>
        <div class="split">
          <div class="a sl-card flush">
            <form method="get" class="row wrap g12" style="padding: 14px 16px">
              <input type="hidden" name="filter" value="<?= h($d['filter']) ?>">
              <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search pre-alerts" placeholder="Search tracking or customer"></div>
              <div class="sl-seg boxed"><?php foreach ($d['filters'] as $x): ?><a class="<?= $x['f'] === $d['filter'] ? 'on' : '' ?>" href="<?= h(sl_url(['filter' => $x['f'], 'page' => null])) ?>"><?= h($x['label']) ?></a><?php endforeach; ?></div>
            </form>
            <div class="sl-tw"><table class="sl-table">
              <thead><tr><th>Tracking</th><?php if (!$d['isClient']): ?><th>Customer</th><?php endif; ?><th>Supplier</th><th>Expected</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($d['rows'] as $r): ?>
                  <tr><td class="mono sm fw5"><?= h($r['trk']) ?><span class="sub" style="font-family:var(--font)"><?= h($r['carrier']) ?> · <?= h($r['sent']) ?></span></td>
                    <?php if (!$d['isClient']): ?><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><span class="fw5"><?= h($r['name']) ?></span><span class="sub mono"><?= h($r['locker']) ?></span></span></div></td><?php endif; ?>
                    <td><?= h($r['sup']) ?><span class="sub"><?= h($r['what']) ?> · <?= h($r['price']) ?></span></td><td><?= h($r['eta']) ?></td><td><span class="sl-pill <?= h($r['cls']) ?>"><?= h($r['st']) ?></span></td>
                    <td><span class="row g4"><?php if ($r['open'] && $d['canPackage']): ?><a class="sl-btn ghost sm" href="<?= sl_route('NewPackage', ['prealert_id' => $r['id']]) ?>">Create package</a><?php endif; ?><?php if ($r['open'] && $d['canShipment']): ?><a class="sl-btn ghost sm" href="courier_add.php?prealert_id=<?= (int) $r['id'] ?>">Air shipment</a><?php endif; ?><?php if ($d['canInvoice'] && $r['invoice']): ?><a class="sl-ib sm" target="_blank" href="<?= h($r['invoice']) ?>" aria-label="Open invoice"><i class="ic i-file" aria-hidden="true"></i></a><?php endif; ?></span></td></tr>
                <?php endforeach; ?>
                <?php if (!$d['rows']): ?><tr><td colspan="6"><div class="sl-empty"><span class="sl-tile"><i class="ic i-inbox" aria-hidden="true"></i></span>No pre-alerts here.</div></td></tr><?php endif; ?>
              </tbody>
            </table></div>
            <?= sl_pager($d['page'], $d['per'], $d['count'], 'pre-alerts') ?>
          </div>
          <aside class="b col g16">
            <?php if ($d['canAdd']): ?>
            <form class="sl-card col g12" id="pa-new" data-sl-form data-sl-reload action="ajax/sl/prealert_ajax.php" method="post" enctype="multipart/form-data"><h2 class="sl-ct">New pre-alert</h2>
              <input type="hidden" name="op" value="create">
              <?php if (!$d['isClient']): ?><label class="sl-field"><span class="sl-lab">Customer</span><span class="sl-sel native"><select name="customer_id" required><option value="">Select customer</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h(trim($c->fname . ' ' . $c->lname)) ?><?= $c->locker ? ' · ' . h($c->locker) : '' ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
              <label class="sl-field"><span class="sl-lab">Carrier tracking number</span><input name="tracking" class="sl-in mono" placeholder="1Z999AA1…" required></label>
              <div class="grid cols2" style="gap: 12px"><label class="sl-field"><span class="sl-lab">Supplier</span><input name="provider" class="sl-in" placeholder="Amazon" required></label><label class="sl-field"><span class="sl-lab">Carrier</span><span class="sl-sel native"><select name="courier" required><?php foreach ($d['couriers'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name_com) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label></div>
              <div class="grid cols2" style="gap: 12px"><label class="sl-field"><span class="sl-lab">Purchase price</span><input name="price" type="number" step="0.01" min="0.01" class="sl-in mono" required></label><label class="sl-field"><span class="sl-lab">Expected</span><input name="date" type="date" class="sl-in" value="<?= h(date('Y-m-d', strtotime('+5 days'))) ?>" required></label></div>
              <label class="sl-field"><span class="sl-lab">What is in it</span><input name="description" class="sl-in" placeholder="2 pairs of shoes" required></label>
              <label class="sl-drop" style="padding: 16px;cursor:pointer"><i class="ic i-upload" aria-hidden="true"></i><span class="sm" id="pa-file-note">Attach invoice<?= $d['isClient'] ? '' : ' (optional)' ?></span><input type="file" name="file_invoice" accept=".pdf,image/*" hidden onchange="document.getElementById('pa-file-note').textContent=this.files.length?this.files[0].name:'Attach invoice'"></label>
              <button type="submit" class="sl-btn block">Submit pre-alert</button>
            </form>
            <?php endif; ?>
            <div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>When a parcel is registered with the same supplier tracking number, the matching pre-alert is linked automatically.</span></div>
          </aside>
        </div>
<?php if ($d['openForm']): ?><script>document.addEventListener('DOMContentLoaded',function(){var f=document.querySelector('#pa-new input[name=tracking]');if(f){f.scrollIntoView({block:'center'});f.focus();}});</script><?php endif; ?>
