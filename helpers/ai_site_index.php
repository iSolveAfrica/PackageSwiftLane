<?php
/**
 * AI site index: everything a person can open in Swift Lane, with what it is for, plus the knowledge
 * the assistant has been taught and published help articles. Built from the live menu
 * (helpers/sl_nav.php), the module catalogue of Control panels (views/sl/data/control_panels.php) and
 * the legacy page catalogue (cdp_aiPageCatalogue), so it never drifts from what is actually routed.
 * Every entry is filtered by the asking user's permissions. Used by the list_pages and search_site tools.
 */

if (!function_exists('cdp_aiSiteIndex')) {

    /** Pages the user may open: [title, group, url, purpose]. Cached per request. */
    function cdp_aiSiteIndex($user): array
    {
        static $cache = [];
        $uid = (int) ($_SESSION['userid'] ?? 0);
        if (isset($cache[$uid])) return $cache[$uid];
        $root = defined('CDP_ROOT') ? CDP_ROOT : dirname(__DIR__);
        require_once $root . '/helpers/sl_view.php';
        require_once $root . '/helpers/sl_nav.php';
        $pages = [];
        $add = function (string $title, string $group, string $url, string $purpose) use (&$pages) {
            $key = strtolower(strtok($url, '#'));
            if ($url === '' || $url === '#') return;
            if (isset($pages[$key])) {
                if ($purpose !== '' && mb_strlen($purpose) > mb_strlen($pages[$key]['purpose'])) $pages[$key]['purpose'] = $purpose;
                return;
            }
            $pages[$key] = ['title' => $title, 'group' => $group, 'url' => $url, 'purpose' => $purpose];
        };
        $describe = function () use (&$pages, &$aboutUrl) {
            foreach ($pages as $k => $p) if (isset($aboutUrl[$k])) $pages[$k]['purpose'] = $aboutUrl[$k] . ($p['purpose'] !== '' && !str_contains($aboutUrl[$k], $p['purpose']) ? ' ' . $p['purpose'] : '');
        };
        // what the newer pages are for (keyed by route), so search finds them by meaning, not only by name
        $about = [
            'LiveTracking' => 'Live map of every air shipment, sea package and consolidation: where it is, late or on time, ETA, details, contents and actions. Customers see their own parcels.',
            'LockerSearch' => 'Smart Search: search anything (tracking or store number, locker, customer, phone, container, ticket, staff, page) and open a customer card with open parcels and virtual mailbox.',
            'Campaigns' => 'E-mail, SMS and WhatsApp campaigns: sent, delivered, opened, clicked, failed and cost per campaign.',
            'Documents' => 'Every invoice, receipt, label, manifest and export printed or downloaded, with search by tracking, customer, person and date.',
            'ReceiptStudio' => 'Receipt layout: choose which sections the package receipt shows (logo, barcode, items, charges, QR code...), drag them into order, live preview.',
            'LabelStudio' => 'Label options and printer settings (paper size, alignment, darkness, logo, test print) for shipping and small labels.',
            'EmailBuilder' => 'Design and send newsletters and e-mail campaigns to customers, with recipients, cost and results.',
            'MessageCosts' => 'Prices per SMS, WhatsApp and e-mail, and what messaging costs over time.',
            'MessageLogs' => 'Every SMS, WhatsApp and e-mail sent, skipped or failed, with the reason.',
            'ControlPanels' => 'Every module grouped by area, queues that need attention and workspace health.',
            'Main' => 'Overview: your productivity, today\'s parcels, deliveries, customer service, AI, freight trend, Ghana destinations and the daily briefing.',
            'ShipmentTracking' => 'Tracking for one shipment: journey timeline, add tracking events.',
            'HandOut' => 'Hand parcels to customers at the counter: ready parcels, payment and pickup codes.',
            'StaffAI' => 'Chat with King, with saved conversations.',
        ];
        $aboutUrl = [];
        foreach ($about as $board => $text) $aboutUrl[strtolower(strtok(sl_route($board), '#'))] = $text;
        $client = function_exists('sl_userIs') && sl_userIs('client');
        // 1) the menu the person actually sees
        foreach ($client ? sl_navCustomer() : sl_navStaff() as $g) {
            foreach ($g['items'] as $it) {
                if (!sl_navAllowed($it['perm'] ?? null, [])) continue;
                $add($it['label'], ucfirst(strtolower($g['label'])), $it['href'] ?? sl_route($it['board']), '');
            }
        }
        // 2) module descriptions from Control panels (staff)
        if (!$client) {
            require_once $root . '/views/sl/data/control_panels.php';
            if (function_exists('sl_controlPanelSections')) {
                foreach (sl_controlPanelSections(fn() => '') as $s) {
                    foreach ($s['rows'] as [$board, $icon, $label, $sub, $perm, $count]) {
                        if ($perm !== null && !sl_navAllowed($perm, [])) continue;
                        $add($label, $s['t'], sl_route($board), $sub . ($s['s'] ? ' (' . $s['s'] . ')' : ''));
                    }
                }
            }
        }
        // 3) the older catalogue: pages that are not in the menu but still open (details, add forms)
        if (function_exists('cdp_aiPageCatalogue')) {
            foreach (cdp_aiPageCatalogue() as [$url, $title, $purpose, $perm]) {
                if ($perm !== '' && !$user->cdp_hasPermission($perm)) continue;
                if (!is_file($root . '/' . strtok($url, '?'))) continue;
                $add($title, 'Pages', $url, $purpose);
            }
        }
        $describe();
        return $cache[$uid] = array_values($pages);
    }

    /** Taught facts (cdb_ai_knowledge) and published help articles (cdb_cs_kb) matching the words. */
    function cdp_aiSiteKnowledge(array $words, bool $client): array
    {
        $out = [];
        if (function_exists('cdp_aiKnowledge')) {
            foreach (cdp_aiKnowledge(true) as $k) {
                $note = (string) ($k['note'] ?? '');
                if (cdp_aiSiteScore($words, $note, '') > 0) $out[] = ['type' => 'fact', 'text' => mb_substr($note, 0, 400)];
            }
        }
        if (function_exists('sl_tableExists') && sl_tableExists('cdb_cs_kb') && $words) {
            $aud = $client ? "audience IN ('customer','all')" : '1 = 1';
            $like = [];
            $bind = [];
            foreach (array_slice($words, 0, 5) as $i => $w) {
                $like[] = "(title LIKE :t$i OR body LIKE :b$i)";
                $bind[":t$i"] = "%$w%";
                $bind[":b$i"] = "%$w%";
            }
            foreach (sl_rows("SELECT id, title, body FROM cdb_cs_kb WHERE status = 'published' AND $aud AND (" . implode(' OR ', $like) . ') ORDER BY helpful DESC, updated_at DESC LIMIT 5', $bind) as $a) {
                $out[] = ['type' => 'article', 'title' => (string) $a->title, 'text' => mb_substr(trim(strip_tags((string) $a->body)), 0, 600)];
            }
        }
        return array_slice($out, 0, 8);
    }

    function cdp_aiSiteWords(string $q): array
    {
        $stop = ['the', 'a', 'an', 'to', 'of', 'and', 'or', 'in', 'on', 'for', 'is', 'are', 'i', 'my', 'me', 'how', 'do', 'can', 'where', 'what', 'which', 'page', 'see', 'find', 'show', 'with', 'about', 'this', 'that', 'it'];
        $w = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_filter($w, fn($x) => mb_strlen($x) > 1 && !in_array($x, $stop, true))));
    }

    function cdp_aiSiteScore(array $words, string $title, string $rest): int
    {
        $t = mb_strtolower($title);
        $r = mb_strtolower($rest);
        $s = 0;
        foreach ($words as $w) {
            $stem = mb_strlen($w) > 4 ? rtrim($w, 's') : $w;
            if (str_contains($t, $stem)) $s += 3;
            elseif (str_contains($r, $stem)) $s += 1;
        }
        return $s;
    }

    /** The search_site tool: pages, taught facts and help articles that match the question. */
    function cdp_aiToolSearchSite(array $a, $ctx): array
    {
        $q = trim((string) ($a['query'] ?? ''));
        $words = cdp_aiSiteWords($q);
        $pages = [];
        foreach (cdp_aiSiteIndex($ctx['user']) as $p) {
            $score = cdp_aiSiteScore($words, $p['title'], $p['group'] . ' ' . $p['purpose'] . ' ' . $p['url']);
            if ($score > 0) $pages[] = $p + ['score' => $score];
        }
        usort($pages, fn($x, $y) => $y['score'] <=> $x['score']);
        $client = function_exists('sl_userIs') && sl_userIs('client');
        return ['query' => $q, 'pages' => array_map(function ($p) { unset($p['score']); return $p; }, array_slice($pages, 0, 10)),
            'knowledge' => cdp_aiSiteKnowledge($words, $client),
            'note' => $pages ? 'Link pages with their url. Only these pages are open to this user.' : 'No page matched; try other words or list_pages.'];
    }
}
