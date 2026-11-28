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
    // Sea only on the sea control tower: no air shipments and no home pickups here.
    $modes = [$series($groups,'registered','Sea packages','#21877c')];
    // Sea-freight shipments booked in the shipments table (category Ocean / Sea freight).
    $seaCats = [];
    foreach (sl_rows('SELECT id, name_item FROM cdb_category') as $c) {
        if (preg_match('/ocean|\bsea\b|mar[ií]tim|barco|vessel/u', mb_strtolower((string) $c->name_item, 'UTF-8'))) $seaCats[] = (int) $c->id;
    }
    if ($seaCats && $user->cdp_hasPermission(['view_shipment_list', 'view_general_shipments'])) {
        $rows = sl_rows("SELECT DATE_FORMAT(a.order_date,'%Y-%m') AS month_id, COUNT(*) AS registered
            FROM cdb_add_order a WHERE a.is_deleted=0 AND a.order_item_category IN (" . implode(',', $seaCats) . ") AND $period GROUP BY month_id", $bind);
        $modes[] = $series($rows,'registered','Sea-freight shipments','#3f6fa8');
    }
    // Containers (sea consolidations) opened per month.
    if ($user->cdp_hasPermission('view_consolidate_package_list') && sl_tableExists('cdb_consolidate_packages')) {
        $rows = sl_rows("SELECT DATE_FORMAT(a.c_date,'%Y-%m') AS month_id, COUNT(*) AS registered
            FROM cdb_consolidate_packages a WHERE a.c_date >= :start AND a.c_date < :end $scope GROUP BY month_id", $bind);
        $modes[] = $series($rows,'registered','Containers','#c98a2b');
    }
    $charts = [
        ['title'=>'Registrations & pre-alerts','series'=>[$series($groups,'registered','Registered','#518ac6'),$series($groups,'alerted','With pre-alert','#21877c')]],
        ['title'=>'Sea volume by type','series'=>$modes]
    ];
    $selected = (int)($_GET['cargo'] ?? 0);
    $ids = array_column($packages['rows'],'id');
    if (!in_array($selected,array_map('intval',$ids),true)) $selected = $ids[0] ?? 0;
    $detail = $selected && $packages['can']['view'] ? sl_packageDetailData($user,$core,$selected) : null;
    $place = $detail ? sl_row("SELECT sender_city AS city,sender_country AS country FROM cdb_address_shipments
        WHERE order_track=:track ORDER BY id DESC LIMIT 1", [':track'=>$detail['trk']]) : null;
    return compact('months','labels','charts','selected','detail','place');
}
