<?php
/** drivers_add.php The page had no view in the original release; it now redirects to the Swift Lane page that replaces it. */
$q = $_SERVER["QUERY_STRING"] ?? "";
header("Location: users_add.php?role=driver" . ($q !== "" ? "&" . $q : ""), true, 301);
exit;
