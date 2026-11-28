<?php
/**
 * Small extra numbers for the phone support screens (views/sl/phone/Cs*.php) that the web $d does not carry.
 * Read-only, bound, same tables and conditions as helpers/support.php.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** Open urgent tickets (the "Urgent" segment of M-Desk). */
function sl_pdUrgentCount(): int
{
    if (!sl_tableExists('cdb_cs_tickets')) return 0;
    return (int) sl_scalar("SELECT COUNT(*) FROM cdb_cs_tickets WHERE disposition = 'none' AND status NOT IN ('resolved','closed') AND priority = 'urgent'");
}

/** Tickets per day for the last $days days (oldest first), for the M-CsReports bar chart. */
function sl_pdTicketsPerDay(int $days = 7): array
{
    $map = [];
    if (sl_tableExists('cdb_cs_tickets')) {
        foreach (sl_rows('SELECT DATE(created_at) AS d, COUNT(*) AS n FROM cdb_cs_tickets WHERE created_at >= :f GROUP BY d', [':f' => date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'))]) as $r) {
            $map[$r->d] = (int) $r->n;
        }
    }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));
        $out[] = ['l' => date('D', strtotime($day)), 'n' => $map[$day] ?? 0, 'now' => $i === 0];
    }
    return $out;
}

/** AI runs this month: total and handed to a person (M-CsAI hero). */
function sl_pdAiMonth(): array
{
    if (!sl_tableExists('cdb_cs_ai_runs')) return ['n' => 0, 'handoff' => 0];
    $r = sl_row("SELECT COUNT(*) AS n, SUM(outcome LIKE '%hand%') AS h FROM cdb_cs_ai_runs WHERE kind = 'king' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    return ['n' => (int) ($r->n ?? 0), 'handoff' => (int) ($r->h ?? 0)];
}
