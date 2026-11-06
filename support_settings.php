<?php
/** Legacy Customer Service settings: split into support_channels.php, support_rules.php, support_playbook.php and support_ai.php. */
require_once 'loader.php';
require_once 'helpers/sl_routes.php';
$tab = (string) ($_GET['tab'] ?? '');
$map = ['rules' => 'CsRules', 'automation' => 'CsRules', 'canned' => 'CsPlaybook', 'kb' => 'CsPlaybook', 'king' => 'CsAI', 'ai' => 'CsAI'];
header('Location: ' . sl_route($map[$tab] ?? 'CsChannels'), true, 302);
exit;
