<?php
// The customer's virtual mailbox addresses (Swift Lane design: views/sl/pages/MailboxAddresses.php).
// ?filter_virtual_mail_boxes=<id> shows one country.
require_once 'loader.php';
$user = new User();
$core = new Core();
require_once 'helpers/sl_view.php';
sl_gate('client_virtual_mail_box_addresses');
require_once 'views/sl/data/account_edit.php';
sl_render('MailboxAddresses', ['id' => 'addresses', 'title' => 'My shipping addresses', 'board' => ''], sl_mailboxData($core, $userData));
