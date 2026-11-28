<?php
/**
 * King, the customer care chat, for signed-in customers (role rank 0).
 *
 *   $kcMode = 'dock'   every customer page (views/sl/inc/shell_end.php): a bubble at the bottom right that
 *                      opens a chat sheet (full screen on phones);
 *   $kcMode = 'inline' the customer dashboard (views/sl/pages/ClientDashboard.php): a panel at the top of
 *                      the page on desktop, a large card on phones that opens the full-screen chat.
 *
 * When opened, King greets the customer by first name with a briefing built on the server from their own
 * records (helpers/support_king.php cdp_csKingBriefing): parcels ready for pickup, what changed since the last
 * visit, balance due, open requests ... as cards, then quick replies. Questions go to King (the AI when a
 * provider is set up, account answers otherwise); "Talk to a person" hands the chat to the Customer Care desk.
 * Endpoint: ajax/support/customer_ajax.php (brief, start via=king, send, quick, human, poll, rate).
 * Behaviour: dataJs/sl/king_chat.js. Styles: assets/swiftlane/king_chat.css.
 *
 * King is presented warmly and personally (the AI icon, "Hi, I'm King", the customer's first name); the footer
 * under the chat still says plainly that King is automated and a person is one tap away.
 */
require_once CDP_ROOT . '/helpers/support.php';
$kcMode = ($kcMode ?? 'dock') === 'inline' ? 'inline' : 'dock';
$kcKing = function_exists('cdp_kingName') ? cdp_kingName() : 'King';
$kcFirst = trim((string) ($userData->fname ?? ''));
$kcFirst = $kcFirst !== '' ? mb_convert_case(mb_strtolower($kcFirst), MB_CASE_TITLE) : '';
// The chat this customer has going (a person on it or waiting, or King's from today): unread count for the bubble.
$kcLive = null;
try {
    if (function_exists('cdp_csTablesExist') && cdp_csTablesExist()) {
        // last_message_at is on the support clock (cdp_csNow), so the 12 hours are counted on it too.
        $kcRow = cdp_csOne("SELECT id, owner_state, customer_read_id FROM cdb_cs_conversations WHERE customer_id = :c AND (owner_state IN ('handoff_queued','human_active')
                OR (owner_state = 'ai_active' AND last_message_at >= :since))
            ORDER BY (owner_state = 'human_active') DESC, (owner_state = 'handoff_queued') DESC, updated_at DESC LIMIT 1", [':c' => (int) ($_SESSION['userid'] ?? 0), ':since' => cdp_csNow(-12 * 3600)]);
        if ($kcRow) {
            $kcUnread = cdp_csOne("SELECT COUNT(*) AS n, (SELECT MAX(id) FROM cdb_cs_messages WHERE conversation_id = :c2 AND visibility = 'public') AS last_id
                FROM cdb_cs_messages WHERE conversation_id = :c AND visibility = 'public' AND author_type IN ('king','staff','system') AND id > :r",
                [':c' => (int) $kcRow->id, ':c2' => (int) $kcRow->id, ':r' => (int) $kcRow->customer_read_id]);
            // last_id: the chat counts unread only after it (what the badge already counted is not counted twice).
            $kcLive = ['id' => (int) $kcRow->id, 'owner' => (string) $kcRow->owner_state, 'unread' => (int) ($kcUnread->n ?? 0), 'last_id' => (int) ($kcUnread->last_id ?? 0)];
        }
    }
} catch (Throwable $e) {
    $kcLive = null;
}
$kcCfg = [
    'url' => 'ajax/support/customer_ajax.php', 'mode' => $kcMode, 'name' => $kcKing, 'first' => $kcFirst, 'help' => 'support.php',
    'codes' => function_exists('sl_route') ? sl_route('PickupCodes') : 'pickup_codes.php', 'live' => $kcLive,
    // the support clock the messages are stamped with (not the page's timezone): "just now", "Seen"
    'now' => function_exists('cdp_csNow') ? cdp_csNow() : gmdate('Y-m-d H:i:s'),
    // the site's hour (the page runs on the site's timezone): "Good morning" matches the dashboard's own greeting
    'hour' => (int) date('G'),
    // seconds from the support clock to the site's clock: message times are shown in the site's time, like the rest of the app
    'shift' => (int) date('Z') - (function_exists('cdp_csClockTz') ? cdp_csClockTz()->getOffset(new DateTime('now')) : 0),
    'site' => is_object($kcCore = $core ?? ($GLOBALS['core'] ?? null)) && !empty($kcCore->site_name) ? (string) $kcCore->site_name : 'Swift Lane',
];
$kcSite = $kcCfg['site'];
$kcIni = mb_strtoupper(mb_substr($kcKing, 0, 1));
// King's avatar: the AI icon on the brand amber. Used on the bubble, the dashboard card, the chat header, the
// typing dots and every message King sends (dataJs/sl/king_chat.js reads SL_KC.face).
$kcFace = '<i class="ic i-ai kc-face" aria-hidden="true"></i>';
$kcCfg['face'] = $kcFace;
$kcHi = 'Hi, I\'m ' . $kcKing;
$kcAsk = 'How can I help' . ($kcFirst !== '' ? ', ' . $kcFirst : '') . '?';
?>
<?php if (!in_array('assets/swiftlane/king_chat.css', (array) ($slPage['styles'] ?? []), true)): ?><link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/king_chat.css')) ?>"><?php endif; ?>
<div class="kc" id="kc" role="region" data-mode="<?= h($kcMode) ?>" data-open="0" data-owner="ai_active" aria-label="Chat with <?= h($kcKing) ?>, <?= h($kcSite) ?> customer care"><?php /* a div, not a section: the page reveal animation (transform) must not become the containing block of the full-screen chat */ ?>
<?php if ($kcMode === 'dock'): ?>
  <button type="button" class="kc-launch" data-kc-launch aria-controls="kc-panel" aria-expanded="false">
    <span class="kc-av face" aria-hidden="true"><?= $kcFace ?><i class="kc-on"></i></span>
    <span class="kc-launch-t"><b><?= h($kcHi) ?> <span class="kc-wave" aria-hidden="true">👋</span></b><small><?= h($kcAsk) ?></small></span>
    <span class="kc-badge" data-kc-badge hidden></span>
    <span class="kc-sr">Open the chat with <?= h($kcKing) ?> from <?= h($kcSite) ?> customer care</span>
  </button>
<?php else: ?>
  <button type="button" class="kc-teaser" data-kc-teaser aria-controls="kc-panel" aria-expanded="false">
    <span class="kc-teaser-top"><span class="kc-av lg face" aria-hidden="true"><?= $kcFace ?><i class="kc-on"></i></span><span class="kc-who"><b><?= h($kcKing) ?></b><small><?= h($kcSite) ?> customer care · online now</small></span><span class="kc-badge" data-kc-badge hidden></span></span>
    <span class="kc-teaser-hello" data-kc-hello><?= h($kcHi) ?>. <?= h($kcAsk) ?></span>
    <span class="kc-teaser-lead" data-kc-teaser-lead>Checking your parcels…</span>
    <span class="kc-teaser-tags" data-kc-teaser-tags></span>
    <span class="kc-teaser-go"><i class="ic ic-sm i-message" aria-hidden="true"></i>Chat with <?= h($kcKing) ?><i class="ic ic-sm i-right" aria-hidden="true"></i></span>
  </button>
<?php endif; ?>
  <div class="kc-panel" id="kc-panel" role="<?= $kcMode === 'dock' ? 'dialog' : 'region' ?>" aria-labelledby="kc-name"<?= $kcMode === 'dock' ? ' hidden' : '' ?>>
    <header class="kc-head">
      <span class="kc-av lg face" data-kc-av aria-hidden="true"><?= $kcFace ?><i class="kc-on"></i></span>
      <div class="kc-who"><b id="kc-name" data-kc-name><?= h($kcKing) ?></b><small data-kc-sub><?= h($kcSite) ?> customer care · online now</small></div>
      <button type="button" class="kc-person" data-kc-human><i class="ic ic-sm i-user" aria-hidden="true"></i><span>Talk to a person</span></button>
      <button type="button" class="kc-ib" data-kc-close aria-label="<?= $kcMode === 'dock' ? 'Close the chat' : 'Minimise the chat' ?>" title="<?= $kcMode === 'dock' ? 'Close' : 'Minimise' ?>"><i class="ic ic-sm <?= $kcMode === 'dock' ? 'i-x' : 'i-minus' ?>" aria-hidden="true"></i></button>
    </header>
    <div class="kc-status" data-kc-status role="status" hidden></div>
    <div class="kc-min" data-kc-min hidden><span class="kc-min-t" data-kc-min-t></span><button type="button" class="sl-btn sm" data-kc-expand><i class="ic ic-sm i-message" aria-hidden="true"></i>Open chat</button></div>
    <div class="kc-log" data-kc-scroll>
      <div class="kc-thread" role="log" aria-live="polite" aria-relevant="additions" data-kc-thread></div>
      <div class="kc-typing" data-kc-typing hidden><span class="kc-av sm face" aria-hidden="true" data-kc-typing-av><?= $kcFace ?></span><span class="kc-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="kc-typing-t" data-kc-typing-t></span></div>
    </div>
    <div class="kc-chips" data-kc-chips role="group" aria-label="Quick questions"></div>
    <div class="kc-files" data-kc-files hidden></div>
    <form class="kc-form" data-kc-form autocomplete="off">
      <input type="file" data-kc-file accept="image/*,application/pdf" multiple hidden tabindex="-1" aria-hidden="true">
      <button type="button" class="kc-attach" data-kc-attach aria-label="Attach a photo or file" title="Attach a photo or file"><i class="ic i-paperclip" aria-hidden="true"></i></button>
      <label class="kc-sr" for="kc-text">Message <?= h($kcKing) ?></label>
      <textarea id="kc-text" data-kc-text rows="1" maxlength="4000" placeholder="Ask <?= h($kcKing) ?> anything…" enterkeyhint="send"></textarea>
      <button type="submit" class="kc-send" data-kc-send aria-label="Send"><i class="ic i-send" aria-hidden="true"></i></button>
    </form>
    <small class="kc-foot" data-kc-foot data-king="<?= h($kcKing . ' is ' . $kcSite . "'s automated customer care assistant and only sees your own account. A person from our team is one tap away. Never share passwords or card numbers here.") ?>"><?= h($kcKing) ?> is <?= h($kcSite) ?>'s automated customer care assistant and only sees your own account. A person from our team is one tap away. Never share passwords or card numbers here.</small>
  </div>
</div>
<script>window.SL_KC = <?= json_encode($kcCfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
<script defer src="<?= h(cdp_asset('dataJs/sl/king_chat.js')) ?>"></script>
