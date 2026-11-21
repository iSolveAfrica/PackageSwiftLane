<?php
/**
 * The AI pop-up on every page (shell): ask about what is on screen, anything else in the system, or
 * where to find something. Opened by any [data-sl-ai] button (top bar, page buttons) or Ctrl/Cmd+J.
 * Sends the question with the page title, address and the text shown on screen to
 * ajax/ai_assistant/chat_ajax.php (scope=page); the assistant also has search_site (helpers/ai_site_index.php).
 * Behaviour: dataJs/sl/ai_page.js (loads the Markdown renderer only when opened). Styles: assets/swiftlane/ai-page.css.
 */
$aiBoard = (string) ($slPage['board'] ?? '');
$aiTitle = (string) ($slPage['title'] ?? ($core->site_name ?? 'this page'));
$aiClient = !empty($slIsClient);
$aiAsk = [
    'Main' => ['What needs my attention today?', 'Summarise the numbers on this page', 'Which shipments are late and why?', 'How does this week compare with last week?'],
    'AirShipments' => ['Which shipments on this page are unpaid?', 'What is held or stuck in customs?', 'Who are the biggest senders this month?', 'Summarise the shipments shown'],
    'SeaPackages' => ['Which packages are waiting longest?', 'What is unpaid?', 'Which packages are not in a consolidation yet?'],
    'LiveTracking' => ['Which shipments are running late?', 'What is arriving this week?', 'Explain the selected shipment'],
    'ShipmentTracking' => ['Explain this shipment\'s journey', 'Is anything wrong with it?', 'When will it arrive?'],
    'ShipmentDetail' => ['Explain this shipment in plain words', 'Is it paid, and what is still owed?', 'What happened most recently?'],
    'PackageDetail' => ['Explain this package in plain words', 'Is it paid?', 'Which consolidation is it in?'],
    'Consolidations' => ['Which consolidations are departing soon?', 'Which are nearly full?', 'Summarise loads in motion'],
    'Warehouse' => ['What is held and why?', 'What has waited more than 30 days?', 'How full are the bays?'],
    'HandOut' => ['Who has the most parcels waiting?', 'Which ready parcels are unpaid?', 'Who should I remind first?'],
    'Pickups' => ['Which pickups have no rider?', 'Which pickups are overdue?', 'How many pickups today?'],
    'Customers' => ['Who are our top customers this month?', 'Which customers have gone quiet?', 'Who is waiting for approval?'],
    'CsInbox' => ['Which tickets are waiting longest?', 'What are customers asking about most?', 'Summarise open tickets'],
    'Campaigns' => ['Which campaign performed best?', 'Why did messages fail?', 'What did we spend this month?'],
    'Documents' => ['What was printed today?', 'Who printed the most labels this week?'],
    'ControlPanels' => ['What needs attention first?', 'Explain the workspace health'],
    'MessageLogs' => ['Why are messages failing?', 'How many messages went out today?'],
    'LockerSearch' => ['How do I find a customer by phone?', 'What can I search for here?'],
    'AccountsReceivable' => ['Who owes the most?', 'What is overdue more than 30 days?'],
    'FinancialOverview' => ['Summarise revenue this month', 'What is unpaid?'],
    'Reports' => ['Which report shows shipments by customer?', 'Summarise this month'],
];
$aiClientAsk = ['Where are my parcels?', 'Do I owe anything?', 'When will my next parcel arrive?', 'How do I book a pickup?'];
$aiSuggest = $aiClient ? $aiClientAsk : ($aiAsk[$aiBoard] ?? ['Explain this page', 'What do the numbers here mean?', 'What needs attention here?', 'Which other pages relate to this?']);
?>
<div class="sl-ai" id="sl-ai" hidden data-board="<?= h($aiBoard) ?>" data-title="<?= h($aiTitle) ?>" data-full="<?= h(sl_route('StaffAI')) ?>">
  <div class="sl-ai-panel" role="dialog" aria-modal="false" aria-labelledby="sl-ai-h">
    <header class="sl-ai-head">
      <span class="sl-ai-ic"><i class="ic i-ai" aria-hidden="true"></i></span>
      <div class="col grow" style="min-width:0"><b id="sl-ai-h">Ask <?= h(cdp_kingName()) ?></b><small>About <span data-ai-page><?= h($aiTitle) ?></span> and everything you can see in <?= h($core->site_name ?? 'Swift Lane') ?></small></div>
      <button type="button" class="sl-ib sm" data-ai-new aria-label="New conversation" title="New conversation"><i class="ic ic-sm i-plus" aria-hidden="true"></i></button>
      <?php if (!$aiClient): ?><a class="sl-ib sm" data-ai-full href="<?= h(sl_route('StaffAI')) ?>" aria-label="Open the full chat with <?= h(cdp_kingName()) ?>" title="Open the full chat with <?= h(cdp_kingName()) ?>"><i class="ic ic-sm i-expand" aria-hidden="true"></i></a><?php endif; ?>
      <button type="button" class="sl-ib sm" data-ai-close aria-label="Close"><i class="ic ic-sm i-x" aria-hidden="true"></i></button>
    </header>
    <div class="sl-ai-log" data-ai-log aria-live="polite">
      <div class="sl-ai-hello" data-ai-hello>
        <b>Hi<?= !empty($slFirst) ? ' ' . h($slFirst) : '' ?>, what would you like to know?</b>
        <span>I can read this page, look up live records you have access to, and point you to the right page.</span>
        <div class="sl-ai-chips"><?php foreach ($aiSuggest as $q): ?><button type="button" data-ai-q><?= h($q) ?></button><?php endforeach; ?></div>
      </div>
    </div>
    <form class="sl-ai-form" data-ai-form autocomplete="off">
      <textarea rows="1" data-ai-in maxlength="4000" placeholder="Ask about this page or anything else…" aria-label="Your question"></textarea>
      <button type="submit" class="sl-ai-send" aria-label="Send"><i class="ic ic-sm i-send" aria-hidden="true"></i></button>
    </form>
    <small class="sl-ai-foot">Reads data only, never changes it. Check important figures on the page it links to.</small>
  </div>
</div>
<script>window.SL_AI = <?= json_encode(['libs' => [cdp_asset('assets/vendor/libs/marked/marked.min.js'), cdp_asset('assets/vendor/libs/dompurify/purify.min.js'), cdp_asset('dataJs/sl/ai_response.js')], 'css' => cdp_asset('assets/swiftlane/ai-response.css')], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
