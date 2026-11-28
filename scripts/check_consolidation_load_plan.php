<?php
require_once __DIR__.'/../helpers/consolidation_load_plan.php';
function expectRejected(array $positions,array $ids): void {
    try {sl_loadPositions($positions,$ids);}catch(DomainException $e){return;}
    throw new RuntimeException('Invalid cargo position was accepted');
}
$valid=[['id'=>41,'bay'=>1,'row'=>1,'tier'=>1],['id'=>42,'bay'=>1,'row'=>2,'tier'=>1]];
if(sl_loadPositions($valid,[41,42])!==$valid)throw new RuntimeException('Valid plan changed');
expectRejected([['id'=>99,'bay'=>1,'row'=>1,'tier'=>1]],[41]);
expectRejected([['id'=>41,'bay'=>1,'row'=>1,'tier'=>1],['id'=>41,'bay'=>2,'row'=>1,'tier'=>1]],[41,42]);
expectRejected([['id'=>41,'bay'=>1,'row'=>1,'tier'=>1],['id'=>42,'bay'=>1,'row'=>1,'tier'=>1]],[41,42]);
expectRejected([['id'=>41,'bay'=>0,'row'=>1,'tier'=>1]],[41]);
expectRejected([['id'=>41,'bay'=>1.5,'row'=>1,'tier'=>1]],[41]);
expectRejected([['id'=>41,'bay'=>1,'row'=>1,'tier'=>11]],[41]);
if(sl_loadPositions([],[41])!==[])throw new RuntimeException('Clearing plan failed');
echo "Load-plan validation passed\n";
