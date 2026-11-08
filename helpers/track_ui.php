<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************


/**
 * Presentation helpers for the public tracking pages (track.php,
 * track_online_shopping.php): stage summary, dates and status colours.
 *
 * Pure functions, no database. Views escape every value they print.
 */

/**
 * Where the shipment is along its journey, as numbers a view can print.
 *
 * @param array $progress cdp_trackProgress() / cdp_airJourney() result
 * @return array{current:int,count:int,percent:int,label:string,next:?string,done:bool}
 */
function cdp_trackStageSummary(array $progress)
{
    $steps = isset($progress['steps']) && is_array($progress['steps']) ? array_values($progress['steps']) : [];
    $count = count($steps);
    $index = isset($progress['index']) ? (int) $progress['index'] : 0;
    if ($count === 0) {
        return ['current' => 0, 'count' => 0, 'percent' => 0, 'label' => '', 'next' => null, 'done' => false];
    }
    $index = max(0, min($index, $count - 1));
    $done  = $index >= $count - 1;

    // The last stage is the destination: 100% there, otherwise a linear share.
    $percent = $count > 1 ? (int) round(($index / ($count - 1)) * 100) : 100;

    return [
        'current' => $index + 1,
        'count'   => $count,
        'percent' => $percent,
        'label'   => (string) ($steps[$index]['label'] ?? ''),
        'next'    => $done ? null : (string) ($steps[$index + 1]['label'] ?? ''),
        'done'    => $done,
    ];
}

/**
 * Stage markers that come before the fold on a long rail. The rail folds
 * completed stages away when there are more than $visibleDone of them before
 * the current one; the last $visibleDone stay in view for context.
 *
 * @return int number of leading stages to fold (0 = no fold)
 */
function cdp_trackFoldCount(array $progress, $visibleDone = 2)
{
    $index = isset($progress['index']) ? (int) $progress['index'] : 0;
    $count = isset($progress['steps']) && is_array($progress['steps']) ? count($progress['steps']) : 0;
    $visibleDone = max(0, (int) $visibleDone);
    if ($count < 8 || $index <= $visibleDone + 1) {
        return 0; // short rails and early shipments show everything
    }
    return $index - $visibleDone;
}

/**
 * Parse a stored datetime. Returns a timestamp or null for blanks / zero dates.
 */
function cdp_trackTimestamp($datetime)
{
    $datetime = trim((string) $datetime);
    if ($datetime === '' || strpos($datetime, '0000-00-00') === 0) {
        return null;
    }
    $ts = strtotime($datetime);
    return $ts === false ? null : $ts;
}

/** Human date, the one format the tracking pages use everywhere. */
function cdp_trackFmtDate($datetime)
{
    $ts = cdp_trackTimestamp($datetime);
    return $ts === null ? '' : date('M d, Y · h:i A', $ts);
}

/** ISO 8601 value for a <time datetime=""> attribute. */
function cdp_trackIsoDate($datetime)
{
    $ts = cdp_trackTimestamp($datetime);
    return $ts === null ? '' : date('c', $ts);
}

/**
 * "just now", "12 minutes ago", "3 hours ago", "yesterday", "4 days ago";
 * anything older than a month falls back to the plain date so the copy never
 * reads "412 days ago".
 *
 * @param string   $datetime
 * @param int|null $now      injectable for tests
 */
function cdp_trackRelativeDate($datetime, $now = null)
{
    $ts = cdp_trackTimestamp($datetime);
    if ($ts === null) {
        return '';
    }
    $now  = $now === null ? time() : (int) $now;
    $diff = $now - $ts;
    if ($diff < 0) {
        $diff = 0; // a clock that runs ahead must not say "in -2 minutes"
    }
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }
    $d = (int) floor($diff / 86400);
    if ($d === 1) {
        return 'yesterday';
    }
    if ($d < 31) {
        return $d . ' days ago';
    }
    return date('M d, Y', $ts);
}

/**
 * Validate a cdb_styles colour. Accepts #rgb / #rrggbb (hash optional);
 * anything else becomes the brand amber so a bad row never breaks the page.
 */
function cdp_trackSafeColor($color, $fallback = '#FFCB01')
{
    $c = trim((string) $color);
    if (preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $c, $m)) {
        $hex = $m[1];
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return '#' . strtoupper($hex);
    }
    return $fallback;
}

/**
 * WCAG relative luminance of a hex colour (0 = black, 1 = white).
 */
function cdp_trackLuminance($color)
{
    $hex = ltrim(cdp_trackSafeColor($color), '#');
    $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $lin = array_map(function ($v) {
        $v = $v / 255;
        return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }, $rgb);
    return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/**
 * Text colour that stays readable on a solid fill of $color: the brand ink on
 * light / bright statuses (yellow, lime, cyan), white on dark ones. Used as the
 * fallback where the browser cannot tint the surface with color-mix().
 */
function cdp_trackColorInk($color, $ink = '#192A3E', $light = '#FFFFFF')
{
    $l = cdp_trackLuminance($color);
    // Contrast of white vs the fill and of ink vs the fill; pick the better.
    $cWhite = 1.05 / ($l + 0.05);
    $cInk   = ($l + 0.05) / (cdp_trackLuminance($ink) + 0.05);
    return $cInk >= $cWhite ? $ink : $light;
}
