<?php
/**
 * M-CRecipients: the customer's address book (recipients_list.php, sl_recipientsData()). Search and the country
 * filter use the web page's GET parameters (q, country, page); edit goes to recipients_edit.php, delete posts
 * ajax/sl/recipient_ajax.php op=delete exactly like the web row action (only when the recipient has no parcels).
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$rows = (array) ($d['rows'] ?? []);
$q = (string) ($d['q'] ?? '');
$cur = (int) ($d['country'] ?? 0);
$uid = (int) ($_SESSION['userid'] ?? 0);
// Country chips: the countries of this customer's own recipients, with counts (scoped to the signed-in customer).
$chips = sl_rows('SELECT co.id, co.name, COUNT(DISTINCT r.id) AS n FROM cdb_recipients r JOIN cdb_recipients_addresses a ON a.recipient_id = r.id JOIN cdb_countries co ON co.id = a.country WHERE r.sender_id = :u GROUP BY co.id, co.name ORDER BY n DESC, co.name LIMIT 12', [':u' => $uid]);
$total = (int) sl_scalar('SELECT COUNT(*) FROM cdb_recipients r WHERE r.sender_id = :u', [':u' => $uid]);
$pages = max(1, (int) ceil((int) ($d['count'] ?? 0) / max(1, (int) ($d['per'] ?? 25))));
$page = (int) ($d['page'] ?? 1);
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-cust.css')) ?>">
<?= sl_phoneTop('Recipients', 'People you ship to', ['back' => sl_route('MyAccount')], sl_phoneIcon('download', 'Export all recipients (CSV)', sl_url(['export' => 'csv', 'page' => null]))) ?>
<main class="mx-body">
<form class="b3-sr" method="get" action="<?= h(sl_route('RecipientsMine')) ?>" role="search"><?php if ($cur): ?><input type="hidden" name="country" value="<?= $cur ?>"><?php endif; ?><label class="b3-sp"><i class="ic i-search" aria-hidden="true"></i><input type="search" name="q" value="<?= h($q) ?>" aria-label="Search recipients" placeholder="Name, phone or address" enterkeyhint="search"></label></form>
<?php if ($chips): ?>
<nav class="b3-chs" aria-label="Country"><a class="pc-chip-a<?= $cur === 0 ? ' on' : '' ?>" href="<?= h(sl_url(['country' => null, 'page' => null])) ?>"<?= $cur === 0 ? ' aria-current="true"' : '' ?>>All <?= $total ?></a><?php foreach ($chips as $c): ?><a class="pc-chip-a<?= $cur === (int) $c->id ? ' on' : '' ?>" href="<?= h(sl_url(['country' => (int) $c->id, 'page' => null])) ?>"<?= $cur === (int) $c->id ? ' aria-current="true"' : '' ?>><?= h($c->name) ?> <?= (int) $c->n ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<?php foreach ($rows as $r): $place = $r['addr'] !== '—' ? $r['addr'] : $r['region']; ?>
<section class="b3-cd b3-rc"><div class="b3-rch"><span class="mx-ava"><?= h($r['ini']) ?></span><span class="mx-rm"><b><?= h($r['name']) ?></b><small><?= h($place) ?><?= $r['naddr'] > 1 ? ' · +' . ($r['naddr'] - 1) . ' more' : '' ?></small></span><span class="b3-st<?= $r['n'] ? ' ink' : '' ?>"><?= (int) $r['n'] ?> parcel<?= (int) $r['n'] === 1 ? '' : 's' ?></span></div>
<div class="b3-g2"><span><small>Phone</small><b><?= h($r['phone']) ?></b></span><span><small>E-mail</small><b><?= h($r['mail'] ?: '—') ?></b></span></div>
<div class="pc-acts"><?php if (!empty($d['canEdit'])): ?><a class="b3-x" href="recipients_edit.php?recipient=<?= (int) $r['id'] ?>"><i class="ic ic-sm i-edit" aria-hidden="true"></i>Edit</a><?php endif; ?><a class="b3-x" href="courier_add_client.php"><i class="ic ic-sm i-plus" aria-hidden="true"></i>Ship to <?= h(strtok($r['name'], ' ') ?: $r['name']) ?></a><?php if (!empty($d['canDelete']) && !$r['n']): ?><button type="button" class="b3-x bad" data-sl-action="ajax/sl/recipient_ajax.php" data-p-op="delete" data-p-id="<?= (int) $r['id'] ?>" data-sl-confirm="Delete <?= h($r['name']) ?>?" data-sl-reload aria-label="Delete <?= h($r['name']) ?>"><i class="ic ic-sm i-trash" aria-hidden="true"></i>Delete</button><?php endif; ?></div>
</section>
<?php endforeach; ?>
<?php if (!$rows): ?>
<section class="mx-card"><?= sl_mxEmpty('users', $q !== '' || $cur ? 'No recipient matches' : 'No recipients yet', $q !== '' || $cur ? 'Try another name, or clear the filter.' : 'Add the people you ship to, and pick them when you create a shipment.', !empty($d['canAdd']) ? 'Add recipient' : '', 'recipients_add.php') ?></section>
<?php endif; ?>
<?php if ($pages > 1): ?>
<div class="b3-row2"><?php if ($page > 1): ?><a class="b3-gh" href="<?= h(sl_url(['page' => $page - 1])) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Newer</a><?php else: ?><span></span><?php endif; ?><?php if ($page < $pages): ?><a class="b3-gh" href="<?= h(sl_url(['page' => $page + 1])) ?>">Older<i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?></div>
<?php endif; ?>
<p class="b3-cap">You can delete a recipient only when they have no parcels.</p>
</main>
<?php if (!empty($d['canAdd'])): ?><a class="mx-fab" href="recipients_add.php" aria-label="Add recipient"><i class="ic i-plus" aria-hidden="true"></i></a><?php endif; ?>
