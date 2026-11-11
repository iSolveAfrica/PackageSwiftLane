<?php
/** Data for the Notifications page (Notifications.dc.html): cdb_notifications_users joined to cdb_notifications. */
require_once CDP_ROOT . '/helpers/sl_view.php';

function sl_notificationPrefEvents(): array
{
    return [
        'prealert' => 'New pre-alert', 'received' => 'Package received at warehouse', 'status' => 'Shipment status updates',
        'payment' => 'Payment received', 'overdue' => 'Overdue invoice', 'login' => 'Failed sign-in',
    ];
}

function sl_notificationsData($user, $core): array
{
    $uid = (int) $_SESSION['userid'];
    $filter = (string) ($_GET['filter'] ?? 'all');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per = 25;
    $where = 'a.user_id = :u';
    if ($filter === 'unread') {
        $where .= ' AND a.notification_read = 0';
    } elseif ($filter === 'shipments') {
        $where .= ' AND b.order_id > 0';
    }
    $binds = [':u' => $uid];
    $base = 'FROM cdb_notifications_users a INNER JOIN cdb_notifications b ON a.notification_id = b.notification_id WHERE ' . $where;
    $count = (int) sl_scalar("SELECT COUNT(*) $base", $binds);
    $unread = (int) sl_scalar('SELECT COUNT(*) FROM cdb_notifications_users WHERE user_id = :u AND notification_read = 0', [':u' => $uid]);
    $rows = sl_rows("SELECT a.id_notifi_user, a.notification_read, b.notification_id, b.notification_description, b.notification_date, b.order_id, b.shipping_type $base ORDER BY b.notification_id DESC LIMIT " . (($page - 1) * $per) . ", $per", $binds);
    $items = [];
    foreach ($rows as $r) {
        $type = (int) $r->shipping_type;
        $href = '';
        if ((int) $r->order_id > 0) {
            $href = $type === 99 ? sl_route(sl_userIs('driver') ? 'DeliveryJob' : (sl_userIs('client') ? 'DeliveryRequest' : 'DeliveryDispatch'), ['id'=>(int)$r->order_id]) : ($type === 4 ? sl_route('PackageDetail', ['id' => (int) $r->order_id]) : sl_route('ShipmentDetail', ['id' => (int) $r->order_id]));
        }
        $items[] = ['id' => (int) $r->notification_id, 'ic' => $type === 4 ? 'i-ship' : ($type === 1 ? 'i-plane' : 'i-bell'), 't' => (string) $r->notification_description,
            'd' => $href ? ($type === 99 ? 'Delivery' : ($type === 4 ? 'Sea package' : 'Air shipment')) . ' #' . (int) $r->order_id : 'System', 'w' => sl_ago($r->notification_date), 'c' => (int) $r->notification_read ? '' : 'g', 'read' => (int) $r->notification_read === 1, 'href' => $href];
    }
    $prefs = [];
    $hasPrefs = sl_tableExists('cdb_user_notification_prefs');
    if ($hasPrefs) {
        foreach (sl_rows('SELECT channel, event_key, enabled FROM cdb_user_notification_prefs WHERE user_id = :u', [':u' => $uid]) as $r) {
            $prefs[$r->channel][$r->event_key] = (int) $r->enabled === 1;
        }
    }
    $events = [];
    foreach (sl_notificationPrefEvents() as $k => $label) {
        $events[] = ['key' => $k, 'label' => $label, 'on' => $prefs['inapp'][$k] ?? true];
    }
    $channels = [];
    foreach (['email' => 'Email', 'inapp' => 'In-app', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $k => $label) {
        $channels[] = ['key' => $k, 'label' => $label, 'on' => $prefs[$k]['all'] ?? ($k !== 'sms')];
    }
    return ['items' => $items, 'count' => $count, 'page' => $page, 'per' => $per, 'unread' => $unread, 'filter' => $filter, 'events' => $events, 'channels' => $channels, 'hasPrefs' => $hasPrefs,
        'filters' => [['f' => 'all', 'label' => 'All'], ['f' => 'unread', 'label' => 'Unread'], ['f' => 'shipments', 'label' => 'Shipments']]];
}
