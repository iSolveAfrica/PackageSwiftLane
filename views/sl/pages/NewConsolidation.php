<?php
/**
 * NewConsolidation - air or sea consolidation, built on one page.
 * Data: views/sl/data/consolidations.php (sl_newConsolidationData). Posts to ajax/sl/consolidation_ajax.php (op=create).
 * The code is generated on save (air: consolidation prefix + number; sea: "C" + origin country + number) and
 * cannot be edited. No prices here: the summary shows the packing list by customer and the total weight.
 */
if (!isset($d)) { $d = []; }
$L = $d['L']; $def = $d['def']; $sea = $d['sea'];
$opt = function (array $rows, $sel) { $o = ''; foreach ($rows as $r) { $o .= '<option value="' . (int) $r->id . '"' . ((int) $r->id === (int) $sel ? ' selected' : '') . '>' . h($r->label) . '</option>'; } return $o; };
$numShow = $d['num'] !== '' ? $d['num'] : '••••••';
$code = $d['prefix'] . $numShow;
$showApproved = $d['approvedN'] > 0;
?>
<form data-sl-form action="ajax/sl/consolidation_ajax.php" method="post" enctype="multipart/form-data" id="nc-form">
<input type="hidden" name="op" value="create"><input type="hidden" name="type" value="<?= h($d['type']) ?>"><input type="hidden" name="ids" id="nc-ids" value=""><input type="hidden" name="dg" value="<?= (int) $d['dg'] ?>">
<div class="row g8 mut sm"><a href="<?= sl_route('Consolidations') ?>">Consolidations</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">New <?= $sea ? 'sea' : 'air' ?> consolidation</span></div>
<div class="sl-head">
  <div><h1 class="sl-title" style="font-size:22px">New <?= $sea ? 'sea' : 'air' ?> consolidation</h1><p class="sl-lede"><?= $sea ? 'Load packages into a container and ship it to ' . h($d['dest']) . '.' : 'Load packages onto a flight to ' . h($d['dest']) . '.' ?> Pick the packages, then save.</p></div>
  <div class="row g8 wrap">
    <?php if (count($d['modes']) > 1): ?><div class="sl-seg boxed"><?php foreach ($d['modes'] as $m): ?><a class="<?= h($m['cls']) ?>" href="<?= h($m['href']) ?>"><i class="ic ic-sm <?= h($m['icon']) ?>" aria-hidden="true"></i> <?= h($m['label']) ?></a><?php endforeach; ?></div><?php endif; ?>
    <a class="sl-btn ghost" href="<?= sl_route('Consolidations') ?>">Cancel</a>
    <button type="submit" class="sl-btn"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save consolidation</button>
  </div>
</div>

<div class="nc2">
<div class="col g16" style="min-width:0">
  <section class="sl-card col g14">
    <h2 class="sl-ct">Consolidation</h2>
    <div class="nc2-code">
      <span class="sl-tile"><i class="ic <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span>
      <div class="col grow"><span class="mut sm"><?= $sea ? 'Container number' : 'Consolidation number' ?></span><b class="mono" id="nc-code" data-num="<?= h($numShow) ?>"><?= h($code) ?></b><span class="mut sm"><?= $d['num'] !== '' ? 'Given automatically when you save' : 'The number is picked automatically when you save' ?> · it cannot be edited</span></div>
      <?php if ($sea): ?><label class="sl-field" style="min-width:220px"><span class="sl-lab">Origin country</span><span class="sl-sel native"><select name="country_code" id="nc-cc"><?php foreach ($d['countries'] as $cc => $cn): ?><option value="<?= h($cc) ?>" <?= $cc === $d['country'] ? 'selected' : '' ?>><?= h($cn) ?> (<?= h($cc) ?>)</option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
    </div>
    <div class="grid cols2" style="gap:12px">
      <label class="sl-field"><span class="sl-lab">Office of origin</span><span class="sl-sel native"><select name="origin_off" required><?= $opt($L['offices'], $def['office']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      <label class="sl-field"><span class="sl-lab">Consolidation status</span><span class="sl-sel native"><select name="status_courier"><?php foreach ($L['statuses'] as $st): if (cdp_isHandedOver($st->id) || (int) $st->id === 21) continue; ?><option value="<?= (int) $st->id ?>" <?= (int) $st->id === $def['status'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $st->label)) ?></option><?php endforeach; ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      <label class="sl-field"><span class="sl-lab"><?= $sea ? 'Shipping line' : 'Airline' ?></span><span class="sl-sel native"><select name="order_courier"><?= $opt($L['couriers'], $def['courier']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      <label class="sl-field"><span class="sl-lab"><?= $sea ? 'Bill of lading' : 'Air waybill number (AWB)' ?></span><input class="sl-in mono" name="awb_no" maxlength="40" placeholder="<?= $sea ? 'MEDU 7781 2290' : '706-44128831' ?>" <?= $d['awbReady'] ? '' : 'disabled title="Run the consolidation AWB upgrade to store this"' ?>></label>
      <?php if ($sea): ?>
      <label class="sl-field"><span class="sl-lab">Seals</span><input class="sl-in mono" name="seals" maxlength="300" placeholder="ML-2219904"></label>
      <label class="sl-field"><span class="sl-lab">Delivery time</span><span class="sl-sel native"><select name="order_deli_time"><?= $opt($L['delitimes'], $def['delitime']) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label>
      <?php endif; ?>
    </div>
  </section>

  <section class="sl-card col g14">
    <h2 class="sl-ct">Dates<?= $sea ? ' and files' : ' and payment' ?></h2>
    <div class="grid cols<?= $sea ? '2' : '3' ?>" style="gap:12px">
      <label class="sl-field"><span class="sl-lab">Shipment date and time</span><input class="sl-in" type="datetime-local" name="order_date" value="<?= h($d['today']) ?>" required></label>
      <label class="sl-field"><span class="sl-lab">Estimated time of arrival</span><input class="sl-in" type="date" name="estimated_eta" value="<?= h($d['eta']) ?>"></label>
      <?php if (!$sea): ?><label class="sl-field"><span class="sl-lab">Payment method</span><span class="sl-sel native"><select name="order_pay_mode"><option value="0">Not set</option><?= $opt($L['payments'], 1) ?></select><i class="ic ic-sm i-down" aria-hidden="true"></i></span></label><?php endif; ?>
    </div>
    <?php if ($sea): ?><label class="sl-field"><span class="sl-lab">Attach files (bill of lading, photos)</span><span class="row g8 wrap"><label class="sl-btn ghost sm" style="cursor:pointer"><i class="ic ic-sm i-upload" aria-hidden="true"></i>Upload files<input type="file" name="files[]" multiple hidden accept=".pdf,image/*" onchange="document.getElementById('nc-doc').textContent=this.files.length+' file(s)'"></label><span class="sl-badge" id="nc-doc">No files</span></span></label><?php endif; ?>
  </section>

  <section class="sl-card col g12 nc2-pk">
    <div class="sl-ch" style="margin:0"><div><h2 class="sl-ct">Packages</h2><span class="mut sm">At the warehouse and not in a consolidation yet<?= $sea ? '' : ($d['dg'] ? ' · dangerous goods only' : ' · normal goods (dangerous goods travel in their own consolidation)') ?></span></div>
      <?php if (!$sea): ?><a class="sl-btn ghost sm" href="<?= h(sl_url(['dg' => $d['dg'] ? 0 : 1])) ?>"><i class="ic ic-sm i-alert" aria-hidden="true"></i><?= $d['dg'] ? 'Show normal goods' : 'Dangerous goods' ?></a><?php endif; ?></div>
    <div class="nc2-tools">
      <div class="sl-seg boxed" role="group" aria-label="Which packages">
        <button type="button" class="<?= $showApproved ? 'on' : '' ?>" data-nc-show="approved">Approved <b><?= (int) $d['approvedN'] ?></b></button>
        <button type="button" class="<?= $showApproved ? '' : 'on' ?>" data-nc-show="all">All waiting <b><?= count($d['avail']) ?></b></button>
      </div>
      <div class="sl-search grow" style="max-width:none"><i class="ic ic-sm i-search" aria-hidden="true"></i><input id="nc-q" aria-label="Find packages" placeholder="Customer, locker, tracking or contents"></div>
      <button type="button" class="sl-btn sm" id="nc-all"><i class="ic ic-sm i-check" aria-hidden="true"></i>Select all</button>
    </div>
    <div class="nc2-head"><span></span><span>Customer</span><span>Tracking</span><span>Contents</span><span class="num">Pcs</span><span>Status</span><span class="num">Weight</span></div>
    <div class="nc2-list">
      <?php foreach ($d['avail'] as $a): ?>
      <button type="button" class="nc2-row" data-oid="<?= (int) $a['oid'] ?>" data-w="<?= h($a['wn']) ?>" data-c="<?= (int) $a['sender'] ?>" data-name="<?= h($a['name']) ?>" data-pcs="<?= (int) $a['pcs'] ?>" data-ok="<?= $a['approved'] ? '1' : '' ?>" data-text="<?= h(strtolower($a['name'] . ' ' . $a['lock'] . ' ' . $a['id'] . ' ' . $a['post'] . ' ' . $a['what'])) ?>"<?= $showApproved && !$a['approved'] ? ' hidden' : '' ?>>
        <span class="sl-chk" aria-hidden="true"></span>
        <span class="col" style="min-width:0"><b class="fw5"><?= h($a['name']) ?></b><span class="mut sm"><?= h($a['lock']) ?></span></span>
        <span class="col" style="min-width:0"><span class="mono sm"><?= h($a['id']) ?></span><span class="mono mut nc2-ell"><?= h($a['post']) ?></span></span>
        <span class="nc2-ell sm"><?= h($a['what']) ?><?php if ($a['dg']): ?> <span class="sl-pill overdue">DG</span><?php endif; ?></span>
        <span class="num mono"><?= (int) $a['pcs'] ?></span>
        <span><span class="sl-pill <?= h($a['stC']) ?>"><?= h($a['st']) ?></span></span>
        <span class="num mono"><?= h($a['w']) ?></span>
      </button>
      <?php endforeach; ?>
      <div class="sl-empty" id="nc-empty"<?= $d['avail'] && !($showApproved && !$d['approvedN']) ? ' hidden' : '' ?>><?= $d['avail'] ? 'No package matches.' : 'No packages are waiting for a consolidation.' ?></div>
    </div>
  </section>
</div>

<aside class="nc2-sum">
  <section class="sl-card col g12">
    <div class="row g10"><span class="sl-tile"><i class="ic <?= $sea ? 'i-ship' : 'i-plane' ?>" aria-hidden="true"></i></span><span class="col grow"><b class="fw5 mono" id="nc-code2"><?= h($code) ?></b><span class="mut sm"><?= $sea ? 'Sea' : 'Air' ?> · to <?= h($d['dest']) ?></span></span></div>
    <div class="nc2-figs"><div><b id="nc-n">0</b><span>packages</span></div><div><b id="nc-c">0</b><span>customers</span></div><div><b id="nc-p">0</b><span>pieces</span></div></div>
    <div class="nc2-total"><span>Total weight</span><b class="mono" id="nc-w">0 <?= h($d['unit']) ?></b></div>
  </section>
  <section class="sl-card col g10">
    <div class="row between"><h2 class="sl-ct">Packing list</h2><span class="mut sm" id="nc-plN">empty</span></div>
    <div class="nc2-pl" id="nc-pl"><span class="mut sm">Pick packages to build the packing list.</span></div>
    <span class="mut sm">After saving, open the consolidation for the full packing list (every customer, package, item and weight<?= $sea ? '' : ', dangerous goods apart' ?>) to print or download.</span>
    <button class="sl-btn block" type="submit" style="height:46px"><i class="ic ic-sm i-check" aria-hidden="true"></i>Save consolidation</button>
  </section>
</aside>
</div>
</form>
<script>
(function () {
  var unit = <?= json_encode($d['unit']) ?>, f = document.getElementById('nc-form'), sel = {}, show = <?= json_encode($showApproved ? 'approved' : 'all') ?>;
  var rows = Array.prototype.slice.call(f.querySelectorAll('.nc2-row'));
  function fmt(n) { return n.toFixed(1).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ' + unit; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function calc() {
    var ids = Object.keys(sel), w = 0, pcs = 0, by = {};
    ids.forEach(function (k) { var s = sel[k]; w += s.w; pcs += s.p; (by[s.c] = by[s.c] || { n: s.name, k: 0, w: 0 }); by[s.c].k++; by[s.c].w += s.w; });
    document.getElementById('nc-ids').value = ids.join(',');
    document.getElementById('nc-n').textContent = ids.length; document.getElementById('nc-c').textContent = Object.keys(by).length; document.getElementById('nc-p').textContent = pcs;
    document.getElementById('nc-w').textContent = fmt(w);
    var pl = document.getElementById('nc-pl'), list = Object.keys(by).map(function (c) { return by[c]; }).sort(function (a, b) { return a.n.localeCompare(b.n); });
    document.getElementById('nc-plN').textContent = list.length ? list.length + ' customer' + (list.length === 1 ? '' : 's') : 'empty';
    pl.innerHTML = list.length ? list.map(function (x) { return '<div><span class="grow">' + esc(x.n) + '</span><span class="mut">' + x.k + ' pkg' + (x.k === 1 ? '' : 's') + '</span><b class="mono">' + fmt(x.w) + '</b></div>'; }).join('') + '<div class="tot"><span class="grow">Total</span><span class="mut">' + ids.length + ' pkgs</span><b class="mono">' + fmt(w) + '</b></div>' : '<span class="mut sm">Pick packages to build the packing list.</span>';
    var vis = rows.filter(function (r) { return !r.hidden; }), allOn = vis.length && vis.every(function (r) { return sel[r.getAttribute('data-oid')]; });
    document.getElementById('nc-all').innerHTML = allOn ? 'Clear selection' : '<i class="ic ic-sm i-check" aria-hidden="true"></i>Select all' + (vis.length ? ' (' + vis.length + ')' : '');
  }
  function toggle(b, on) { var id = b.getAttribute('data-oid'); if (on) sel[id] = { w: +b.getAttribute('data-w'), c: b.getAttribute('data-c'), name: b.getAttribute('data-name'), p: +b.getAttribute('data-pcs') }; else delete sel[id]; b.classList.toggle('on', on); b.querySelector('.sl-chk').classList.toggle('on', on); }
  function filter() {
    var q = document.getElementById('nc-q').value.trim().toLowerCase(), shown = 0;
    rows.forEach(function (r) { var ok = (show === 'all' || r.getAttribute('data-ok') === '1') && (!q || r.getAttribute('data-text').indexOf(q) !== -1); r.hidden = !ok; if (ok) shown++; });
    var em = document.getElementById('nc-empty'); em.hidden = shown > 0; if (rows.length) em.textContent = show === 'approved' && !q ? 'No approved packages. Switch to All waiting.' : 'No package matches.';
    calc();
  }
  f.addEventListener('click', function (e) {
    var b = e.target.closest('.nc2-row'); if (b) { toggle(b, !sel[b.getAttribute('data-oid')]); calc(); return; }
    var s = e.target.closest('[data-nc-show]'); if (s) { show = s.getAttribute('data-nc-show'); f.querySelectorAll('[data-nc-show]').forEach(function (x) { x.classList.toggle('on', x === s); }); filter(); }
  });
  document.getElementById('nc-all').addEventListener('click', function () { var vis = rows.filter(function (r) { return !r.hidden; }), on = !vis.every(function (r) { return sel[r.getAttribute('data-oid')]; }); vis.forEach(function (r) { toggle(r, on); }); calc(); });
  document.getElementById('nc-q').addEventListener('input', filter);
  document.getElementById('nc-q').addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
  var cc = document.getElementById('nc-cc');
  if (cc) cc.addEventListener('change', function () { var code = document.getElementById('nc-code'), t = 'C' + cc.value + code.getAttribute('data-num'); code.textContent = t; document.getElementById('nc-code2').textContent = t; });
  f.addEventListener('submit', function (e) { if (!Object.keys(sel).length) { e.preventDefault(); e.stopImmediatePropagation(); if (window.SL && SL.toast) SL.toast('Pick at least one package', true); } }, true);
  calc();
})();
</script>
