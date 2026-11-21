<?php
/**
 * Model picker for the AI centre: search on top, Free / Paid filter (OpenRouter), grouped list with
 * Recommended / Best / Best value / Best free badges, and a details line for the chosen model.
 * The live catalogue is loaded by dataJs/sl/ai_center.js (action=models); the presets below show
 * until then. Expects $d['providers'], $d['provider'], $d['model'].
 */
require_once CDP_ROOT . '/helpers/ai_assistant_llm.php';
$presetList = function ($prov) use ($d) {
    $list = [];
    foreach (($d['providers'][$prov]['models'] ?? []) as $id => $name) {
        $list[] = ['id' => $id, 'name' => trim(preg_replace('/\s*\((recommended|most capable|faster, lower cost|fastest, lowest cost)\)/i', '', $name)), 'tools' => true, 'tier' => $prov === 'openrouter' ? (substr($id, -5) === ':free' ? 'free' : 'paid') : ''];
    }
    return function_exists('cdp_aiDecorateModels') ? cdp_aiDecorateModels($prov, $list) : $list;
};
$presets = [];
foreach (array_keys($d['providers']) as $prov) $presets[$prov] = $presetList($prov);
$cur = $presets[$d['provider']] ?? [];
?>
<div class="sl-field aic-mp" data-presets="<?= h(json_encode($presets)) ?>">
  <label class="sl-lab" for="aic-model">Model</label>
  <div class="aic-mp-bar">
    <span class="sl-search aic-mp-q"><i class="ic ic-sm i-search" aria-hidden="true"></i><input type="search" id="aic-model-q" placeholder="Search models" aria-label="Search models" autocomplete="off" spellcheck="false"></span>
    <span class="aic-mp-tier" id="aic-tier" role="group" aria-label="Free or paid models"<?= $d['provider'] === 'openrouter' ? '' : ' hidden' ?>><button type="button" class="on" data-tier="">All</button><button type="button" data-tier="free">Free</button><button type="button" data-tier="paid">Paid</button></span>
  </div>
  <span class="sl-sel native"><select name="model" id="aic-model" class="mono" required>
    <?php $seen = false; foreach ($cur as $m): if ($m['id'] === $d['model']) $seen = true; ?><option value="<?= h($m['id']) ?>"<?= $m['id'] === $d['model'] ? ' selected' : '' ?>><?= h($m['name']) ?></option><?php endforeach; ?>
    <?php if ($d['model'] !== '' && !$seen): ?><option value="<?= h($d['model']) ?>" selected><?= h($d['model']) ?> (saved)</option><?php endif; ?>
  </select><i class="ic ic-sm i-down" aria-hidden="true"></i></span>
  <span class="aic-mp-info" id="aic-model-info" aria-live="polite"></span>
  <span class="sl-hint" id="aic-model-hint" role="status">Choose a model, or load the full list from your provider.</span>
</div>
