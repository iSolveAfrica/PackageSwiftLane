<?php
/** Read-only owner, search and pagination checks for the unified customer list. */
require_once __DIR__.'/../loader.php';require_once CDP_ROOT.'/views/sl/data/portal.php';
if(!preg_match('/test|audit/i',CDP_DB_NAME)){fwrite(STDERR,"Use a disposable test/audit database\n");exit(1);}
$core=new Core();$owners=sl_rows('SELECT DISTINCT sender_id FROM cdb_add_order WHERE sender_id>0 ORDER BY sender_id LIMIT 2');
if(!$owners)throw new RuntimeException('Seed test customers and parcels first');
foreach($owners as $owner){$id=(int)$owner->sender_id;
foreach(['all','active','ready','delivered'] as $filter){$rows=sl_portalShipments($id,10000,0,'',$filter);$count=sl_portalCount($id,'',$filter);if(count($rows)!==$count)throw new RuntimeException('List/count disagree for '.$filter);
 foreach($rows as $row){$table=$row['src']==='air'?'cdb_add_order':'cdb_customers_packages';$actual=(int)sl_scalar("SELECT sender_id FROM $table WHERE order_id=:id",[':id'=>$row['oid']]);if($actual!==$id)throw new RuntimeException('Foreign customer parcel leaked');}
 $page=sl_portalShipments($id,2,0,'',$filter);if(array_column($page,'id')!==array_column(array_slice($rows,0,2),'id'))throw new RuntimeException('Unstable page ordering');
}
 if(sl_portalCount($id,"' OR 1=1 --")!==0||sl_portalShipments($id,12,0,"' OR 1=1 --"))throw new RuntimeException('Search must stay a bound literal');
}
echo "Customer parcel checks passed: air/sea owner scope, filters, counts, ordering and bound search.\n";
