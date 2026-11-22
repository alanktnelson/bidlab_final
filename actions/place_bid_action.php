<?php
require_once '../core/core.php';
require_once '../controllers/bid_controller.php';
require_once '../core/admin.php';

$auction_id = (int) ($_POST['auction_id'] ?? 0);
$page = 'views/single_auction.php?id=' . $auction_id;

if (!is_logged_in()) {
    $_SESSION['errors'] = ['general' => 'Please log in first.'];
    redirect('views/login.php?return=' . urlencode($page));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();
require_active_user();   // suspended users cannot bid

$error = place_bid_ctr(current_user_id(), $auction_id, $_POST['amount'] ?? '');

if ($error !== null) {
    $_SESSION['bid_error'] = $error;
    $_SESSION['old_bid'] = is_string($_POST['amount'] ?? null) ? $_POST['amount'] : '';
}
redirect($page);
?>
