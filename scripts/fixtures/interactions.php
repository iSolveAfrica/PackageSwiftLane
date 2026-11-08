<?php
// Isolated rendering of real templates; no loader, database or provider calls.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('CDP_ROOT', dirname(__DIR__, 2));
require CDP_ROOT . '/helpers/sl_chart_interactions.php';
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function sl_route($name, $params = []) { return strtolower($name) . '.php' . ($params ? '?' . http_build_query($params) : ''); }
function sl_url($params = []) { return '?' . http_build_query($params); }
function sl_num($n) { return number_format((float) $n); }
function sl_pager() { return ''; }
$core = (object) ['site_name' => 'Swift Lane'];
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Interaction fixture</title>
<?php foreach (['swiftlane.css','app.css','pages/ControlPanels.css','pages/AirShipments.css','pages/StaffAI.css','pages/AiChatMobile.sheet.css','chart-interactions.css','ai-response.css'] as $css): ?><link rel="stylesheet" href="/assets/swiftlane/<?= $css ?>"><?php endforeach; ?>
<style>.fixture-content{padding:24px;display:flex;flex-direction:column;gap:24px}.fixture-extra{padding:16px;background:var(--card);border:1px solid var(--line);border-radius:16px}.fixture-extra svg{width:100%;height:150px}#ai{min-width:0}.ai-th{min-width:0}.ai-chat{min-width:0}</style>
<div id="sl-app" class="sl-app"><main class="fixture-content">
<div id="control-fixture">
<?php
$d = ['pins' => [], 'updated' => '08:00', 'health' => [], 'sections' => [], 'attn' => array_map(function ($label, $value) {
    return ['l' => $label, 'n' => $value, 'href' => 'courier_list.php?filter=held', 'up' => false, 'd' => 'Same as yesterday', 'aria' => 'Seven days', 's' => array_fill(0, 7, $value), 'bars' => array_fill(0, 7, 'height:' . ($value ? 90 : 4) . '%')];
}, ['Awaiting approval', 'Held parcels', 'Unassigned pickups', 'Open pre-alerts'], [0, 160, 49, 1327])];
include CDP_ROOT . '/views/sl/pages/ControlPanels.php';
?>
</div><div id="shipment-fixture">
<?php
$d = ['client'=>false,'month'=>1,'attention'=>0,'canImport'=>false,'canAdd'=>false,'metrics'=>[],'all'=>1,'q'=>'','status'=>0,'statuses'=>[],'drivers'=>[],'driver'=>0,'filters'=>[],'filter'=>'','period'=>'all','per'=>25,'canSelect'=>false,'canStatus'=>false,'canAssign'=>false,'canLabel'=>false,'page'=>1,'count'=>1,
'rows'=>[['id'=>1,'track'=>'SL-TEST-001','carrier'=>'','date'=>'Nov 8, 2026','time'=>'08:03','sender_id'=>1,'sender'=>'Example Customer','from'=>'TEST-001','ini'=>'HA','name'=>'Example Customer','dest'=>'Accra, Ghana','eta'=>'Nov 15, 2026','kg'=>'4 lb','total'=>'$20','payCls'=>'pending','pay'=>'Pending','stCls'=>'pending','st'=>'On Route','badges'=>[],'acts'=>[],'driver_id'=>0]]];
include CDP_ROOT . '/views/sl/pages/AirShipments.php';
?>
</div><section class="fixture-extra"><h2>Mixed chart</h2><svg id="mixed-chart" viewBox="0 0 400 150" <?= sl_chartAttrs([['name'=>'Shipments','values'=>[0,12,8]],['name'=>'Packages','values'=>[2,4,6]]], ['Nov 6','Nov 7','Nov 8'], 'Daily volume') ?>><rect x="175" y="25" width="50" height="125" fill="#D6D2CA"/><polyline points="0,130 200,110 400,90" fill="none" stroke="#E08A3C"/></svg></section>
<section class="fixture-extra"><h2>Heatmap</h2><div class="sl-heat" id="test-heat" style="grid-template-columns:repeat(2,14px)"><b class="h0" title="Nov 7: 0 parcels"></b><b class="h3" title="Nov 8: 12 parcels"></b></div></section>
<section class="fixture-extra"><div id="test-donut" class="donut" style="width:150px;height:150px;background:conic-gradient(#333 0% 60%,#abc 60% 100%);border-radius:50%" <?= sl_chartAttrs([['name'=>'Parcels','values'=>[0,12,8]]], ['No data','In transit','Delivered'], 'Status mix', null, null, 'donut') ?>></div></section>
<div id="ai-fixture">
<?php
$answer = <<<'MD'
**Your priorities today:** review stale tracking, complete missing shipment details, and verify overdue payments.

### Records to check first

| Shipment | Customer | Current status | Recommended action |
|---|---|---|---|
| [SL-TEST-001](courier_view.php?id=1) | Example Customer | On Route | Confirm location and complete shipment details. |
| SL-TEST-002 | Example Customer | On Route | Review tracking and payment. |

### What to do today

1. **Confirm air mode** in the shipment list.
2. Check physical location before updating customers.
3. Validate pricing before chasing payment.

> Verify the figures in the linked records before taking action.
MD;
$d = ['settings'=>[],'configured'=>true,'canUse'=>true,'canManage'=>false,'canInsights'=>false,'scope'=>'Staff','convs'=>[],'conv'=>null,'autoSend'=>false,'model'=>'Fixture','first'=>'Alex','starters'=>[],'msgs'=>[['me'=>true,'text'=>'Which shipments need attention?','tools'=>[]],['me'=>false,'text'=>$answer,'tools'=>['count_records · 2 records']]],'draft'=>'','facts'=>[],'tools'=>[],'ideas'=>[],'qTotal'=>2,'byArea'=>[],'unanswered'=>0];
include CDP_ROOT . '/views/sl/pages/StaffAI.php';
?>
</div></main></div><div id="sl-toast" role="status" hidden></div>
<script src="/assets/swiftlane/swiftlane.js"></script><script src="/dataJs/sl/chart_interactions.js"></script>
<script src="/assets/vendor/libs/marked/marked.min.js"></script><script src="/assets/vendor/libs/dompurify/purify.min.js"></script><script src="/dataJs/sl/ai_response.js"></script><script src="/dataJs/sl/ai_chat.js"></script>
</html>
