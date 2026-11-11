<?php
// *************************************************************************
// * Delivery scheduling (public, signed link from a "ready for delivery"  *
// * or "failed delivery" message). The customer picks one of the next     *
// * bookable days and a time window, confirms a contact number and adds   *
// * directions. Dispatch sees the booking in Communication Center ›       *
// * Deliveries and in the dispatch workspace. A confirmation goes back on *
// * the channel the request came on.                                      *
// *************************************************************************

require_once("loader.php");
require_once("helpers/querys.php");
require_once("helpers/comms.php");
require_once("views/comms/public_shell.php");

$core = new Core();
$token = (string) ($_REQUEST['t'] ?? '');
$id = cdp_commEnsureTables() ? cdp_opsVerify('slot', $token) : 0;
$slot = $id ? cdp_opsOne("SELECT * FROM cdb_comm_delivery_slots WHERE id = :id", [':id' => $id]) : null;
$done = false;
$error = '';
if ($slot && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $r = cdp_commBookSlot($id, (string) ($_POST['slot_date'] ?? ''), (string) ($_POST['slot_window'] ?? ''), (string) ($_POST['note'] ?? ''), (string) ($_POST['phone'] ?? ''));
    if ($r['ok']) {
        $done = true;
        $windows = cdp_commSlotWindows();
        $u = cdp_commUser((int) $slot->user_id);
        if ($u) {
            $ch = trim((string) $u->phone) !== '' ? ['whatsapp'] : ['email'];
            cdp_commQueueEvent('delivery_schedule', (int) $u->id, ['tracking' => $slot->entity_ref], ['channels' => $ch, 'send_after' => time(),
                'subject' => 'Delivery booked for {tracking}', 'entity_ref' => $slot->entity_ref, 'dedupe' => 'slotok' . (int) $slot->id . ':' . date('YmdHis'),
                'body' => 'Thank you {first_name}. Delivery of {tracking} is booked for ' . date('l j F', strtotime($_POST['slot_date'])) . ', '
                    . strtolower($windows[$_POST['slot_window']]) . '. Reply here if anything changes.']);
        }
        $slot = cdp_opsOne("SELECT * FROM cdb_comm_delivery_slots WHERE id = :id", [':id' => $id]);
    } else {
        $error = $r['error'];
    }
}
$e = 'cdp_opsEsc';
cdp_commPublicHead('Choose a delivery time', $core->site_name);
if (!$slot || in_array($slot->status, ['done', 'cancelled'], true)) {
    echo '<h1>Link closed</h1><p>This delivery has already been completed or the link has expired. Please contact us if you need help.</p>';
} elseif ($done) {
    $w = cdp_commSlotWindows();
    echo '<h1>Delivery booked</h1><p>We will deliver <strong>' . $e($slot->entity_ref) . '</strong> on <strong>' . $e(date('l j F', strtotime($slot->slot_date)))
        . '</strong>, ' . $e(strtolower($w[$slot->slot_window] ?? '')) . '.</p><p class="muted">You can open this link again to change the time.</p>';
} else {
    ?>
    <h1>Choose a delivery time</h1>
    <p>For <strong><?php echo $e($slot->entity_ref); ?></strong>. Pick the day and time window that suits you.</p>
    <?php if ($error) { echo '<p class="err" role="alert">' . $e($error) . '</p>'; } ?>
    <form method="post" action="delivery_schedule.php">
        <input type="hidden" name="t" value="<?php echo $e($token); ?>">
        <label for="slot_date">Day</label>
        <select id="slot_date" name="slot_date" required>
            <?php foreach (cdp_commSlotDays() as $d) { ?>
                <option value="<?php echo $e($d); ?>" <?php echo $slot->slot_date === $d ? 'selected' : ''; ?>><?php echo $e(date('l j F', strtotime($d))); ?></option>
            <?php } ?>
        </select>
        <label for="slot_window">Time</label>
        <select id="slot_window" name="slot_window" required>
            <?php foreach (cdp_commSlotWindows() as $k => $v) { ?>
                <option value="<?php echo $e($k); ?>" <?php echo $slot->slot_window === $k ? 'selected' : ''; ?>><?php echo $e($v); ?></option>
            <?php } ?>
        </select>
        <label for="phone">Phone number for the driver</label>
        <input type="tel" id="phone" name="phone" maxlength="40" value="<?php echo $e($slot->phone); ?>" autocomplete="tel">
        <label for="note">Directions or notes (optional)</label>
        <textarea id="note" name="note" rows="3" maxlength="255"><?php echo $e($slot->address_note); ?></textarea>
        <button type="submit">Book delivery</button>
    </form>
    <?php
}
cdp_commPublicFoot();
