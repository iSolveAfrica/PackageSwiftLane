<?php
/**
 * Phone-app screens (design canvas Page 2, the M-* artboards) inside the application shell.
 *
 * A screen is an ordinary sl_render() page whose body is <div class="mxp"> filled with the design's
 * mx- / ra- / rb- / rc- / rd- / cd- components (assets/swiftlane/pwa/mx.css). The same markup is the phone
 * screen, the installed app screen and, with class "wide", a two or three column page on a computer.
 *
 *   sl_render('RiderWallet', sl_mxPage(['id' => 'wallet', 'title' => 'Wallet']), $data);
 */
require_once __DIR__ . '/sl_view.php';

/** Adds the phone-app stylesheet and script to a page definition. */
function sl_mxPage(array $page): array
{
    $page['styles'] = array_merge(['assets/swiftlane/pwa/mx.css'], (array) ($page['styles'] ?? []));
    $page['scripts'] = array_merge(['dataJs/sl/mx.js'], (array) ($page['scripts'] ?? []));
    return $page;
}

/**
 * Title row of a screen: back button, title and sub line, then icon actions.
 * $back: URL of the screen one level up (null = no back button). $actions: trusted HTML of .mx-ib buttons.
 */
function sl_mxHead(string $title, string $sub = '', ?string $back = null, string $actions = ''): string
{
    $out = '<header class="mxp-head">';
    if ($back !== null) {
        $out .= '<a class="mx-ib" href="' . h($back) . '" data-mx-back aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a>';
    }
    $out .= '<div class="mx-ttl"><h1>' . h($title) . '</h1>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</div>';
    return $out . $actions . '</header>';
}

/** One icon button for the title row. */
function sl_mxIcon(string $icon, string $label, string $href, int $badge = 0): string
{
    return '<a class="mx-ib" href="' . h($href) . '" aria-label="' . h($label) . '"><i class="ic i-' . h($icon) . '" aria-hidden="true"></i>' . ($badge > 0 ? '<b>' . ($badge > 99 ? '99+' : $badge) . '</b>' : '') . '</a>';
}

/** Section heading with an optional link on the right. */
function sl_mxSec(string $title, string $linkLabel = '', string $href = ''): string
{
    return '<div class="mx-sec"><h2>' . h($title) . '</h2>' . ($linkLabel !== '' ? '<a href="' . h($href) . '">' . h($linkLabel) . '</a>' : '') . '</div>';
}

/** KPI tile (.mx-kt). $trend: '' | 'up' | 'down'. */
function sl_mxKpi(string $icon, string $label, string $value, string $note = '', string $trend = ''): string
{
    return '<div class="mx-kt"><div class="mx-kh"><span class="mx-kb"><i class="ic ic-sm i-' . h($icon) . '" aria-hidden="true"></i></span><small>' . h($label) . '</small></div><b>' . h($value) . '</b>' . ($note !== '' ? '<em class="' . h($trend) . '">' . h($note) . '</em>' : '') . '</div>';
}

/**
 * List row (.mx-row). $lead: icon name, or initials when $leadIsAvatar. $right: trusted HTML for .mx-rr.
 */
function sl_mxRow(string $lead, string $title, string $sub = '', string $right = '', string $href = '', bool $leadIsAvatar = false, string $cls = ''): string
{
    $tag = $href !== '' ? 'a' : 'div';
    $leadHtml = $leadIsAvatar ? '<span class="mx-ava">' . h($lead) . '</span>' : '<span class="mx-li"><i class="ic i-' . h($lead) . '" aria-hidden="true"></i></span>';
    return '<' . $tag . ' class="mx-row' . ($cls !== '' ? ' ' . h($cls) : '') . '"' . ($href !== '' ? ' href="' . h($href) . '"' : '') . '>' . $leadHtml
        . '<span class="mx-rm"><b>' . h($title) . '</b>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</span>'
        . ($right !== '' ? '<span class="mx-rr">' . $right . '</span>' : '') . '</' . $tag . '>';
}

/** Empty state: nothing to show yet, said plainly, with an optional next step. */
function sl_mxEmpty(string $icon, string $title, string $text = '', string $actionLabel = '', string $href = ''): string
{
    return '<div class="mxp-empty"><span class="mx-li"><i class="ic i-' . h($icon) . '" aria-hidden="true"></i></span><b>' . h($title) . '</b>' . ($text !== '' ? '<span>' . h($text) . '</span>' : '')
        . ($actionLabel !== '' ? '<a class="mx-lnk" href="' . h($href) . '">' . h($actionLabel) . '</a>' : '') . '</div>';
}

/** A switch row (.mx-tg). With $save (AJAX endpoint) the switch posts {key, value} and rolls back on failure. */
function sl_mxToggle(string $title, string $sub, bool $on, string $save = '', string $key = '', array $params = [], bool $disabled = false): string
{
    $attrs = '';
    if ($save !== '') {
        $attrs .= ' data-sl-save="' . h($save) . '" data-key="' . h($key) . '"';
        foreach ($params as $k => $v) {
            $attrs .= ' data-p-' . h($k) . '="' . h($v) . '"';
        }
    }
    return '<div class="mx-tg"><span class="mx-rm"><b>' . h($title) . '</b>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</span>'
        . '<button type="button" class="mx-sw' . ($on ? ' on' : '') . '" role="switch" aria-checked="' . ($on ? 'true' : 'false') . '" aria-label="' . h($title) . '"' . $attrs . ($disabled ? ' disabled aria-disabled="true"' : '') . '></button></div>';
}

/**
 * A shell-less phone screen (the rider app): the canvas app bar, then the screen body.
 * $lead: null = nothing, ['back' => url] = back button, ['avatar' => 'KA', 'href' => url] = account avatar.
 * $actions: trusted HTML of .mx-ib buttons (sl_mxIcon()). Close with sl_mxScreenClose().
 */
function sl_mxScreenOpen(string $title, string $sub = '', ?array $lead = null, string $actions = '', bool $wide = false): string
{
    $out = '<div class="mxp-screen' . ($wide ? ' wide' : '') . '"><header class="mx-top">';
    if (isset($lead['back'])) {
        $out .= '<a class="mx-ib" href="' . h($lead['back']) . '" data-mx-back aria-label="Back"><i class="ic i-left" aria-hidden="true"></i></a>';
    } elseif (isset($lead['avatar'])) {
        $out .= '<a class="mx-av" href="' . h($lead['href'] ?? '#') . '" aria-label="My account">' . h($lead['avatar']) . (!empty($lead['online']) ? '<i aria-hidden="true"></i>' : '') . '</a>';
    }
    $out .= '<div class="mx-ttl"><h1>' . h($title) . '</h1>' . ($sub !== '' ? '<small>' . h($sub) . '</small>' : '') . '</div>' . $actions . '</header>';
    return $out . '<main class="mxp' . ($wide ? ' wide' : '') . '" id="sl-main" tabindex="-1">';
}

function sl_mxScreenClose(): string
{
    return '</main></div>';
}
