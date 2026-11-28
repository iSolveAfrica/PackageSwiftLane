<?php
/**
 * templates_whatsapp_edit.php: replaced by Communications > Automations. An old link with ?id=<template row>
 * opens the trigger whose whatsapp text lives in that row (or "Unassigned old templates"),
 * on that row. 302 (not 301) while the area is still changing.
 */
require_once __DIR__ . '/helpers/comms_triggers.php';
$id = is_string($_GET['id'] ?? null) ? (int) $_GET['id'] : 0;
$to = 'comms_automations.php?tab=other&ch=whatsapp';
if ($id > 0) {
    $key = 'legacy:unassigned';
    foreach (cdp_commLegacyTriggers() as $src => $t) {
        if (in_array($id, array_map('intval', (array) ($t['templates']['whatsapp']['ids'] ?? [])), true)) {
            $key = 'legacy:' . $src;
            break;
        }
    }
    $to = 'comms_automations.php?edit=' . rawurlencode($key) . '&ch=whatsapp&id=' . $id;
}
header('Location: ' . $to, true, 302);
exit;
