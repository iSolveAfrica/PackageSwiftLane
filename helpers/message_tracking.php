<?php
/**
 * Message engagement tracking for campaigns (e-mail opens and clicks).
 *
 * Every campaign e-mail gets a 1×1 image and its links rewritten through msg_track.php. Both carry the
 * campaign batch id, the recipient and an HMAC (newsletter secret), so nobody can forge events or use
 * the redirect for other sites. Events land in cdb_message_events and are read by the Campaigns page
 * (views/sl/data/campaigns.php). SMS and WhatsApp delivery comes from cdb_message_log statuses.
 * Note: image blocking and Apple Mail privacy make opens approximate; clicks are reliable.
 */
require_once __DIR__ . '/newsletter.php';

function cdp_mtReady(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $db = new Conexion;
        $db->cdp_query("CREATE TABLE IF NOT EXISTS cdb_message_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, batch_id VARCHAR(40) NOT NULL, recipient_to VARCHAR(190) NOT NULL,
            channel VARCHAR(12) NOT NULL DEFAULT 'email', event VARCHAR(16) NOT NULL, url VARCHAR(500) NULL,
            ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, created_at DATETIME NOT NULL,
            PRIMARY KEY (id), KEY idx_batch (batch_id, event), KEY idx_recipient (recipient_to, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ok = (bool) $db->cdp_execute();
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function cdp_mtB64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function cdp_mtUnB64(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/')); }

function cdp_mtSig(string $event, string $batch, string $to, string $url = ''): string
{
    $secret = cdp_nlSecret();
    return $secret === '' ? '' : substr(hash_hmac('sha256', "mt|$event|$batch|" . strtolower($to) . "|$url", $secret), 0, 24);
}

/** Add the open pixel and wrap every http(s) link (not the unsubscribe link) for click tracking. */
function cdp_mtInject(string $html, string $batch, string $to, string $siteUrl, string $unsubUrl = ''): string
{
    if ($batch === '' || cdp_nlSecret() === '' || !cdp_mtReady()) return $html;
    $base = rtrim($siteUrl, '/') . '/msg_track.php';
    $b = rawurlencode($batch);
    $r = cdp_mtB64(strtolower($to));
    $html = preg_replace_callback('/href\s*=\s*"(https?:\/\/[^"]+)"/i', function ($m) use ($base, $b, $r, $batch, $to, $unsubUrl) {
        $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        if ($unsubUrl !== '' && strpos($url, 'newsletter_unsubscribe.php') !== false) return $m[0];
        if (strpos($url, '/msg_track.php') !== false) return $m[0];
        $u = cdp_mtB64($url);
        return 'href="' . $base . '?e=click&b=' . $b . '&r=' . $r . '&u=' . $u . '&s=' . cdp_mtSig('click', $batch, $to, $url) . '"';
    }, $html);
    $pixel = '<img src="' . $base . '?e=open&b=' . $b . '&r=' . $r . '&s=' . cdp_mtSig('open', $batch, $to) . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">';
    return stripos($html, '</body>') !== false ? preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1) : $html . $pixel;
}

function cdp_mtRecord(string $event, string $batch, string $to, string $url = '', string $channel = 'email'): void
{
    if (!cdp_mtReady()) return;
    try {
        $db = new Conexion;
        $db->cdp_query('INSERT INTO cdb_message_events (batch_id, recipient_to, channel, event, url, ip, user_agent, created_at) VALUES (:b, :t, :c, :e, :u, :ip, :ua, NOW())');
        $db->bind(':b', mb_substr($batch, 0, 40));
        $db->bind(':t', mb_substr(strtolower($to), 0, 190));
        $db->bind(':c', $channel);
        $db->bind(':e', $event);
        $db->bind(':u', $url !== '' ? mb_substr($url, 0, 500) : null);
        $db->bind(':ip', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $db->bind(':ua', mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));
        $db->cdp_execute();
    } catch (Throwable $e) {
        // tracking never breaks the redirect or the image
    }
}
