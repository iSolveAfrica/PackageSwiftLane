<?php
// *************************************************************************
// *                                                                       *
// * Swiftlane - Integrated Web Shipping System                            *
// * Copyright (c) iSolveAfrica Ltd. All rights reserved.                  *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software and its source code are proprietary and confidential    *
// * property of iSolveAfrica Ltd. and were developed specifically for     *
// * Swiftlane.                                                            *
// *                                                                       *
// * The software may not be copied, reproduced, modified, distributed,    *
// * sublicensed, published, or used in whole or in part except as         *
// * expressly permitted under the applicable license or written           *
// * agreement with iSolveAfrica Ltd. Any permitted copies or derivative   *
// * works must retain this copyright notice and all applicable            *
// * proprietary notices.                                                  *
// *                                                                       *
// *************************************************************************
  
  require_once("loader.php");
  $user= new User;
?>
<?php
  require_once("helpers/activity_log.php");
  require_once("helpers/csrf.php");

  // Security: sign-out changes state, so it only happens on a POST that carries
  // the CSRF token. A plain GET (the existing "Sign out" links, or a cross-site
  // link/image) gets a one-click confirmation form instead of signing the user
  // out silently. Do not auto-submit this form: that would re-open the CSRF hole.
  if ($user->logged_in && !($_SERVER['REQUEST_METHOD'] === 'POST' && cdp_csrf_validate_request())) {
      $csrf = htmlspecialchars(cdp_csrf_token(), ENT_QUOTES, 'UTF-8');
      $back = 'index.php';
      header('Content-Type: text/html; charset=utf-8');
      header('Cache-Control: no-store');
      ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign out</title>
<style>
  :root { --bg: #f5f6f8; --card: #ffffff; --text: #192a3e; --muted: #5b6b7d; --accent: #192a3e; --accent-text: #ffffff; --border: #dde2e8; }
  @media (prefers-color-scheme: dark) { :root { --bg: #11161d; --card: #1a212b; --text: #e8edf3; --muted: #9aa8b8; --accent: #f2b21b; --accent-text: #11161d; --border: #2a3441; } }
  body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg); color: var(--text); font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; padding: 16px; box-sizing: border-box; }
  main { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 28px; max-width: 360px; width: 100%; text-align: center; }
  h1 { font-size: 20px; margin: 0 0 8px; }
  p { color: var(--muted); margin: 0 0 20px; }
  button { background: var(--accent); color: var(--accent-text); border: 0; border-radius: 8px; padding: 10px 20px; font: inherit; font-weight: 600; cursor: pointer; width: 100%; }
  a { display: inline-block; margin-top: 14px; color: var(--muted); }
</style>
</head>
<body>
<main>
  <h1>Sign out?</h1>
  <p>You will need to sign in again to continue.</p>
  <form method="post" action="logout.php">
    <input type="hidden" name="_csrf_token" value="<?php echo $csrf; ?>">
    <button type="submit" autofocus>Sign out</button>
  </form>
  <a href="<?php echo $back; ?>">Cancel</a>
</main>
</body>
</html><?php
      exit;
  }

  if ($user->logged_in) {
      // Log BEFORE cdp_logout(), which destroys the session the actor is read from.
      cdp_activityLog([
          'module'      => 'auth',
          'verb'        => 'logout',
          'entity_type' => 'user',
          'entity_id'   => (int) ($_SESSION['userid'] ?? 0),
          'summary'     => 'Signed out',
      ]);
  }

  if ($user->logged_in)
      $user->cdp_logout();
	  
   header("location: index.php");
?>