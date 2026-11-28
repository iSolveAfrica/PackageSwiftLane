<?php
/**
 * Phone mock-up for WhatsApp and SMS previews (shared editor kit, WP-B).
 *
 * dataJs/sl/comms_editor.js fills it: SLCommsEditor.phone(el, options) finds the
 * parts by data-cp-* and renders the message (WhatsApp *bold* _italic_ ~strike~
 * ```mono```, placeholders filled from a sample or shown as chips, ticks, time,
 * media placeholder; SMS bubble with the character / part counter). The script
 * builds exactly the same markup when it is given an empty element, so a page
 * may either include this partial or let the script draw the phone.
 * Styles: assets/swiftlane/comms_editor.css (.cp-*).
 *
 * Set $cp before the include (all keys optional):
 *   channel  'whatsapp' (default) | 'sms'
 *   name     WhatsApp: the contact the message goes to (header); SMS: the sender ID the customer sees
 *   sub      line under the name (WhatsApp default 'online'; SMS default 'Text message')
 *   body     initial message text (plain; escaped here, formatted by the script)
 *   id       id attribute of the phone
 *   class    extra classes
 *   counter  bool, SMS only: character / part counter under the phone (default true)
 *   time     clock text (default the server time, H:i)
 */
$cp = (isset($cp) && is_array($cp) ? $cp : []) + [
    'channel' => 'whatsapp', 'name' => '', 'sub' => null, 'body' => '', 'id' => '', 'class' => '', 'counter' => true, 'time' => date('H:i'),
];
$cpWa = $cp['channel'] !== 'sms';
$cpName = trim((string) $cp['name']) !== '' ? (string) $cp['name'] : ($cpWa ? 'Customer' : 'SWIFTLANE');
$cpSub = $cp['sub'] !== null ? (string) $cp['sub'] : ($cpWa ? 'online' : 'Text message');
$cpIni = '';
foreach (preg_split('/\s+/', trim($cpName)) ?: [] as $cpW) {
    if ($cpW !== '' && mb_strlen($cpIni) < 2) {
        $cpIni .= mb_strtoupper(mb_substr($cpW, 0, 1));
    }
}
$cpTime = (string) $cp['time'];
?>
<div class="cp <?= $cpWa ? 'cp-wa' : 'cp-sms' ?><?= $cp['class'] !== '' ? ' ' . h($cp['class']) : '' ?>"<?= $cp['id'] !== '' ? ' id="' . h($cp['id']) . '"' : '' ?> data-cp="<?= $cpWa ? 'whatsapp' : 'sms' ?>" role="figure" aria-label="<?= $cpWa ? 'WhatsApp' : 'SMS' ?> preview">
<div class="cp-sc">
<div class="cp-st" aria-hidden="true"><span data-cp-time><?= h($cpTime) ?></span><span class="cp-ic"><i class="sig"><b></b><b></b><b></b><b></b></i><i class="wifi"></i><i class="bat"><b></b></i></span></div>
<?php if ($cpWa): ?>
<div class="cp-hd"><span class="cp-back" aria-hidden="true">&#8249;</span><span class="cp-av" data-cp-av><?= h($cpIni ?: '?') ?></span><span class="cp-who"><b data-cp-name><?= h($cpName) ?></b><small data-cp-sub><?= h($cpSub) ?></small></span><span class="cp-acts" aria-hidden="true"><i class="cp-i-vid"></i><i class="cp-i-call"></i><i class="cp-i-dots"></i></span></div>
<div class="cp-bd" data-cp-body><span class="cp-day">Today</span><span class="cp-e2e">Messages and calls are end-to-end encrypted.</span>
<div class="cp-bub out" data-cp-bubble><div class="cp-media" data-cp-media hidden></div><div class="cp-txt" data-cp-text><?= nl2br(h((string) $cp['body']), false) ?></div><span class="cp-meta"><span data-cp-clock><?= h($cpTime) ?></span><span class="cp-ticks read" data-cp-ticks aria-label="Read">&#10003;&#10003;</span></span></div>
<div class="cp-btns" data-cp-btns hidden></div>
</div>
<div class="cp-in" aria-hidden="true"><span class="cp-field"><i class="cp-i-emoji"></i>Message<i class="cp-i-clip"></i></span><span class="cp-mic"><i class="cp-i-mic"></i></span></div>
<?php else: ?>
<div class="cp-hd"><span class="cp-av" data-cp-av><?= h($cpIni ?: '?') ?></span><b data-cp-name><?= h($cpName) ?></b><small data-cp-sub><?= h($cpSub) ?></small></div>
<div class="cp-bd" data-cp-body><span class="cp-day">Text Message · Today <span data-cp-clock><?= h($cpTime) ?></span></span>
<div class="cp-bub in" data-cp-bubble><div class="cp-txt" data-cp-text><?= nl2br(h((string) $cp['body']), false) ?></div></div>
<div class="cp-parts" data-cp-parts hidden></div>
</div>
<div class="cp-in" aria-hidden="true"><span class="cp-field">Text Message</span><span class="cp-mic"><i class="cp-i-up"></i></span></div>
<?php endif; ?>
</div>
<?php if (!$cpWa && $cp['counter']): ?><div class="cp-count" data-cp-count aria-live="polite"></div><?php endif; ?>
</div>
<?php unset($cp, $cpWa, $cpName, $cpSub, $cpIni, $cpTime, $cpW); ?>
