<?php
/** Additive delivery storage setup, limited to signed-in administrators. */
function dl_ensureStorage(): bool
{
    global $user;
    static $result=null;
    if($result!==null)return $result;
    $tables=['cdb_delivery_config','cdb_delivery_zone_rules','cdb_delivery_requests','cdb_delivery_items','cdb_delivery_claims','cdb_delivery_events','cdb_delivery_outbox','cdb_delivery_zones','cdb_rider_positions','cdb_consolidation_load_plans'];
    $db=(new Conexion())->dbh;
    $in=implode(',',array_fill(0,count($tables),'?'));
    $count=$db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($in)");
    $count->execute($tables);
    if((int)$count->fetchColumn()===count($tables))return $result=true;
    if(!is_object($user)||empty($user->logged_in)||!$user->cdp_is_Admin()||CDP_APP_MODE_DEMO)return $result=false;
    try {
        foreach(['2026_11_06_freight_devops.sql','2026_11_11_courier_delivery.sql','2026_11_27_rider_positions.sql','2026_11_27_consolidation_load_plans.sql'] as $file){
            $sql=(string)file_get_contents(CDP_ROOT.'/migrations/'.$file);
            foreach(dl_deliverySchemaStatements($sql,$tables) as $statement){
                if($db->exec(trim($statement))===false)throw new RuntimeException('Could not create delivery table');
            }
        }
        $count->execute($tables);
        return $result=(int)$count->fetchColumn()===count($tables);
    } catch(Throwable $e){
        error_log('[delivery-storage] '.$e->getMessage());
        return $result=false;
    }
}

/** Extract only additive table definitions from the fixed migration files. */
function dl_deliverySchemaStatements(string $sql,array $tables): array
{
    $sql=preg_replace('/--[^\r\n]*/','',$sql);
    preg_match_all('/CREATE TABLE IF NOT EXISTS\s+(cdb_[a-z0-9_]+)\s*\([^;]*;/i',$sql,$matches,PREG_SET_ORDER);
    $out=[];
    foreach($matches as $statement)if(in_array($statement[1],$tables,true))$out[$statement[1]]=$statement[0];
    return $out;
}
