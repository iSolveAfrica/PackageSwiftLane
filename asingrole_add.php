<?php
/** asingrole_add.php The page had no view in the original release; it now redirects to the Swift Lane page that replaces it. */
$q = $_SERVER["QUERY_STRING"] ?? "";
header("Location: manage_permissions.php" . ($q !== "" ? "?" . $q : ""), true, 301);
exit;
