<?php
// Settings › Status pictures: the illustration shown on load plans for each shipment status, air and sea.
require_once 'loader.php'; $user = new User(); $core = new Core();
require_once 'helpers/sl_view.php'; require_once 'helpers/sl_mx.php';
sl_gate('view_tools');
require_once 'views/sl/data/ops_common.php';
require_once 'helpers/cargo_art.php';
sl_render('StatusPictures', ['id' => 'statuspics', 'title' => 'Status pictures', 'board' => ''], ['rows' => sl_cargoArtRows(), 'art' => sl_cargoArt()]);
