<?php
/** Settings form (Swift Lane design). Posts the legacy field names to the existing endpoint. */
$form = $d['form'];
?>
        <div class="sl-head">
          <div><h1 class="sl-title"><?= h($form['title']) ?></h1><p class="sl-lede"><?= h($form['lede']) ?></p></div>
          <a class="sl-btn ghost" href="<?= h(sl_route('SettingsHub')) ?>"><i class="ic ic-sm i-left" aria-hidden="true"></i>Settings</a>
        </div>
        <?php if (count($d['siblings']) > 1): ?>
        <nav class="sl-tabs" aria-label="Shipping settings"><?php foreach ($d['siblings'] as $k => $f): ?><a class="<?= $k === $d['key'] ? 'on' : '' ?>" href="settings_form.php?f=<?= h($k) ?>"><?= h($f['title']) ?></a><?php endforeach; ?></nav>
        <?php endif; ?>
        <form class="sl-card col g16" data-sl-form action="<?= h($form['endpoint']) ?>" method="post">
          <input type="hidden" name="save_config" value="1"><input type="hidden" name="id" value="1">
          <div class="grid cols2" style="gap:16px">
          <?php foreach ($form['fields'] as $k => $f): $v = (string) ($d['values'][$k] ?? ''); ?>
            <div class="sl-field" <?= $f[1] === 'textarea' ? 'style="grid-column:1/-1"' : '' ?>>
              <label class="sl-lab" for="sf-<?= h($k) ?>"><?= h($f[0]) ?></label>
              <?php if ($f[1] === 'textarea'): ?><textarea class="sl-in" id="sf-<?= h($k) ?>" name="<?= h($k) ?>" rows="5"><?= h($v) ?></textarea>
              <?php elseif ($f[1] === 'select' || $f[1] === 'lookup'): $opts = $f[1] === 'select' ? $f[3] : sl_catalogLookup($f[3], '', 1000); ?>
              <label class="sl-sel native"><select id="sf-<?= h($k) ?>" name="<?= h($k) ?>"><?php if ($f[1] === 'lookup'): ?><option value="0">None</option><?php endif; ?><?php foreach ($opts as $ov => $ol): ?><option value="<?= h($ov) ?>" <?= (string) $ov === $v ? 'selected' : '' ?>><?= h($ol) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></label>
              <?php elseif ($f[1] === 'yesno'): ?>
              <div class="sl-seg boxed" role="radiogroup" aria-label="<?= h($f[0]) ?>"><label class="<?= $v === '1' ? 'on' : '' ?>" style="padding:0 12px;height:30px;display:inline-flex;align-items:center;border-radius:9px;cursor:pointer"><input type="radio" name="<?= h($k) ?>" value="1" <?= $v === '1' ? 'checked' : '' ?> style="margin-right:6px">Yes</label><label class="<?= $v !== '1' ? 'on' : '' ?>" style="padding:0 12px;height:30px;display:inline-flex;align-items:center;border-radius:9px;cursor:pointer"><input type="radio" name="<?= h($k) ?>" value="0" <?= $v !== '1' ? 'checked' : '' ?> style="margin-right:6px">No</label></div>
              <?php else: ?><input class="sl-in" id="sf-<?= h($k) ?>" name="<?= h($k) ?>" type="<?= $f[1] === 'number' ? 'number' : 'text' ?>" step="any" value="<?= h($v) ?>"><?php endif; ?>
              <?php if (!empty($f[2])): ?><span class="sl-note"><?= h($f[2]) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
          </div>
          <div class="row g8"><button type="submit" class="sl-btn"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save changes</button></div>
        </form>
