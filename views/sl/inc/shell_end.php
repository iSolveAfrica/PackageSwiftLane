      </div>
    </main>
  </div>
</div>
<div id="sl-toast" class="sl-toast" role="status" aria-live="polite" hidden></div>
<script src="<?= h(cdp_asset('assets/swiftlane/swiftlane.js')) ?>"></script>
<?php if (!empty($slIsClient) && is_file(CDP_ROOT . '/views/support/widget.php')) { include CDP_ROOT . '/views/support/widget.php'; } ?>
<script src="<?= h(cdp_asset('dataJs/sl/chart_interactions.js')) ?>"></script>
<?php foreach ((array) ($slPage['scripts'] ?? []) as $slJs): ?>
<script src="<?= h(cdp_asset($slJs)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
