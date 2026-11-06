<?php
/** Data for catalog.php: rows of one reference table with search, filter and paging. */
function sl_catalogData($user, string $key, array $cat): array
{
    $t = $cat['table'];
    $fields = $cat['fields'];
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = '1=1';
    $b = [];
    if ($q !== '') {
        $cols = $cat['search'] ?? array_keys(array_filter($fields, fn($f) => in_array($f[1], ['text', 'textarea'], true)));
        $parts = [];
        foreach ($cols as $i => $col) {
            $parts[] = "`$col` LIKE :q$i";
            $b[":q$i"] = '%' . $q . '%';
        }
        if ($parts) {
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
    }
    $filterCol = $cat['filter'] ?? null;
    $filterVal = $filterCol ? (int) ($_GET['f'] ?? 0) : 0;
    if ($filterCol && $filterVal > 0) {
        $where .= " AND `$filterCol` = :fv";
        $b[':fv'] = $filterVal;
    }
    $count = (int) sl_scalar("SELECT COUNT(*) FROM `$t` WHERE $where", $b);
    $order = $cat['order'];
    $rows = sl_rows("SELECT * FROM `$t` WHERE $where ORDER BY `$order` LIMIT " . (($page - 1) * $per) . ", $per", $b);
    $lookups = [];
    foreach ($fields as $col => $f) {
        if ($f[1] === 'lookup') {
            $ids = array_values(array_unique(array_filter(array_map(fn($r) => (int) ($r->$col ?? 0), $rows))));
            $lookups[$col] = [];
            if ($ids) {
                [$lt, $lv, $ll] = $f[3];
                foreach (sl_rows("SELECT `$lv` AS v, `$ll` AS l FROM `$lt` WHERE `$lv` IN (" . implode(',', $ids) . ")") as $r) {
                    $lookups[$col][$r->v] = $r->l;
                }
            }
        }
    }
    $filterOptions = [];
    if ($filterCol) {
        $filterOptions = sl_catalogLookup($fields[$filterCol][3], '', 400);
    }
    return [
        'key' => $key, 'cat' => $cat, 'rows' => $rows, 'count' => $count, 'page' => $page, 'per' => $per, 'q' => $q,
        'lookups' => $lookups, 'filterCol' => $filterCol, 'filterVal' => $filterVal, 'filterOptions' => $filterOptions,
        'canCreate' => $user->cdp_hasPermission($cat['perm']['create']),
        'canEdit' => $user->cdp_hasPermission($cat['perm']['edit']),
        'openNew' => !empty($_GET['new']), 'openEdit' => (int) ($_GET['edit'] ?? 0),
        'siblings' => array_filter(sl_catalogs(), fn($c) => $c['group'] === $cat['group'] && $user->cdp_hasPermission($c['perm']['view'])),
    ];
}
