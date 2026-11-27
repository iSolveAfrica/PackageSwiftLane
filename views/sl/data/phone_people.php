<?php
/**
 * Small helpers for the phone screens of the people and finance pages (views/sl/phone/*, by ph-people).
 * Only reads what the matching web page already shows; every query is scoped like the web page.
 */
require_once CDP_ROOT . '/helpers/sl_view.php';

/** Previous / next links for a paged phone list (same "page" parameter as the web table). */
function sl_ppPager(int $page, int $per, int $count): string
{
    $pages = max(1, (int) ceil($count / max(1, $per)));
    if ($pages <= 1) return '';
    $prev = $page > 1 ? '<a href="' . h(sl_url(['page' => $page - 1 > 1 ? $page - 1 : null])) . '" aria-label="Previous page"><i class="ic ic-sm i-left" aria-hidden="true"></i>Previous</a>' : '<span class="pp-off" aria-hidden="true">Previous</span>';
    $next = $page < $pages ? '<a href="' . h(sl_url(['page' => $page + 1])) . '" aria-label="Next page">Next<i class="ic ic-sm i-right" aria-hidden="true"></i></a>' : '<span class="pp-off" aria-hidden="true">Next</span>';
    return '<nav class="pp-pg" aria-label="Pages">' . $prev . '<span>Page ' . $page . ' of ' . $pages . '</span>' . $next . '</nav>';
}

/**
 * Staff & branches, phone: today's shifts of one branch (helpers/staffops.php, scoped by cdp_staffScopeSql
 * inside cdp_staffShifts, the same as the Shifts tab of the web page).
 */
function sl_ppStaffHub(int $branchId): array
{
    $branches = [];
    foreach (cdp_staffBranches(true) as $b) $branches[] = ['id' => (int) $b->id, 'name' => (string) $b->name, 'n' => (int) ($b->staff_count ?? 0)];
    if ($branchId && !in_array($branchId, array_column($branches, 'id'), true)) $branchId = 0;
    $today = date('Y-m-d');
    $rows = [];
    $on = 0; $late = 0; $total = 0;
    foreach (cdp_staffShifts($today, $today, $branchId) as $s) {
        $total++;
        $name = trim((string) $s->staff_name) ?: 'Staff';
        $lateMin = $s->check_in_at ? (int) floor((strtotime((string) $s->check_in_at) - strtotime($today . ' ' . $s->start_time)) / 60) : 0;
        if ($s->status === 'checked_in') $on++;
        if ($s->check_in_at && $lateMin > 0) $late++;
        if ($s->status === 'checked_in') $pill = $lateMin > 0 ? ['Late ' . $lateMin . ' min', 'pending'] : ['On', 'delivered'];
        elseif ($s->status === 'completed') $pill = ['Done', 'plain'];
        elseif ($s->status === 'scheduled') $pill = strtotime($today . ' ' . $s->start_time) < time() ? ['Not in', 'overdue'] : ['Planned', 'plain'];
        else $pill = [ucfirst((string) $s->status), 'plain'];
        $when = $s->check_in_at ? 'In ' . date('H:i', strtotime((string) $s->check_in_at)) : substr((string) $s->start_time, 0, 5) . '–' . substr((string) $s->end_time, 0, 5);
        $rows[] = ['name' => $name, 'ini' => sl_initials($name), 'sub' => $when . ($s->note ? ' · ' . $s->note : ($branchId ? '' : ' · ' . ($s->branch_name ?: 'No branch'))), 'pill' => $pill];
    }
    return ['branches' => $branches, 'branch' => $branchId, 'rows' => $rows, 'on' => $on, 'late' => $late, 'total' => $total];
}
