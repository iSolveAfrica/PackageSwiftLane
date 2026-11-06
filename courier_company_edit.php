<?php
/** Replaced by the Swift Lane lookup lists manager (catalog.php). Old URL kept for bookmarks. */
header("Location: " . 'catalog.php?c=courier_company' . (isset($_GET['id']) ? '&edit=' . (int) $_GET['id'] : ''), true, 301);
exit;
