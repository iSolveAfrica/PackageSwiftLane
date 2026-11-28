<?php
/**
 * The illustration for where a parcel is now (the owner's drawings in assets/swiftlane/cargo/stage/,
 * used exactly as supplied). Air parcels only ever get the air drawings and sea parcels only the sea
 * drawings; ready for pickup and handed over show the Swift courier with the parcel.
 *   air: loading at the warehouse, in the air, landed in Accra
 *   sea: packing the container, at the quay, at sea, unloading at the port, container yard, unpacking
 * Rendered by views/sl/pages/partials/parcel_scene.php.
 */
require_once __DIR__ . '/track_progress.php';
require_once __DIR__ . '/air_journey.php';

function sl_parcelScene(string $mode, int $statusId, string $statusName): array
{
    $dir = 'assets/swiftlane/cargo/stage/';
    $name = str_replace('_', ' ', $statusName);
    $courier = ['img' => $dir . 'courier-ready.png', 'agent' => true, 'mode' => $mode];
    if (in_array($statusId, [6, 32], true)) {
        return $courier + ['title' => 'Ready for pickup', 'text' => 'Your parcel is in Accra and ready to hand over.'];
    }
    if (function_exists('cdp_isHandedOver') ? cdp_isHandedOver($statusId) : in_array($statusId, [8, 15], true)) {
        return $courier + ['title' => 'Handed over', 'text' => 'Delivered to the customer by the Swift team.'];
    }
    if ($mode === 'sea') {
        $i = (int) (cdp_trackProgress($statusId, $name)['index'] ?? 0);
        $sea = [
            ['sea-1-packing', 'Packing the container', 'Checked in and packed into a container for the next sailing.'],
            ['sea-2-at-quay', 'Booked on a ship', 'The container is at the quay, waiting to be loaded.'],
            ['sea-3-at-sea', 'On the water', 'Sailing towards Ghana.'],
            ['sea-4-unloading', 'Unloading at the port', 'Arrived at the port of entry and clearing customs.'],
            ['sea-5-container-yard', 'At the container yard', 'Off the ship and on its way to the Accra office.'],
            ['sea-6-emptying', 'Unpacking the container', 'Being unpacked and sorted at the Accra office.'],
        ];
        [$f, $t, $x] = $sea[max(0, min($i, 5))];
        return ['img' => $dir . $f . '.png', 'title' => $t, 'text' => $x, 'agent' => false, 'mode' => 'sea'];
    }
    $a = cdp_airStageIndex($statusId, $name) ?? 0;
    if ($a >= 10) return $courier + ['title' => 'Ready for pickup', 'text' => 'Your parcel is in Accra and ready to hand over.'];
    if ($a >= 7) return ['img' => $dir . 'air-3-unloading.png', 'title' => 'Landed in Accra', 'text' => 'Off the plane, through customs and into sorting.', 'agent' => false, 'mode' => 'air'];
    if ($a >= 5) return ['img' => $dir . 'air-2-in-flight.png', 'title' => 'In the air', 'text' => 'Flying to Accra.', 'agent' => false, 'mode' => 'air'];
    return ['img' => $dir . 'air-1-loading.png', 'title' => 'At the warehouse', 'text' => 'Checked in, weighed and being loaded for the next flight.', 'agent' => false, 'mode' => 'air'];
}
