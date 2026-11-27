<?php
/** Registration cohorts and scoped details for the sea control tower. */
function sl_seaTowerData($user, $core, array $packages): array
{
    $months = (int) ($_GET['months'] ?? 6);
    if (!in_array($months, [3,6,12], true)) $months = 6;
    $start = (new DateTimeImmutable('first day of this month'))->modify('-'.($months-1).' months');
    $scope = sl_opsScope('a');
    $bind = [':start'=>$start->format('Y-m-01'), ':end'=>date('Y-m-d',strtotime('tomorrow'))];
    $period = "a.order_date >= :start AND a.order_date < :end $scope";
    $keys = $labels = [];
    for ($i=0; $i<$months; $i++) {
        $date = $start->modify("+$i months");
        $keys[] = $date->format('Y-m');
        $labels[] = $date->format('M Y');
    }
    $groups = sl_rows("SELECT DATE_FORMAT(a.order_date,'%Y-%m') AS month_id, COUNT(*) AS registered,
        SUM(CASE WHEN a.is_prealert=1 THEN 1 ELSE 0 END) AS alerted
        FROM cdb_customers_packages a WHERE $period GROUP BY month_id", $bind);
    $series = static function ($rows,$field,$name,$color) use ($keys) {
        $values = array_fill_keys($keys,0);
        foreach ($rows as $row) $values[$row->month_id] = (int)$row->$field;
        return ['name'=>$name,'color'=>$color,'values'=>array_values($values)];
    };
    $sea = $series($groups,'registered','Sea','#21877c');
    $modes = [$sea];
    foreach ([['view_shipment_list',0,'Air','#5d82b7'],['view_pickup_list',1,'Home pickups','#a38355']] as [$permission,$pickup,$name,$color]) {
        if (!$user->cdp_hasPermission($permission)) continue;
        $rows = sl_rows("SELECT DATE_FORMAT(a.order_date,'%Y-%m') AS month_id, COUNT(*) AS registered
            FROM cdb_add_order a WHERE a.is_deleted=0 AND a.is_pickup=$pickup AND $period GROUP BY month_id", $bind);
        $modes[] = $series($rows,'registered',$name,$color);
    }
    $charts = [
        ['title'=>'Registrations & pre-alerts','series'=>[$series($groups,'registered','Registered','#518ac6'),$series($groups,'alerted','With pre-alert','#21877c')]],
        ['title'=>'Shipment volume by service','series'=>$modes]
    ];
    $selected = (int)($_GET['cargo'] ?? 0);
    $ids = array_column($packages['rows'],'id');
    if (!in_array($selected,array_map('intval',$ids),true)) $selected = $ids[0] ?? 0;
    $detail = $selected && $packages['can']['view'] ? sl_packageDetailData($user,$core,$selected) : null;
    $place = $detail ? sl_row("SELECT sender_city AS city,sender_country AS country FROM cdb_address_shipments
        WHERE order_track=:track ORDER BY id DESC LIMIT 1", [':track'=>$detail['trk']]) : null;
    return compact('months','labels','charts','selected','detail','place');
}
