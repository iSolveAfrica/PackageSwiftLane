<?php
/**
 * M-Playbooks: saved replies ("Playbooks") and help articles ("Knowledge base") on a phone.
 * Same $d as the web page (sl_csPlaybookData). Read-only here: writing and editing stay on the web page.
 */
require_once CDP_ROOT . '/helpers/sl_mx.php';
$slPhone['scripts'][] = 'dataJs/sl/cs_common.js';
$slPhone['scripts'][] = 'dataJs/sl/phone/cs_desk.js';
$aud = ['public' => 'Shared with customers and AI', 'customer' => 'Signed-in customers', 'staff' => 'Staff only'];
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/pwa/phone-ph-desk.css')) ?>">
<?= sl_phoneTop('Playbooks & knowledge', '', ['back' => sl_route('CsInbox')], sl_phoneIcon('filter', 'Ticket rules', sl_route('CsRules'))) ?>
<main class="mx-body" data-mx-scope>
<div class="mx-srch"><label><i class="ic i-search" aria-hidden="true"></i><input type="search" aria-label="Search answers" placeholder="Search answers" data-pd-filter></label></div>
<div class="mx-seg" data-mx-seg role="group" aria-label="Show"><button type="button" class="on" aria-pressed="true" data-mx-panel="pb">Playbooks <?= count($d['canned']) ?></button><button type="button" aria-pressed="false" data-mx-panel="kb">Knowledge base <?= count($d['kb']) ?></button></div>
<div data-mx-panel-of="pb">
<?php if ($d['canned']): ?>
<section class="mx-card mx-list"><?php foreach ($d['canned'] as $c): ?><details class="pd-item" data-pd-item><summary class="mx-row"><span class="mx-li"><i class="ic i-book" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($c['title']) ?></b><small><?= $c['shortcut'] !== '' ? 'Type /' . h($c['shortcut']) . ' in a reply' : 'Saved reply' ?><?= $c['shared'] ? ' · shared' : ' · only you' ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></summary><p><?= h($c['body']) ?></p></details><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('book', 'No saved replies yet', 'Saved replies are written on the web page and show up here.') ?><?php endif; ?>
</div>
<div data-mx-panel-of="kb" hidden>
<?php if ($d['kb']): ?>
<section class="mx-card mx-list"><?php foreach ($d['kb'] as $a): ?><details class="pd-item" data-pd-item><summary class="mx-row"><span class="mx-li"><i class="ic i-file" aria-hidden="true"></i></span><span class="mx-rm"><b><?= h($a['title']) ?></b><small>Article · <?= $a['status'] === 'published' ? h($aud[$a['audience']] ?? ucfirst($a['audience'])) : h(ucfirst($a['status'])) ?></small></span><i class="ic ic-sm mx-ch i-right" aria-hidden="true"></i></summary><p><?= h(mb_substr(strip_tags($a['body']), 0, 600)) ?></p></details><?php endforeach; ?></section>
<?php else: ?><?= sl_mxEmpty('file', 'No help articles yet', 'Articles are written on the web page and show up here.') ?><?php endif; ?>
</div>
</main>
