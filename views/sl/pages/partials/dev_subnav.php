<?php
/**
 * Shared sub-navigation of the developer console (owned by the "observe" package).
 *
 * Usage in a Dev page view (render the page with 'theme' => 'dk dvx' and
 * 'styles' => ['assets/swiftlane/dev_theme.css']):
 *     <?php $dvxActive = 'overview'; include CDP_ROOT . '/views/sl/pages/partials/dev_subnav.php'; ?>
 *     ... page content ...
 *     <?= sl_dvxSubnavEnd() ?>
 * The include opens <div class="dvx-layout"><nav class="dvx-sub">…</nav><div class="dvx-body">;
 * sl_dvxSubnavEnd() closes both.
 *
 * Each section is shown only when its page exists and the user may see it: cdp_devCanUse() and,
 * when the access package is installed, cdp_devSectionAllowed($user, $key). Section keys:
 *   overview services logs metrics infrastructure deployments alerts github integrations issues
 *   database modules access
 */
if (!function_exists('sl_dvxSections')) {
    /** [key, label, href, icon, group]; optional pages appear once their file exists. */
    function sl_dvxSections(): array
    {
        $ex = fn(string $f) => is_file(CDP_ROOT . '/' . $f);
        $s = [
            ['overview', 'Overview', 'dev_overview.php', 'i-grid', 'Observe'],
            ['services', 'Services', 'dev_services.php', 'i-server', 'Observe'],
            ['logs', 'Logs', 'dev_logs.php', 'i-terminal', 'Observe'],
            ['metrics', 'Metrics', 'dev_metrics.php', 'i-activity', 'Observe'],
            ['infrastructure', 'Infrastructure', 'dev_infrastructure.php', 'i-cloud', 'Operate'],
            ['alerts', 'Alerts', 'dev_alerts.php', 'i-bell', 'Operate'],
            ['deployments', 'Deployments', 'dev_deployments.php', 'i-rocket', 'Ship'],
        ];
        if ($ex('dev_github.php')) {
            $s[] = ['github', 'GitHub', 'dev_github.php', 'i-git', 'Ship'];
            $s[] = ['integrations', 'Integrations', 'dev_integrations.php', 'i-link', 'Ship'];
        } else {
            $s[] = ['integrations', 'Integrations / GitHub', 'dev_integrations.php', 'i-git', 'Ship'];
        }
        $s[] = ['issues', 'Issues & change log', 'dev_issues.php', 'i-bug', 'Ship'];
        if ($ex('dev_database.php')) {
            $s[] = ['database', 'Database', 'dev_database.php', 'i-database', 'Ship'];
        }
        $s[] = ['modules', 'Modules', 'module_controller.php', 'i-sliders', 'Control'];
        if ($ex('dev_access.php')) {
            $s[] = ['access', 'Access', 'dev_access.php', 'i-lock', 'Control'];
        }
        return array_values(array_filter($s, fn($x) => $ex(strtok($x[2], '?#'))));
    }

    /** Sections this user may open. */
    function sl_dvxVisibleSections($user): array
    {
        if (!is_object($user) || !function_exists('cdp_devCanUse') || !cdp_devCanUse($user)) {
            return [];
        }
        return array_values(array_filter(sl_dvxSections(), function ($x) use ($user) {
            return !function_exists('cdp_devSectionAllowed') || cdp_devSectionAllowed($user, $x[0]);
        }));
    }

    function sl_dvxSubnavEnd(): string
    {
        return '</div></div>';
    }
}
if (!function_exists('cdp_devCanUse') && is_file(CDP_ROOT . '/helpers/devops.php')) {
    require_once CDP_ROOT . '/helpers/devops.php';
}
$dvxActive = $dvxActive ?? '';
$dvxItems = sl_dvxVisibleSections($user ?? null);
$dvxGroup = '';
?>
<div class="dvx-layout">
<nav class="dvx-sub" aria-label="Developer console">
  <div class="dvx-sub-h"><span class="dvx-brand"><i class="ic ic-sm i-terminal" aria-hidden="true"></i></span><span class="col"><b>DevOps console</b><small class="mono"><?= h((string) gethostname()) ?></small></span></div>
  <div class="dvx-sub-l">
  <?php foreach ($dvxItems as [$k, $label, $href, $icon, $grp]): ?>
    <?php if ($grp !== $dvxGroup): $dvxGroup = $grp; ?><span class="dvx-sub-g"><?= h($grp) ?></span><?php endif; ?>
    <a class="dvx-sub-a<?= $k === $dvxActive ? ' on' : '' ?>" href="<?= h($href) ?>"<?= $k === $dvxActive ? ' aria-current="page"' : '' ?>><i class="ic ic-sm <?= h($icon) ?>" aria-hidden="true"></i><span><?= h($label) ?></span></a>
  <?php endforeach; ?>
  </div>
</nav>
<div class="dvx-body">
