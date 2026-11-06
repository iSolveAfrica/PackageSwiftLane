<?php
/** Data for Automations (Automations.dc.html): cdb_automations + cdb_automation_runs. */
require_once CDP_ROOT . '/helpers/sl_view.php';
require_once CDP_ROOT . '/helpers/automations.php';

function sl_automationsData($user, $core): array
{
    $d = ['ready' => cdp_automationsReady()];
    $trig = cdp_automationTriggers();
    $acts = cdp_automationActions();
    $conds = cdp_automationConditions();
    $all = cdp_automationsAll();
    $q = trim((string) ($_GET['q'] ?? ''));
    $lf = (string) ($_GET['lf'] ?? 'all');
    $byUser = [];
    foreach (sl_rows('SELECT id, fname, lname FROM cdb_users WHERE userlevel <> 1') as $u) {
        $byUser[(int) $u->id] = trim($u->fname . ' ' . $u->lname);
    }
    $statuses = [];
    foreach (sl_rows('SELECT id, mod_style FROM cdb_styles ORDER BY id') as $s) {
        $statuses[(int) $s->id] = (string) $s->mod_style;
    }
    $condLabel = function (array $c) use ($conds, $statuses) {
        $k = $c['key'] ?? '';
        $v = $c['value'] ?? '';
        if (!isset($conds[$k])) return '';
        if ($k === 'status_is') {
            $v = implode(' or ', array_map(fn($i) => $statuses[(int) $i] ?? $i, is_array($v) ? $v : explode(',', (string) $v)));
        }
        return $conds[$k]['label'] . ($v !== '' && $conds[$k]['input'] !== 'none' ? ' ' . $v : '');
    };
    $rows = [];
    $total = 0;
    $onN = 0;
    foreach ($all as $a) {
        $total += (int) $a['run_count'];
        $onN += (int) $a['enabled'];
        if ($q !== '' && stripos($a['name'], $q) === false) continue;
        if ($lf === 'on' && !(int) $a['enabled']) continue;
        if ($lf === 'off' && (int) $a['enabled']) continue;
        $t = $trig[$a['trigger_key']] ?? ['label' => $a['trigger_key'], 'icon' => 'i-bolt'];
        $rows[] = ['id' => (int) $a['id'], 'name' => $a['name'], 'icon' => $t['icon'], 'cond' => implode(' · ', array_filter(array_map($condLabel, $a['conditions']))) ?: 'No condition',
            'when' => $t['label'], 'then' => implode(', ', array_map(fn($x) => $acts[$x['type'] ?? '']['label'] ?? ($x['type'] ?? ''), $a['actions'])),
            'by' => $byUser[(int) $a['created_by']] ?? 'System', 'created' => sl_date($a['created_at']), 'last' => $a['last_run_at'] ? sl_ago($a['last_run_at']) : 'Never',
            'runs' => sl_num($a['run_count']), 'on' => (int) $a['enabled'] === 1, 'json' => $a];
    }
    $d['rows'] = $rows;
    $d['totalRuns'] = sl_num($total);
    $d['onCount'] = $onN;
    $d['q'] = $q;
    $d['lf'] = $lf;
    $d['lfs'] = [['f' => 'all', 'label' => 'All'], ['f' => 'on', 'label' => 'On'], ['f' => 'off', 'label' => 'Paused']];
    $editId = (int) ($_GET['edit'] ?? 0);
    $edit = null;
    foreach ($all as $a) {
        if ((int) $a['id'] === $editId) $edit = $a;
    }
    $d['edit'] = $edit;
    $d['triggers'] = $trig;
    $d['actions'] = $acts;
    $d['conds'] = $conds;
    $d['statuses'] = $statuses;
    $d['vars'] = cdp_automationVars();
    $d['runs'] = array_map(fn($r) => ['name' => $r->name ?? ('#' . $r->automation_id), 'outcome' => $r->outcome, 'detail' => $r->detail, 'label' => $r->entity_label, 'at' => sl_ago($r->created_at)],
        $d['ready'] ? sl_rows('SELECT r.*, a.name FROM cdb_automation_runs r LEFT JOIN cdb_automations a ON a.id = r.automation_id ORDER BY r.id DESC LIMIT 8') : []);
    $d['templates'] = [
        ['label' => 'Ready for pickup → WhatsApp', 'icon' => 'i-message', 'name' => 'Ready for pickup message', 'trigger' => 'shipment.status', 'conditions' => [['key' => 'status_is', 'value' => '32']], 'actions' => [['type' => 'whatsapp', 'message' => 'Hi [FIRST_NAME], your parcel [TRACKING] is ready for pickup at [SITE_NAME].']]],
        ['label' => 'Delivered → thank-you e-mail', 'icon' => 'i-mail', 'name' => 'Thank you after delivery', 'trigger' => 'shipment.status', 'conditions' => [['key' => 'status_is', 'value' => '8']], 'actions' => [['type' => 'email', 'subject' => 'Your parcel [TRACKING] was delivered', 'message' => 'Hi [FIRST_NAME], thank you for shipping with [SITE_NAME].']]],
        ['label' => 'Held parcel → tell the team', 'icon' => 'i-alert', 'name' => 'Held parcel alert', 'trigger' => 'shipment.status', 'conditions' => [['key' => 'status_is', 'value' => '28']], 'actions' => [['type' => 'staff_email', 'subject' => 'Parcel [TRACKING] held', 'message' => '[TRACKING] for [NAME] is on hold.'], ['type' => 'log']]],
    ];
    $d['canEdit'] = $user->cdp_hasPermission('manage_automations');
    return $d;
}
