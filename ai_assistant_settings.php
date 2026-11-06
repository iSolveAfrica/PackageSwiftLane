<?php
/** Retired: the AI assistant settings now live in the AI centre (ai_center.php). */
require_once 'loader.php';
require_once 'helpers/sl_routes.php';
header('Location: ' . sl_route('AiCenter'), true, 301);
exit;
