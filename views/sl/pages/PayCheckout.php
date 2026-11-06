<?php
/**
 * PayCheckout - generated from docs/handover/artifact-source/PayCheckout.dc.html by scripts/sl_convert.py.
 * Shell page: yes (rendered inside views/sl/inc/shell_start.php)
 * Data contract ($d):
 * sl-wrap extras in the design: style="max-width:980px"
 * Demo data shapes: see the renderVals() script in the artifact source file.
 */
if (!isset($d)) { $d = []; }
?>
<div class="row g8 mut sm"><a href="<?= sl_route('MyBills') ?>">My bills</a><i class="ic ic-sm i-right" aria-hidden="true"></i><span style="color:var(--ink)">Pay</span></div>
<div class="sl-head"><div><h1 class="sl-title">Pay your bills</h1><p class="sl-lede">Paid parcels are released for pickup right away</p></div></div>
<div class="split"><div class="a col g16"><section class="sl-card col g16"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Pay with</h2></div><div class="grid cols3" style="gap:12px"><div class="np-opt on"><span class="sl-radio on"></span><span class="col"><b class="fw5">Mobile money</b><span class="mut sm">MTN, Telecel, AT</span></span></div><div class="np-opt"><span class="sl-radio"></span><span class="col"><b class="fw5">Card</b><span class="mut sm">Visa, Mastercard</span></span></div><div class="np-opt"><span class="sl-radio"></span><span class="col"><b class="fw5">Bank transfer</b><span class="mut sm">GCB, Ecobank</span></span></div></div><div class="grid cols2" style="gap:12px"><div class="sl-field"><span class="sl-lab">Network</span><div class="sl-sel"><span>MTN Mobile Money</span><i class="ic ic-sm i-down" aria-hidden="true"></i></div></div><label class="sl-field"><span class="sl-lab">Phone number</span><input class="sl-in mono" value="024 412 5678" placeholder=""></label></div><div class="mut sm">You will get a prompt on your phone to approve with your PIN.</div></section></div><aside class="b col g16"><section class="sl-card col g16"><div class="sl-ch" style="margin:0"><h2 class="sl-ct">Summary</h2></div><div class="sl-kv"><div><span>INV-2041 · SL-100480</span><span>₵694.00</span></div><div><span>INV-2063 · SL-100455</span><span>₵180.00</span></div><div><span>INV-2067 · SL-100431</span><span>₵1,426.00</span></div></div><div class="row between"><b class="fw5">Total</b><b class="mono" style="font-size:22px">₵2,300.00</b></div><a class="sl-btn block" href="<?= sl_route('ReceiptPaid') ?>" style="height:48px"><i class="ic ic-sm i-lock" aria-hidden="true"></i>Pay ₵2,300.00</a><span class="mut sm" style="text-align:center">Secured by Paystack</span></section></aside></div>
