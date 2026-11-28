<?php
/**
 * The customer's warehouse addresses as a wallet card stack: round flags pick a country, the chosen card sits
 * in front and the next two peek out behind it (swipe, the next arrow, a flag or a tap on a peeking card rolls
 * the stack). Used by the customer dashboard on computers (partials/client_overview.php) and on phones
 * (views/sl/phone/ClientDashboard.php).
 * Expects $addrs (sl_customerAddresses()), $locker, $awName (the customer's name, '' = the address's recipient).
 * $awAssets = true also prints the stylesheets and the script (pages that do not load them already).
 */
$addrs = array_values((array) ($addrs ?? []));
$locker = (string) ($locker ?? '');
if (!$addrs) return;
$addrOn = 0;
foreach ($addrs as $i => $a) if (!empty($a['default'])) { $addrOn = $i; break; }
$addrMark = fn(string $t) => $locker !== '' ? str_replace(h($locker), '<mark class="la-lk">' . h($locker) . '</mark>', h($t)) : h($t);
$coTitle = fn(array $a): string => ($a['label'] !== '' && $a['label'] !== 'Warehouse') ? $a['label'] : (($a['countryName'] ?? '') !== '' ? $a['countryName'] : 'Warehouse');
// Round flags from the bundled square SVGs (assets/vendor/fonts/flags/1x1); the emoji or letters when there is no file.
$coFlag = function (array $a): string {
    $iso = strtolower((string) ($a['iso'] ?? ''));
    if ($iso !== '' && preg_match('/^[a-z]{2}$/', $iso) && is_file(CDP_ROOT . '/assets/vendor/fonts/flags/1x1/' . $iso . '.svg')) {
        return '<img src="' . h(cdp_asset('assets/vendor/fonts/flags/1x1/' . $iso . '.svg')) . '" alt="" width="40" height="40" loading="lazy">';
    }
    return '<span class="co-fl-t">' . h($a['flag'] !== '' ? $a['flag'] : $a['cc']) . '</span>';
};
// Air or sea warehouse (cdb_virtual_mailbox_addresses.shipping_mode): icon and words for the card and the flag.
$coMode = fn(array $a): array => ['air' => ['i-plane', 'Air freight'], 'sea' => ['i-ship', 'Sea freight'], 'express' => ['i-plane', 'Express air']][$a['mode'] ?? ''] ?? ['', ''];
$coN = count($addrs);
?>
<?php if (!empty($awAssets) && empty($GLOBALS['sl_aw_assets'])): $GLOBALS['sl_aw_assets'] = true; ?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/locker-addresses.css')) ?>">
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/client-overview.css')) ?>">
<script src="<?= h(cdp_asset('dataJs/sl/client_wallet.js')) ?>" defer></script>
<?php endif; ?>
    <div class="co-wallet" data-co-wallet>
      <?php if (count($addrs) > 1): ?>
      <div class="co-flags" role="tablist" aria-label="Warehouse country">
        <?php foreach ($addrs as $i => $a): ?><button type="button" role="tab" class="co-flag" id="co-ft-<?= $i ?>" aria-controls="co-wc-<?= $i ?>" aria-selected="<?= $i === $addrOn ? 'true' : 'false' ?>" tabindex="<?= $i === $addrOn ? '0' : '-1' ?>" data-co-pick="<?= $i ?>" title="<?= h(trim($coTitle($a) . ' · ' . $coMode($a)[1], ' ·')) ?>" aria-label="<?= h(trim($coTitle($a) . ', ' . $coMode($a)[1], ' ,')) ?>"><?= $coFlag($a) ?><?php if ($coMode($a)[0]): ?><span class="co-flag-m <?= h($a['mode']) ?>" aria-hidden="true"><i class="ic <?= h($coMode($a)[0]) ?>"></i></span><?php endif; ?><?php if ($a['default']): ?><i class="co-main" aria-hidden="true"></i><?php endif; ?></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="co-stack" style="--back:<?= min(2, count($addrs) - 1) ?>">
        <?php foreach ($addrs as $i => $a):
          $rows = array_merge([['Name', trim((($awName ?? '') ?: $a['recipient']) . ($a['hasLocker'] || $locker === '' ? '' : ' (' . $locker . ')'))]], array_map(fn($l, $j) => [$j ? '' : 'Address', $l], $a['lines'], array_keys($a['lines'])));
          if (!$a['hasLocker'] && $locker !== '') $rows[] = ['Suite', 'Suite ' . $locker];
          if ($a['phone'] !== '') $rows[] = ['Phone', $a['phone']];
          $pos = ($i - $addrOn + count($addrs)) % count($addrs); ?>
        <article class="co-wc" id="co-wc-<?= $i ?>" role="tabpanel" aria-labelledby="co-ft-<?= $i ?>" data-co-card="<?= $i ?>" data-pos="<?= min($pos, 3) ?>" style="--pos:<?= $pos ?>"<?= $pos ? ' aria-hidden="true"' : '' ?>>
          <header class="co-wc-h">
            <span class="co-wc-fl" aria-hidden="true"><?= $coFlag($a) ?></span>
            <div class="co-wc-t"><b><?= h($coTitle($a)) ?><?php if ($a['default']): ?> <span class="la-def">Main</span><?php endif; ?></b><span><?= h(trim(implode(' · ', array_filter([$a['city'], $a['zip']], 'strlen')))) ?: 'Warehouse' ?></span></div>
            <?php if ($coN > 1): $nx = $addrs[($i + 1) % $coN]; ?><button type="button" class="co-next-b" data-co-next aria-label="Next warehouse: <?= h($coTitle($nx)) ?>" title="Next: <?= h($coTitle($nx)) ?>"><i class="ic ic-sm i-right" aria-hidden="true"></i></button><?php endif; ?>
          </header>
          <?php [$mIc, $mTx] = $coMode($a); if ($mIc): ?><div class="co-wc-mode <?= h($a['mode']) ?>"><span class="co-mode-ic"><i class="ic ic-sm <?= h($mIc) ?>" aria-hidden="true"></i></span><b><?= h($mTx) ?></b><span><?= $a['mode'] === 'sea' ? 'Parcels from here sail to Ghana' : 'Parcels from here fly to Ghana' ?><?= $a['transit'] !== '' ? ' · ' . h($a['transit']) . (preg_match('/^[\d\s–-]+$/u', $a['transit']) ? ' days' : '') : '' ?></span></div><?php endif; ?>
          <ul class="la-lines">
            <?php foreach ($rows as [$k, $v]): ?><li><small><?= h($k) ?></small><span><?= $addrMark($v) ?></span><button type="button" class="la-ib" data-sl-copy="<?= h($v) ?>" aria-label="Copy <?= h(($k ?: 'line') . ': ' . $v) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></li><?php endforeach; ?>
          </ul>
          <footer class="co-wc-f"><button type="button" class="sl-btn sm" data-sl-copy="<?= h($a['full']) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy full address</button><?php if ($locker !== ''): ?><span class="la-check"><i class="ic ic-sm i-check" aria-hidden="true"></i>Locker included</span><?php endif; ?></footer>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
