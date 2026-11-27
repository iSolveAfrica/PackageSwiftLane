<?php
require_once __DIR__.'/../helpers/delivery_storage.php';
$expected=['cdb_delivery_config','cdb_delivery_zone_rules','cdb_delivery_requests','cdb_delivery_items','cdb_delivery_claims','cdb_delivery_events','cdb_delivery_outbox','cdb_delivery_zones','cdb_rider_positions','cdb_consolidation_load_plans'];
$found=[];
foreach(['2026_11_06_freight_devops.sql','2026_11_11_courier_delivery.sql','2026_11_27_rider_positions.sql','2026_11_27_consolidation_load_plans.sql'] as $file){
 $sql=file_get_contents(__DIR__.'/../migrations/'.$file);
 $found+=dl_deliverySchemaStatements($sql,$expected);
}
sort($expected);$actual=array_keys($found);sort($actual);
if($actual!==$expected)throw new RuntimeException('Delivery setup misses required tables');
foreach($found as $statement)if(!preg_match('/^CREATE TABLE IF NOT EXISTS/i',$statement))throw new RuntimeException('Non-additive statement included');
echo "Delivery schema definitions passed\n";
