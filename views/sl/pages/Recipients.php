<?php
/**
 * Recipients - from docs/handover/artifact-source/Recipients.dc.html.
 * Data: views/sl/data/recipients.php. Writes: ajax/sl/recipient_ajax.php.
 * Staff (recipients_admin_list.php) see all recipients in scope; customers (recipients_list.php) their own.
 */
if (!isset($d)) { $d = []; }
$e = $d['edit'];
$self = sl_self();
?>
        <div class="sl-head">
          <div><h1 class="sl-title">Recipients</h1><p class="sl-lede"><?= $d['client'] ? 'People and businesses you send parcels to' : 'People and businesses that receive parcels' ?> · <?= sl_num($d['count']) ?></p></div>
          <div class="row g8 wrap"><a class="sl-btn ghost" href="<?= h(sl_url(['export' => 'csv', 'page' => null], $self)) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Export</a><?php if (!$d['client'] && $user->cdp_hasPermission('add_client')): ?><a class="sl-btn" href="<?= sl_route('AddCustomer', ['type' => 'recipient']) ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Add Recipient</a><?php endif; ?></div>
        </div>
        <div class="split">
          <div class="a sl-card flush">
            <form method="get" class="row wrap g12" style="padding: 14px 16px">
              <div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input name="q" value="<?= h($d['q']) ?>" aria-label="Search recipients" placeholder="Search name, phone or address"></div>
              <label class="sl-sel native" style="width: 170px"><select name="country" aria-label="Country" onchange="this.form.submit()"><option value="">All countries</option><?php foreach ($d['countries'] as $c): ?><option value="<?= (int) $c->id ?>" <?= (int) $c->id === $d['country'] ? 'selected' : '' ?>><?= h($c->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
              <?php if ($d['owner']): ?><a class="sl-badge" href="<?= h(sl_url(['owner' => null])) ?>">Customer #<?= (int) $d['owner'] ?> ×</a><?php endif; ?>
            </form>
            <div class="sl-tw"><table class="sl-table"><thead><tr><th>Recipient</th><th>Phone</th><th>Country</th><th>Address</th><?php if (!$d['client']): ?><th>Added by</th><?php endif; ?><th class="num">Parcels</th><th></th></tr></thead><tbody>
              <?php foreach ($d['rows'] as $r): ?><tr><td><div class="sl-cell"><span class="sl-av sm"><?= h($r['ini']) ?></span><span><span class="fw5"><?= h($r['name']) ?></span><span class="sub"><?= h($r['mail']) ?></span></span></div></td><td><?= h($r['phone']) ?></td><td><?= h($r['region']) ?></td><td><?= h($r['addr']) ?><?= $r['naddr'] > 1 ? ' <span class="mut sm">+' . ($r['naddr'] - 1) . '</span>' : '' ?></td><?php if (!$d['client']): ?><td><?php if ($r['ownerIsCustomer']): ?><a href="<?= sl_route('CustomerProfile', ['id' => $r['ownerId']]) ?>"><?= h($r['by']) ?></a><?php else: ?><?= h($r['by']) ?><?php endif; ?></td><?php endif; ?><td class="num"><?= (int) $r['n'] ?></td><td><span class="row g4"><?php if ($d['canEdit']): ?><a class="sl-ib sm" aria-label="Edit recipient" href="<?= h(sl_url(['edit' => $r['id']])) ?>#rc-form"><i class="ic i-edit" aria-hidden="true"></i></a><?php endif; ?><?php if ($d['canDelete'] && !$r['n']): ?><button class="sl-ib sm" aria-label="Delete recipient" data-sl-action="ajax/sl/recipient_ajax.php" data-p-op="delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete <?= h($r['name']) ?>?" data-sl-reload><i class="ic i-trash" aria-hidden="true"></i></button><?php endif; ?></span></td></tr><?php endforeach; ?>
              <?php if (!$d['rows']): ?><tr><td colspan="7"><div class="sl-empty"><span class="sl-tile"><i class="ic i-pin" aria-hidden="true"></i></span>No recipients match.</div></td></tr><?php endif; ?>
            </tbody></table></div>
            <?= sl_pager($d['page'], $d['per'], $d['count'], 'recipients') ?>
          </div>
          <?php if ($e ? $d['canEdit'] : $d['canAdd']): ?>
          <form class="b sl-card col g12" id="rc-form" data-sl-form action="ajax/sl/recipient_ajax.php" method="post">
            <input type="hidden" name="op" value="save"><input type="hidden" name="id" value="<?= (int) ($e['id'] ?? 0) ?>">
            <div class="row between"><h2 class="sl-ct"><?= $e ? 'Edit recipient' : 'Add recipient' ?></h2><?php if ($e): ?><a class="sm fw5" href="<?= h(sl_url(['edit' => null])) ?>">New</a><?php endif; ?></div>
            <?php if (!$d['client']): ?><label class="sl-field"><span class="sl-lab">Belongs to customer</span><span class="sl-sel native"><select name="sender_id"><option value="<?= (int) $_SESSION['userid'] ?>">Me (staff)</option><?php foreach ($d['customers'] as $c): ?><option value="<?= (int) $c->id ?>" <?= (int) $c->id === (int) ($e['owner'] ?? $d['owner']) ? 'selected' : '' ?>><?= h(trim($c->fname . ' ' . $c->lname) . ($c->locker ? ' · ' . $c->locker : '')) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
            <div class="sl-field"><label class="sl-lab" for="rc-n">Full name</label><input id="rc-n" name="name" class="sl-in" required value="<?= h(trim(($e['fname'] ?? '') . ' ' . ($e['lname'] ?? ''))) ?>" placeholder="Ama Boateng"></div>
            <div class="sl-field"><label class="sl-lab" for="rc-p">Phone</label><input id="rc-p" name="phone" class="sl-in" required value="<?= h($e['phone'] ?? '') ?>" placeholder="+233 24 000 0000"></div>
            <div class="sl-field"><label class="sl-lab" for="rc-e">E-mail (optional)</label><input id="rc-e" name="email" type="email" class="sl-in" value="<?= h($e['email'] ?? '') ?>"></div>
            <?php if ($e): foreach ($e['addresses'] as $a): ?><div class="row g8 sm"><i class="ic ic-sm i-pin" aria-hidden="true"></i><span class="grow"><?= h($a->address) ?></span><button type="button" class="sl-ib sm" aria-label="Remove address" data-sl-action="ajax/sl/recipient_ajax.php" data-p-op="address_delete" data-p-id="<?= (int) $e['id'] ?>" data-p-address_id="<?= (int) $a->id_addresses ?>" data-sl-confirm="Remove this address?" data-sl-reload><i class="ic ic-sm i-trash" aria-hidden="true"></i></button></div><?php endforeach; endif; ?>
            <div class="sl-field"><span class="sl-lab"><?= $e ? 'Add another address' : 'Country' ?></span><span class="sl-sel native"><select name="country"><option value="">Choose a country</option><?php foreach ($d['countries'] as $c): ?><option value="<?= (int) $c->id ?>"><?= h($c->name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></div>
            <div class="sl-field"><label class="sl-lab" for="rc-a">Street address</label><textarea id="rc-a" name="address" class="sl-in" placeholder="House number, street, landmark" <?= $e ? '' : 'required' ?>></textarea></div>
            <div class="sl-field"><label class="sl-lab" for="rc-z">Postal / GPS code</label><input id="rc-z" name="postal" class="sl-in"></div>
            <button class="sl-btn block" type="submit">Save recipient</button>
          </form>
          <?php endif; ?>
        </div>
