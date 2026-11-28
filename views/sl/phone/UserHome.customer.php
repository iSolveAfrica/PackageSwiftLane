<?php
/**
 * M-CProfile (view): the customer's profile at a glance (customers_profile_edit.php without ?edit, sl_userHomeData()).
 * Read-only; every change is made on the edit screen (?edit=1, views/sl/phone/ProfileEdit.customer.php) or the linked pages.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['nav'] = 'none';
$name = (string) ($d['name'] ?? '');
$edit = 'customers_profile_edit.php?edit=1';
$avatar = (string) ($d['avatar'] ?? '');
$complete = max(0, min(100, (int) ($d['complete'] ?? 0)));
$kv = function (string $k, string $v, bool $mono = false): string {
    return '<div><span>' . h($k) . '</span><b' . ($mono ? ' class="mono"' : '') . '>' . h($v !== '' ? $v : '—') . '</b></div>';
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<?= sl_phoneTop('My profile', '', ['back' => sl_route('MyAccount')], sl_phoneIcon('edit', 'Edit my profile', $edit)) ?>
<main class="mx-body">
<section class="b3-stu"><span class="b3-av xxl pc-av"<?= $avatar !== '' ? ' style="background-image:url(\'' . h($avatar) . '\')"' : '' ?>><?= $avatar === '' ? h($d['ini'] ?? '') : '' ?></span>
<b style="font-size:18px"><?= h($name) ?></b><small>Locker <?= h($d['locker'] ?? '—') ?> · member since <?= h($d['created'] ?? '—') ?></small>
<div class="b3-row2"><a class="b3-gh" href="<?= h($edit) ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit profile</a><a class="b3-gh" href="<?= h($edit) ?>#pe-photo"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Change photo</a></div></section>

<section class="mx-card pc-pad14"><div class="pc-ck-row"><span class="mx-li"><i class="ic i-shield" aria-hidden="true"></i></span><span class="mx-rm"><b>Profile <?= $complete ?>% complete</b><small><?= !empty($d['idVerified']) ? 'ID on file · needed for customs' : 'Add your ID photos for customs' ?></small></span><span class="sl-pill <?= !empty($d['idVerified']) ? 'delivered' : 'pending' ?>"><?= !empty($d['idVerified']) ? 'ID on file' : 'ID missing' ?></span></div><div class="mx-trk"><i class="<?= $complete >= 100 ? 'ok' : '' ?>" style="width:<?= $complete ?>%"></i></div></section>

<div class="mx-sec"><h2>Name and phone</h2><a href="<?= h($edit) ?>">Edit</a></div>
<section class="mx-card mx-kv"><?= $kv('Full name', $name) ?><?= $kv('Phone (WhatsApp)', (string) ($d['phone'] ?? ''), true) ?><?= $kv('Email', (string) ($d['email'] ?? '')) ?><?= $kv('Last sign-in', (string) ($d['lastLogin'] ?? '')) ?></section>

<div class="mx-sec"><h2>Address</h2><a href="<?= h($edit) ?>">Edit</a></div>
<?php if (!empty($d['address'])): ?>
<section class="mx-card mx-list"><div class="mx-row"><span class="mx-li"><i class="ic i-pin" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($d['address']) ?></b><small><?= h($d['addressCity'] ?? '') ?></small></span></div></section>
<?php else: ?>
<section class="mx-card"><?= sl_mxEmpty('pin', 'No address yet', 'Add one so we can deliver and pick up.', 'Add address', $edit) ?></section>
<?php endif; ?>

<div class="mx-sec"><h2>Your account</h2></div>
<section class="mx-card mx-list">
<a class="mx-row" href="<?= h(sl_route('MyParcels')) ?>"><span class="mx-li"><i class="ic i-box" aria-hidden="true"></i></span><span class="mx-rm"><b>Parcels</b><small><?= (int) ($d['total'] ?? 0) ?> in total · <?= (int) ($d['month'] ?? 0) ?> this month · last <?= h($d['lastShip'] ?? '—') ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row" href="<?= h(sl_route('RecipientsMine')) ?>"><span class="mx-li"><i class="ic i-users" aria-hidden="true"></i></span><span class="mx-rm"><b>Recipients</b><small><?= (int) ($d['recips'] ?? 0) ?> saved</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<a class="mx-row" href="<?= h(sl_route('CustomerNotifications')) ?>"><span class="mx-li"><i class="ic i-bell" aria-hidden="true"></i></span><span class="mx-rm"><b>Notifications</b><small><?= h($d['alerts'] ?? '') ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
<?php if (!empty($d['canPrealert'])): ?><a class="mx-row" href="<?= h(sl_route('PreAlerts')) ?>"><span class="mx-li"><i class="ic i-inbox" aria-hidden="true"></i></span><span class="mx-rm"><b>Pre-alerts</b><small><?= (int) ($d['preShip'] ?? 0) ?> open · <?= (int) ($d['prePkg'] ?? 0) ?> arrived</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
<?php if (!empty($d['finance'])): ?><a class="mx-row" href="<?= h(sl_route('MyBills')) ?>"><span class="mx-li"><i class="ic i-receipt" aria-hidden="true"></i></span><span class="mx-rm"><b>Bills</b><small><?= (int) ($d['dueN'] ?? 0) ?> to pay · <?= h($d['paid'] ?? '—') ?> paid of <?= h($d['billed'] ?? '—') ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a><?php endif; ?>
<a class="mx-row" href="<?= h($edit) ?>"><span class="mx-li"><i class="ic i-lock" aria-hidden="true"></i></span><span class="mx-rm"><b>Security and ID</b><small>Password, WhatsApp number and ID card</small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></a>
</section>
</main>
