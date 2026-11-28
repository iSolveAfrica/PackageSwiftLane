<?php
/**
 * The customer's locker ID and every warehouse address it works with (customer dashboard and
 * client_virtual_mail_box_addresses.php). Expects $laAddr (sl_customerAddresses()), $laLocker,
 * $laName and optionally $laSteps (true: show the how-it-works strip) and $laMore (link to the full page).
 * Styles: assets/swiftlane/locker-addresses.css.
 */
$laAddr = (array) ($laAddr ?? []);
$laLocker = (string) ($laLocker ?? '');
$laMark = function (string $text) use ($laLocker): string {
    $out = h($text);
    return $laLocker !== '' ? str_replace(h($laLocker), '<mark class="la-lk">' . h($laLocker) . '</mark>', $out) : $out;
};
?>
<link rel="stylesheet" href="<?= h(cdp_asset('assets/swiftlane/locker-addresses.css')) ?>">
<section class="la" id="addresses" aria-labelledby="la-title">
  <div class="la-hero">
    <div class="la-hero-l">
      <span class="la-eyebrow"><i class="ic ic-sm i-key" aria-hidden="true"></i> Your locker ID</span>
      <div class="la-id"><b class="mono"><?= h($laLocker !== '' ? $laLocker : '—') ?></b><?php if ($laLocker !== ''): ?><button type="button" class="la-copy" data-sl-copy="<?= h($laLocker) ?>" aria-label="Copy locker ID"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy</button><?php endif; ?></div>
      <p id="la-title">The same locker works at every warehouse below<?= count($laAddr) > 1 ? ' (' . count($laAddr) . ' countries)' : '' ?>. Put it on every order so we know the parcel is yours.</p>
    </div>
    <?php if (!empty($laSteps)): ?>
    <ol class="la-steps">
      <li><b>1</b><span>Pick the country you are shopping in</span></li>
      <li><b>2</b><span>Paste that address at checkout, locker included</span></li>
      <li><b>3</b><span>Add a pre-alert with the store's tracking number</span></li>
      <li><b>4</b><span>We message you when it arrives and when it is ready</span></li>
    </ol>
    <?php else: ?>
    <div class="la-hero-r"><?php if (!empty($laMore)): ?><a class="la-link" href="<?= h($laMore) ?>">How it works &amp; all addresses <i class="ic ic-sm i-right" aria-hidden="true"></i></a><?php endif; ?></div>
    <?php endif; ?>
  </div>

  <?php if (!$laAddr): ?>
  <div class="sl-card sl-empty mut sm">No warehouse address is set up yet. New addresses appear here as soon as we add them.</div>
  <?php else: ?>
  <div class="la-grid">
    <?php foreach ($laAddr as $a): $rows = array_merge([['Name', trim(($laName ?? $a['recipient']) . ($a['hasLocker'] || $laLocker === '' ? '' : ' (' . $laLocker . ')'))]], array_map(fn($l, $i) => [$i ? '' : 'Address', $l], $a['lines'], array_keys($a['lines']))); if (!$a['hasLocker'] && $laLocker !== '') $rows[] = ['Suite', 'Suite ' . $laLocker]; if ($a['phone'] !== '') $rows[] = ['Phone', $a['phone']]; ?>
    <article class="la-card">
      <header class="la-ch">
        <span class="la-flag" aria-hidden="true"><?= $a['flag'] !== '' ? h($a['flag']) : h($a['cc']) ?></span>
        <div class="la-ct"><b><?= h($a['label']) ?></b><span><?= h(trim($a['city'] . ($a['zip'] !== '' ? ' · ' . $a['zip'] : ''), ' ·')) ?: 'Warehouse' ?></span></div>
        <?php if ($a['default']): ?><span class="la-def">Main</span><?php endif; ?>
      </header>
      <ul class="la-lines">
        <?php foreach ($rows as [$k, $v]): ?>
        <li><small><?= h($k) ?></small><span><?= $laMark($v) ?></span><button type="button" class="la-ib" data-sl-copy="<?= h($v) ?>" aria-label="Copy <?= h(($k ?: 'line') . ': ' . $v) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i></button></li>
        <?php endforeach; ?>
      </ul>
      <footer class="la-cf">
        <button type="button" class="sl-btn sm" data-sl-copy="<?= h($a['full']) ?>"><i class="ic ic-sm i-copy" aria-hidden="true"></i>Copy full address</button>
        <?php if ($laLocker !== ''): ?><span class="la-check"><i class="ic ic-sm i-check" aria-hidden="true"></i>Locker <?= h($laLocker) ?> included</span><?php endif; ?>
      </footer>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
