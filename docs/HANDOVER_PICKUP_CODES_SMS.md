# Pickup codes, Picked up vs Delivered, and when SMS goes out

Changed 2026-11-04. Run `migrations/2026_11_04_pickup_codes_handover_sms_mailbox.sql` once on the live database.

## Pickup codes (`helpers/pickup_code.php`)

- The code now goes out **inside the ready message**: when packages become Ready for PickUp (32), Available (6) or ready for delivery / On Route (7), the WhatsApp, SMS and e-mail that say so carry the owner's 6-digit code. One code per owner per message, covering the packages that message names.
- Paths that put it in: tracking update forms (shipments and packages), bulk status updates, edit forms when the status changes, the consolidation fan-out, and the Financial Sheet bill ("ready for pickup").
- The same packages announced again keep the code they already have. A package announced in a new set moves to a new code.
- A code is valid until its packages are handed over (90 days at most). Five wrong entries in a row void it.
- At handover staff open the Pickup Code dialog, type the code the customer shows, and hand over within 30 minutes. A partial collection can be verified again later.
- "Send Pickup Code" / "Resend Code To Customer" sends the customer's existing code by SMS, WhatsApp and e-mail (a new one only when they have none). One resend a minute.
- Ready for delivery also carries the code (`CDP_PICKUP_CODE_ON_DELIVERY`); set it to `false` to keep codes on pickup messages only.

## Picked up vs Delivered (`helpers/handover.php`)

- 15 **Picked up**: the customer collected it from us. 8 **Delivered**: we took it to the customer.
- Every handover screen asks "How was it handed over?", Picked up first: the shipment and package handover forms, both consolidation handover forms, Warehouse View (bulk) and Warehouse Delivery.
- Messages, history lines, chips and detail panels use the word that matches the saved status. Lists, views and reports treat both as finished (`cdp_isHandedOver()`).
- Warehouse Delivery closes a consolidation as Picked up when every package was picked up, otherwise Delivered.

## SMS (`helpers/hubtel_sms.php`)

SMS goes out only when someone means it to:

- Staff send one: the "notify by SMS" toggles, the message composer, Send Pickup Code.
- Three automations (while the SMS switch is on and the customer has not opted out): a package is **registered**, a package is **ready** for pickup / delivery, a package is **picked up / delivered**.
- Sign-in and reset codes, as before.

Every other WhatsApp message (other status updates, payment receipts, reminders, bulk notices) no longer gets an SMS copy. A WhatsApp send asks for a copy with `['sms_event' => 'registered' | 'ready' | 'handed_over']`.
