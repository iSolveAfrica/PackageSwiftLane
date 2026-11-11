<?php
require_once CDP_ROOT.'/helpers/pwa.php';
$installRole=sl_pwa_role();
if(in_array($installRole,['customer','rider'],true)):
?>
<div class="sl-app-tools" aria-label="App options"><button type="button" class="sl-btn ghost sm" data-pwa-install>Install <?= $installRole==='rider'?'Swift Lane Rider':'Swift Lane' ?></button><button type="button" class="sl-btn sm" data-pwa-update hidden>App update ready</button></div>
<?php endif; ?>
