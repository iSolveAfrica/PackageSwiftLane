<?php
$modelOptions = $d['providers'][$d['provider']]['models'] ?? [];
if ($d['model'] !== '' && !isset($modelOptions[$d['model']])) $modelOptions = [$d['model'] => $d['model'] . ' (saved)'] + $modelOptions;
?>
<label class="sl-field"><span class="sl-lab">Model</span><span class="sl-sel native"><select name="model" id="aic-model" class="mono" required><?php foreach ($modelOptions as $id => $name): ?><option value="<?= h($id) ?>"<?= $id === $d['model'] ? ' selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span><span class="sl-hint" id="aic-model-hint" role="status">Choose a model, or load the latest list from your provider.</span></label>
