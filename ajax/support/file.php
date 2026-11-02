<?php
// *************************************************************************
// * Customer Service — attachment download.                               *
// * GET t=<token>[&dl=1]. Staff reach any attachment; a customer reaches   *
// * their own uploads and files on public messages of their own chats and  *
// * tickets (helpers/support.php cdp_csFileCanRead).                       *
// *************************************************************************

require_once("../../loader.php");
require_once(__DIR__ . '/../../helpers/ajax_guard.php');
require_once(__DIR__ . '/../../helpers/support.php');
require_login();

$token = preg_replace('/[^a-f0-9]/', '', (string) ($_GET['t'] ?? ''));
$f = strlen($token) === 32 && cdp_csTablesExist() ? cdp_csFileCanRead($token, $user) : null;
$path = $f ? cdp_csFileDir() . '/' . basename($f->stored_name) : '';
if (!$f || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
}
$inline = empty($_GET['dl']) && strpos($f->mime, 'image/') === 0;
header('Content-Type: ' . $f->mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, max-age=300');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $f->original_name) . '"');
readfile($path);
