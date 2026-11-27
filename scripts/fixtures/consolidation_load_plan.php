<?php
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function sl_weight($v,$digits=1){return number_format((float)$v,$digits).' kg';}
function sl_tableExists($table){return true;}
function sl_row($sql,$bind){return (object)['revision'=>2,'positions_json'=>'[]'];}
$sea=true;$self='consolidate_view.php?id=1&type=sea';$can=['packing'=>''];
$d=['cid'=>1,'type'=>'sea','code'=>'SEA-001','capacityKg'=>1000,'weightKg'=>300,'canEdit'=>true,'lq'=>'','pcsN'=>2,'custN'=>1,'steps'=>[],'rows'=>[]];
foreach([11,12] as $i=>$id)$d['rows'][]=['oid'=>$id,'id'=>'SEA-'.$id,'name'=>'Test Customer','w'=>($i+1).'00 kg','weightKg'=>($i+1)*100,'href'=>'#package-'.$id,'stC'=>'','stL'=>'Loaded'];
?><!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="test-token"><style>
:root{--card:#fff;--line:#ddd;--ink:#222;--mut:#777}*{box-sizing:border-box}body{font-family:Arial;margin:16px}.row{display:flex;gap:8px}.wrap{flex-wrap:wrap}.between{justify-content:space-between}.col{display:flex;flex-direction:column}.sl-tw{overflow:auto}.sl-table{border-collapse:collapse;width:100%}.sl-table td{padding:8px}.sl-in{padding:8px}.sl-btn{padding:8px;border:1px solid #ddd;background:white;border-radius:8px}a{text-decoration:none}button{cursor:pointer}
</style><link rel="stylesheet" href="/assets/swiftlane/pages/ConLoadPlan.css"></head><body>
<?php include __DIR__.'/../../views/sl/pages/partials/con_load_plan.php'; ?>
<script src="/dataJs/sl/consolidation_load_plan.js"></script></body></html>
