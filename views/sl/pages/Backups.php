<?php
/**
 * Backups - database backup and restore (backup.php), replacing views/tools/backup/backup.php.
 * Create: backup.php?do=backup&create=1 (doBackup() in helpers/backups_function.php, then ?backupok=1).
 * Download: download.php?file=. Delete / restore: ajax/tools/backup/backup_delete_ajax.php (deleteBackup)
 * and backup_restore_ajax.php (restoreBackup); both still answer with alert HTML, read by SL.fromHtml.
 */
?>
<div class="row g8 mut sm"><a href="<?= sl_route('SettingsHub') ?>">Settings</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Backup and restore</span></div>
<div class="sl-head">
  <div><h1 class="sl-title">Backup and restore</h1><p class="sl-lede">A backup is a full copy of the database as one SQL file in the <span class="mono">backups</span> folder</p></div>
  <div class="row g8 wrap"><?php if (!$d['demo']): ?><a class="sl-btn" href="backup.php?do=backup&amp;create=1" data-sl-confirm="Create a new database backup now? This can take a moment on a large database."><i class="ic ic-sm i-database" aria-hidden="true"></i>Create backup</a><?php endif; ?></div>
</div>
<?php if ($d['ok']): ?><div class="sl-banner ok"><i class="ic ic-sm i-check" aria-hidden="true"></i>Backup created. Download it and keep a copy somewhere safe.</div><?php endif; ?>
<?php if ($d['demo']): ?><div class="sl-banner warn"><i class="ic ic-sm i-alert" aria-hidden="true"></i>Backups are switched off in the demo version.</div><?php endif; ?>
<div class="sl-banner info"><i class="ic ic-sm i-info" aria-hidden="true"></i>Restoring replaces every table with the copy in the file. Make a fresh backup first; this cannot be undone.</div>
<section class="sl-card flush">
  <div class="sl-tw"><table class="sl-table">
    <thead><tr><th>File</th><th>Created</th><th>Size</th><th style="width:240px"></th></tr></thead>
    <tbody>
    <?php foreach ($d['files'] as $f): ?>
      <tr>
        <td class="fw5"><span class="row g8"><i class="ic ic-sm i-database" aria-hidden="true"></i><span class="mono sm"><?= h(preg_replace('/\.sql$/', '', $f['name'])) ?></span><?= $f['latest'] ? sl_pill('Latest', 'delivered') : '' ?></span></td>
        <td class="sm"><?= h(sl_date($f['time'], 'M j, Y H:i')) ?></td>
        <td class="mono sm"><?= h(sl_fileSize((int) $f['size'])) ?></td>
        <td><span class="row g4" style="justify-content:flex-end">
          <a class="sl-btn ghost sm" href="download.php?file=<?= h(rawurlencode($f['name'])) ?>"><i class="ic ic-sm i-download" aria-hidden="true"></i>Download</a>
          <button class="sl-btn ghost sm" type="button" data-bk="restore" data-file="<?= h($f['name']) ?>"><i class="ic ic-sm i-refresh" aria-hidden="true"></i>Restore</button>
          <button class="sl-ib sm" type="button" aria-label="Delete <?= h($f['name']) ?>" data-bk="delete" data-file="<?= h($f['name']) ?>"><i class="ic ic-sm i-trash" aria-hidden="true"></i></button>
        </span></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$d['files']): ?><tr><td colspan="4"><div class="sl-empty"><span class="sl-tile"><i class="ic i-database" aria-hidden="true"></i></span>No backups yet. Create the first one.</div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</section>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-bk]'); if (!b) return;
  var del = b.getAttribute('data-bk') === 'delete', file = b.getAttribute('data-file');
  if (!confirm(del ? 'Delete ' + file + '? This cannot be undone.' : 'Restore the database from ' + file + '? Every table is replaced and this cannot be undone.')) return;
  var data = {}; data[del ? 'deleteBackup' : 'restoreBackup'] = file;
  b.classList.add('loading'); if (!del) SL.toast('Restoring the database, please wait…');
  SL.post(del ? 'ajax/tools/backup/backup_delete_ajax.php' : 'ajax/tools/backup/backup_restore_ajax.php', data).then(function (j) {
    SL.toast(j.message || (j.success ? 'Done' : 'Failed'), !j.success);
    if (j.success && del) setTimeout(function () { location.reload(); }, 400);
  }).catch(function () { SL.toast('Network error', true); }).finally(function () { b.classList.remove('loading'); });
});
</script>
