<?php
require_once '../core/core.php';
require_once '../controllers/auction_controller.php';

$return = safe_return($_POST['return'] ?? '');

if (!is_logged_in()) {
    $_SESSION['errors'] = ['general' => 'Please log in to watch a lot.'];
    redirect('views/login.php?return=' . urlencode($return));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

toggle_watch_ctr(current_user_id(), (int) ($_POST['auction_id'] ?? 0));
redirect($return);
?>
