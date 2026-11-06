<?php
/**
 * DesignSystem - generated from docs/handover/artifact-source/DesignSystem.dc.html by scripts/sl_convert.py.
 * Shell page: no (plain page)
 * Data contract ($d):
 *   loop  surfaces                     as $s
 *   loop  inks                         as $s
 *   loop  signals                      as $s
 *   loop  icons                        as $n
 *   value icons, inks, signals, surfaces
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="sl-app" style="padding: 0 0 56px">
  <div style="width: 100%; margin: 0 auto; padding: 56px 24px 0; display: flex; flex-direction: column; gap: 56px">

    <header class="col g12">
      <div class="sl-brand"><span class="sl-logo"><i></i></span>Swift Lane</div>
      <h1 style="font-size: 44px; font-weight: 500; letter-spacing: -.02em; line-height: 1.1">Warm Ledger design system</h1>
      <p class="mut" style="font-size: 16px; max-width: 640px">Warm paper surfaces, hairline borders, dotted tick meters and one dark action. Extracted from the Overview reference and applied to every Swift Lane screen on this canvas.</p>
    </header>

    <section class="col g16" aria-labelledby="p">
      <h2 id="p" class="sl-title">Principles</h2>
      <div class="grid cols3">
        <div class="sl-card col g8"><div class="sl-tile"><i class="ic i-layers" aria-hidden="true"></i></div><h3 class="sl-ct">Paper, not chrome</h3><p class="mut">Three warm surfaces and a 1px hairline do all the layering. No drop shadows on cards, ever.</p></div>
        <div class="sl-card col g8"><div class="sl-tile"><i class="ic i-chart" aria-hidden="true"></i></div><h3 class="sl-ct">Meters made of ticks</h3><p class="mut">Any quantity against a limit becomes a dotted tick bar. Green is healthy, yellow is filling, red is near the limit.</p></div>
        <div class="sl-card col g8"><div class="sl-tile"><i class="ic i-plus" aria-hidden="true"></i></div><h3 class="sl-ct">One dark action</h3><p class="mut">Each view has a single near-black button. Everything else is soft or ghost. Colour is kept for state.</p></div>
      </div>
    </section>

    <section class="col g16" aria-labelledby="c">
      <h2 id="c" class="sl-title">Colour</h2>
      <div class="sl-card col g20">
        <div class="up">Surfaces</div>
        <div class="grid cols5">
          <?php foreach ((array) sl_get($d, 'surfaces', []) as $__i => $s): ?><div class="col g8"><div style="height: 56px; border-radius: 12px; border: 1px solid var(--line-2); background: <?= sl_v($s, 'hex') ?>"></div><div><div class="fw5"><?= sl_v($s, 'name') ?></div><div class="mono mut sm"><?= sl_v($s, 'hex') ?> · <?= sl_v($s, 'use') ?></div></div></div><?php endforeach; ?>
        </div>
        <div class="up">Ink</div>
        <div class="grid cols5">
          <?php foreach ((array) sl_get($d, 'inks', []) as $__i => $s): ?><div class="col g8"><div style="height: 56px; border-radius: 12px; border: 1px solid var(--line-2); background: <?= sl_v($s, 'hex') ?>"></div><div><div class="fw5"><?= sl_v($s, 'name') ?></div><div class="mono mut sm"><?= sl_v($s, 'hex') ?> · <?= sl_v($s, 'use') ?></div></div></div><?php endforeach; ?>
        </div>
        <div class="up">Signals</div>
        <div class="grid cols5">
          <?php foreach ((array) sl_get($d, 'signals', []) as $__i => $s): ?><div class="col g8"><div style="height: 56px; border-radius: 12px; border: 1px solid var(--line-2); background: <?= sl_v($s, 'hex') ?>"></div><div><div class="fw5"><?= sl_v($s, 'name') ?></div><div class="mono mut sm"><?= sl_v($s, 'hex') ?> · <?= sl_v($s, 'use') ?></div></div></div><?php endforeach; ?>
        </div>
        <div class="up">Activity heat</div>
        <div class="row g12 wrap"><div class="sl-heat" style="grid-template-rows: 18px; grid-template-columns: repeat(4, 40px)"><b class="h0"></b><b class="h1"></b><b class="h2"></b><b class="h3"></b></div><span class="mono mut sm">#D6D5CF · #BDE9D6 · #6FCDA6 · #0EAD78</span></div>
      </div>
    </section>

    <section class="col g16" aria-labelledby="t">
      <h2 id="t" class="sl-title">Typography</h2>
      <div class="sl-card col g16">
        <div class="row between wrap g12" style="border-bottom: 1px solid var(--line); padding-bottom: 14px"><span class="big">Figtree 400 / 500 / 600</span><span class="mut">Interface and numerals. JetBrains Mono for IDs, tracking numbers and peaks.</span></div>
        <div class="row between wrap g12"><span style="font-size: 18px; font-weight: 500">Page title — Air Shipments</span><span class="mono mut sm">18 / 500</span></div>
        <div class="row between wrap g12"><span class="sl-ct">Card title — Parcels Received</span><span class="mono mut sm">15 / 500</span></div>
        <div class="row between wrap g12"><span>Body — Kwame Mensah, Accra, 3.2 kg</span><span class="mono mut sm">14 / 400</span></div>
        <div class="row between wrap g12"><span class="sl-lab">Field label — Recipient address</span><span class="mono mut sm">12.5 / 500</span></div>
        <div class="row between wrap g12"><span class="up">Overline — Cost &amp; billing</span><span class="mono mut sm">11.5 / 500 / +.05em caps</span></div>
        <div class="row between wrap g12"><span class="mono fw5">SL-100482 · 1Z999AA10123456784</span><span class="mono mut sm">JetBrains Mono 12.5 / 500</span></div>
        <div class="row between wrap g12"><span class="big">$48,200</span><span class="mono mut sm">26 / 500</span></div>
      </div>
    </section>

    <section class="col g16" aria-labelledby="s">
      <h2 id="s" class="sl-title">Shape and space</h2>
      <div class="grid cols2">
        <div class="sl-card col g12"><div class="up">Radius</div>
          <div class="row g12 wrap end" style="justify-content: flex-start">
            <div class="col g6" style="align-items: center"><div style="width: 56px; height: 56px; border: 1.5px solid var(--ink); border-radius: 6px"></div><span class="mono mut sm">6</span></div>
            <div class="col g6" style="align-items: center"><div style="width: 56px; height: 56px; border: 1.5px solid var(--ink); border-radius: 10px"></div><span class="mono mut sm">10</span></div>
            <div class="col g6" style="align-items: center"><div style="width: 56px; height: 56px; border: 1.5px solid var(--ink); border-radius: 14px"></div><span class="mono mut sm">14</span></div>
            <div class="col g6" style="align-items: center"><div style="width: 56px; height: 56px; border: 1.5px solid var(--ink); border-radius: 18px"></div><span class="mono mut sm">18</span></div>
            <div class="col g6" style="align-items: center"><div style="width: 56px; height: 56px; border: 1.5px solid var(--ink); border-radius: 22px"></div><span class="mono mut sm">22</span></div>
            <div class="col g6" style="align-items: center"><div style="width: 56px; height: 56px; border: 1.5px solid var(--ink); border-radius: 100px"></div><span class="mono mut sm">pill</span></div>
          </div>
          <p class="mut sm">Buttons and fields 10 · cards 18 · panels 22 · search and status pills fully round.</p>
        </div>
        <div class="sl-card col g12"><div class="up">Spacing — 4 · 8 · 12 · 16 · 20 · 24 · 32</div>
          <div class="row g12" style="align-items: flex-end">
            <div style="width: 4px; height: 4px; background: var(--ink)"></div><div style="width: 8px; height: 8px; background: var(--ink)"></div><div style="width: 12px; height: 12px; background: var(--ink)"></div><div style="width: 16px; height: 16px; background: var(--ink)"></div><div style="width: 20px; height: 20px; background: var(--ink)"></div><div style="width: 24px; height: 24px; background: var(--ink)"></div><div style="width: 32px; height: 32px; background: var(--ink)"></div>
          </div>
          <p class="mut sm">Cards sit 16px apart. Card padding 18 / 20. Main panel padding 30 / 32. Content is capped at 1060px.</p>
        </div>
      </div>
    </section>

    <section class="col g16" aria-labelledby="sh">
      <h2 id="sh" class="sl-title">App shell</h2>
      <div class="sl-card">
        <div style="background: var(--bg); border-radius: 18px; padding: 10px; display: flex; flex-direction: column; gap: 8px">
          <div class="row g8 mut sm" style="height: 30px; padding: 0 8px"><i class="ic ic-sm i-sidebar" aria-hidden="true"></i><span class="fw5" style="color: var(--ink)">Top bar · 56px</span><span class="grow"></span><span class="sl-tag">Primary action</span></div>
          <div class="row g8 top">
            <div style="width: 44px; height: 220px; border-radius: 12px; border: 1px dashed var(--line-2); display: grid; place-items: center" class="mut sm">Rail</div>
            <div style="width: 200px; height: 220px; border-radius: 18px; background: var(--panel); border: 1px solid var(--line); display: grid; place-items: center; text-align: center" class="mut sm">Sidebar · 264px<br>grouped nav + counts</div>
            <div class="grow" style="height: 220px; border-radius: 18px; background: var(--main); border: 1px solid var(--line); padding: 14px"><div class="grid cols3"><div style="height: 56px; border-radius: 12px; background: var(--card); border: 1px solid var(--line)"></div><div style="height: 56px; border-radius: 12px; background: var(--card); border: 1px solid var(--line)"></div><div style="height: 56px; border-radius: 12px; background: var(--card); border: 1px solid var(--line)"></div></div><div style="margin-top: 12px; height: 110px; border-radius: 12px; background: var(--card); border: 1px solid var(--line)"></div></div>
          </div>
        </div>
      </div>
    </section>

    <section class="col g16" aria-labelledby="i">
      <h2 id="i" class="sl-title">Icons</h2>
      <p class="mut" style="margin-top: -8px">1.6px stroke, 24px grid, drawn with CSS masks so they take the colour of the text around them.</p>
      <div class="grid" style="grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 8px">
        <?php foreach ((array) sl_get($d, 'icons', []) as $__i => $n): ?><div class="sl-card col g8" style="align-items: center; padding: 14px 8px"><i class="ic ic-lg <?= sl_v($n, 'cls') ?>" aria-hidden="true"></i><span class="mono mut" style="font-size: 11px"><?= sl_v($n, 'name') ?></span></div><?php endforeach; ?>
      </div>
    </section>

    <section class="col g16" aria-labelledby="co">
      <h2 id="co" class="sl-title">Components</h2>

      <div class="sl-card col g16"><div class="up">Buttons</div>
        <div class="row wrap g12"><a class="sl-btn" href="<?= sl_route('NewShipment') ?>"><i class="ic ic-sm i-plus" aria-hidden="true"></i>New Shipment</a><a class="sl-btn soft" href="<?= sl_route('PublicTracking') ?>">Track Shipment</a><a class="sl-btn ghost" href="<?= sl_route('Documents') ?>">Export</a><button class="sl-btn danger"><i class="ic ic-sm i-trash" aria-hidden="true"></i>Delete</button><a class="sl-btn sm ghost" href="<?= sl_route('NewIntegration') ?>">Connect</a><a class="sl-btn-violet" href="<?= sl_route('ControlPanelsMobile') ?>">Get app</a><button class="sl-ib" aria-label="More"><i class="ic i-more" aria-hidden="true"></i></button></div>
        <p class="mut sm">Dark = the one primary action. Soft = selected or secondary. Ghost = utility. Violet is only for the app promo.</p>
      </div>

      <div class="sl-card col g16"><div class="up">Fields</div>
        <div class="grid cols3">
          <div class="sl-field"><label class="sl-lab" for="d1">Tracking number</label><input id="d1" class="sl-in" placeholder="SL-100482"></div>
          <div class="sl-field"><span class="sl-lab">Destination</span><div class="sl-sel"><span>Accra, Greater Accra</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div>
          <div class="sl-field"><label class="sl-lab" for="d3">Search</label><div class="sl-search"><i class="ic ic-sm i-search" aria-hidden="true"></i><input id="d3" placeholder="Search shipments"></div></div>
        </div>
        <div class="row wrap g24"><div class="row g8"><span class="sl-chk on"></span>Checked</div><div class="row g8"><span class="sl-chk"></span>Unchecked</div><div class="row g8"><span class="sl-radio on"></span>Radio</div><div class="row g8"><span class="sl-toggle on"></span>WhatsApp alerts</div><div class="row g8"><span class="sl-toggle"></span>SMS alerts</div></div>
      </div>

      <div class="sl-card col g16"><div class="up">Status</div>
        <div class="row wrap g12"><span class="sl-pill pending">Pending</span><span class="sl-pill sorting">At warehouse</span><span class="sl-pill transit">In transit</span><span class="sl-pill onway">Out for delivery</span><span class="sl-pill delivered">Delivered</span><span class="sl-pill">Cancelled</span></div>
        <div class="row wrap g12"><span class="sl-badge air">Air</span><span class="sl-badge sea">Sea</span><span class="sl-badge warn">Dangerous goods</span><span class="sl-badge ok">Paid</span><span class="sl-badge bad">Overdue</span><span class="sl-badge">Draft</span></div>
      </div>

      <div class="sl-card col g16"><div class="up">Tick meters</div>
        <div class="grid cols4">
          <div class="col g8"><div class="sl-ticks" role="img" aria-label="25 percent"><i style="width: 25%"></i></div><span class="mut sm">Healthy · green</span></div>
          <div class="col g8"><div class="sl-ticks y" role="img" aria-label="60 percent"><i style="width: 60%"></i></div><span class="mut sm">Filling · yellow</span></div>
          <div class="col g8"><div class="sl-ticks r" role="img" aria-label="92 percent"><i style="width: 92%"></i></div><span class="mut sm">Near limit · red</span></div>
          <div class="col g8"><div class="sl-ticks v" role="img" aria-label="40 percent"><i style="width: 40%"></i></div><span class="mut sm">Neutral · violet</span></div>
        </div>
        <div class="sl-steps" aria-label="Step 3 of 5"><i class="on"></i><i class="on"></i><i class="on"></i><i></i><i></i></div>
        <div class="row g12 wrap"><div class="sl-seg"><button>All</button><button class="on">7d</button><button>1m</button><button>6m</button><button>1y</button></div><div class="sl-seg boxed"><button class="on">Overview</button><button>Items</button><button>Payments</button></div></div>
      </div>

      <div class="grid cols2">
        <a class="sl-card sl-metric" href="<?= sl_route('Main') ?>">
          <div class="sl-ml"><span><i class="ic ic-sm i-warehouse" aria-hidden="true"></i>Warehouse</span><i class="ic ic-sm i-right" aria-hidden="true"></i></div>
          <div class="sl-mv">46 / 50 <i>locker bays full (92%)</i></div>
          <div class="sl-ticks r"><i style="width: 92%"></i></div>
          <div class="sl-mf">2 arriving now · 5 awaiting pickup <i class="ic ic-sm i-ext" aria-hidden="true"></i></div>
        </a>
        <div class="sl-card flush">
          <div class="sl-tw"><table class="sl-table"><thead><tr><th>Tracking</th><th>Recipient</th><th>Status</th><th class="num">Weight</th></tr></thead><tbody>
            <tr><td class="id">SL-100482</td><td>Ama Boateng</td><td><span class="sl-pill transit">In transit</span></td><td class="num">3.2 kg</td></tr>
            <tr><td class="id">SL-100481</td><td>Kofi Asante</td><td><span class="sl-pill delivered">Delivered</span></td><td class="num">12.8 kg</td></tr>
          </tbody></table></div>
        </div>
      </div>

      <div class="grid cols2">
        <div class="sl-card"><div class="up" style="margin-bottom: 14px">Timeline</div>
          <div class="sl-tl"><div class="done"><span class="dot"></span><div><div class="fw5">Delivered to recipient</div><div class="mut sm">Nov 3, 14:20 · Accra</div></div></div><div class="now"><span class="dot"></span><div><div class="fw5">Out for delivery</div><div class="mut sm">Nov 3, 08:05 · Kwame, Driver 07</div></div></div><div><span class="dot"></span><div class="mut">Arrived at Accra warehouse</div></div></div>
        </div>
        <div class="col g12"><div class="sl-banner info"><i class="ic i-info" aria-hidden="true"></i><span>Info — Pre-alert received for 3 parcels.</span></div><div class="sl-banner"><i class="ic i-alert" aria-hidden="true"></i><span>Warning — Warehouse bays are 92% full.</span></div><div class="sl-banner bad"><i class="ic i-alert" aria-hidden="true"></i><span>Error — Payment failed for SL-100470.</span></div><div class="sl-banner ok"><i class="ic i-check" aria-hidden="true"></i><span>Success — Label printed.</span></div></div>
      </div>
    </section>
  </div>
</div>
