<?php
/**
 * TrackingHandler – public shipment / package tracking (no auth required).
 *
 * GET /api/v1/tracking/{order_no}
 *
 * Accepts full tracking numbers like "ABC-00001", "PKG-00005", or bare numbers.
 * Searches cdb_add_order, then cdb_customers_packages, then cdb_consolidate.
 */
class TrackingHandler
{
    /** Lookups allowed per client IP per minute (same budget as track.php). */
    const MAX_LOOKUPS_PER_MINUTE = 30;

    public function show(string $orderNo): void
    {
        // Security: tracking numbers are prefix + sequential number, so an
        // unthrottled public endpoint lets anyone enumerate every order.
        if ($this->throttled()) {
            header('Retry-After: 60');
            ApiResponse::error('too_many_requests', 'Too many tracking lookups. Please wait a minute and try again.', 429);
        }

        $orderNo = strtoupper(cdp_sanitize($orderNo));

        if (empty($orderNo)) {
            ApiResponse::badRequest('Tracking number is required.');
        }

        // ── 1. Standard shipment ───────────────────────────────────────────────
        $result = $this->findShipment($orderNo);
        if ($result !== null) {
            ApiResponse::success($result);
        }

        // ── 2. Customer package ───────────────────────────────────────────────
        $result = $this->findPackage($orderNo);
        if ($result !== null) {
            ApiResponse::success($result);
        }

        // ── 3. Consolidation ──────────────────────────────────────────────────
        $result = $this->findConsolidation($orderNo);
        if ($result !== null) {
            ApiResponse::success($result);
        }

        ApiResponse::notFound("No shipment found for tracking number: {$orderNo}");
    }

    /**
     * Per-IP fixed one-minute window kept in a small temp file (the API has no
     * session). Fails open if the temp directory is not writable.
     */
    private function throttled(): bool
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '') {
            return false;
        }
        $window = (int)floor(time() / 60);
        $file   = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/cdp_api_track_' . md5(__DIR__ . '|' . $ip);
        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return false;
        }
        $count = 0;
        if (flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            [$w, $c] = array_pad(explode(':', (string)$raw, 2), 2, '0');
            $count = ((int)$w === $window) ? (int)$c + 1 : 1;
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $window . ':' . $count);
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        return $count > self::MAX_LOOKUPS_PER_MINUTE;
    }

    private function findShipment(string $orderNo): ?array
    {
        $db = new Conexion();
        $db->cdp_query("
            SELECT a.*, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_add_order a
            INNER JOIN cdb_styles s ON a.status_courier = s.id
            WHERE CONCAT(a.order_prefix, a.order_no) = :on
              AND a.status_courier != 14
            LIMIT 1
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) return null;

        // Tracking events
        $db->cdp_query("
            SELECT ct.t_date, ct.comments, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_courier_track ct
            INNER JOIN cdb_styles s ON ct.status_courier = s.id
            WHERE ct.order_track = :on
            ORDER BY ct.id ASC
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $events = $db->cdp_registros();

        // Inside a consolidation the shipment reports the CONSOLIDATION's status
        // and ETA — the whole consolidation moves as one.
        $eff = cdp_getEffectiveStatus($row->order_no, $row->status_courier, $row->is_consolidate ?? null);

        return [
            'type'            => 'shipment',
            'tracking_number' => $orderNo,
            'status'          => $eff->status_id,
            'status_label'    => $eff->mod_style !== '' ? $eff->mod_style : $row->status_label,
            'status_color'    => $eff->color,
            'in_consolidation' => $eff->in_consolidation,
            'consolidation'   => $eff->in_consolidation ? $eff->consolidate_code : null,
            'consolidation_awb' => $eff->in_consolidation ? (cdp_consolidationAwb($eff->consolidate_code) ?: null) : null,
            'eta'             => cdp_getEffectiveEta((int)$row->order_id, $row->order_no, $row->order_deli_time ?? null, $row->is_consolidate ?? null),
            'order_date'      => $row->order_date ?? null,
            // total_order / due_date are invoice data; this endpoint is public.
            'is_pickup'       => (bool)(int)($row->is_pickup ?? 0),
            'events'          => $events ?: [],
        ];
    }

    private function findPackage(string $orderNo): ?array
    {
        $db = new Conexion();
        $db->cdp_query("
            SELECT a.*, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_customers_packages a
            INNER JOIN cdb_styles s ON a.status_courier = s.id
            WHERE CONCAT(a.order_prefix, a.order_no) = :on
            LIMIT 1
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) return null;

        $db->cdp_query("
            SELECT ct.t_date, ct.comments, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_courier_track ct
            INNER JOIN cdb_styles s ON ct.status_courier = s.id
            WHERE ct.order_track = :on
            ORDER BY ct.id ASC
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $events = $db->cdp_registros();

        // Inside a consolidation the package reports the CONSOLIDATION's status
        // and ETA — the whole consolidation moves as one.
        $eff = cdp_getEffectiveStatus($row->order_no, $row->status_courier, $row->is_consolidate ?? null, true);

        return [
            'type'            => 'package',
            'tracking_number' => $orderNo,
            'status'          => $eff->status_id,
            'status_label'    => $eff->mod_style !== '' ? $eff->mod_style : $row->status_label,
            'status_color'    => $eff->color,
            'in_consolidation' => $eff->in_consolidation,
            'consolidation'   => $eff->in_consolidation ? $eff->consolidate_code : null,
            'consolidation_awb' => $eff->in_consolidation ? (cdp_consolidationAwb($eff->consolidate_code) ?: null) : null,
            'eta'             => cdp_getEffectiveEta((int)$row->order_id, $row->order_no, $row->order_deli_time ?? null, $row->is_consolidate ?? null, true),
            'order_date'      => $row->order_date ?? null,
            'events'          => $events ?: [],
        ];
    }

    private function findConsolidation(string $orderNo): ?array
    {
        $db = new Conexion();
        $db->cdp_query("
            SELECT c.*, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_consolidate c
            INNER JOIN cdb_styles s ON c.status_courier = s.id
            WHERE CONCAT(c.c_prefix, c.c_no) = :on
            LIMIT 1
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $row = $db->cdp_registro();

        if (!$row) return null;

        $db->cdp_query("
            SELECT ct.t_date, ct.comments, s.mod_style AS status_label, s.color AS status_color
            FROM cdb_courier_track ct
            INNER JOIN cdb_styles s ON ct.status_courier = s.id
            WHERE ct.order_track = :on
            ORDER BY ct.id ASC
        ");
        $db->bind(':on', $orderNo);
        $db->cdp_execute();
        $events = $db->cdp_registros();

        return [
            'type'            => 'consolidation',
            'tracking_number' => $orderNo,
            'awb'             => cdp_consolidationAwb($row) ?: null,
            'status'          => (int)$row->status_courier,
            'status_label'    => $row->status_label,
            'status_color'    => $row->status_color,
            'order_date'      => $row->c_date ?? null,
            'events'          => $events ?: [],
        ];
    }
}
