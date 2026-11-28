<?php
/** Coordinates describe the dispatcher's recorded bay, row and tier. */
function sl_loadPositions(array $positions, array $memberIds): array
{
    if (count($positions)>count($memberIds)) throw new DomainException('The manifest changed. Refresh before saving.');
    $members=array_fill_keys(array_map('intval',$memberIds),true);
    $seen=[]; $slots=[]; $out=[];
    foreach($positions as $p) {
        if(!is_array($p))throw new DomainException('Invalid cargo position.');
        $id=filter_var($p['id']??null,FILTER_VALIDATE_INT);
        $bay=filter_var($p['bay']??null,FILTER_VALIDATE_INT);
        $row=filter_var($p['row']??null,FILTER_VALIDATE_INT);
        $tier=filter_var($p['tier']??null,FILTER_VALIDATE_INT);
        if(!$id || !isset($members[$id]) || isset($seen[$id]))throw new DomainException('Cargo must belong to this manifest once.');
        if($bay===false || $bay<1 || $bay>100 || $row===false || $row<1 || $row>20 || $tier===false || $tier<1 || $tier>10)throw new DomainException('Use bays 1–100, rows 1–20 and tiers 1–10.');
        $slot="$bay-$row-$tier";
        if(isset($slots[$slot]))throw new DomainException('Two packages occupy the same position.');
        $seen[$id]=true;$slots[$slot]=true;
        $out[]=['id'=>$id,'bay'=>$bay,'row'=>$row,'tier'=>$tier];
    }
    return $out;
}
