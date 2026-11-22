<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/escrow_controller.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/selling.php');
}
verify_csrf();

$escrow_id = (int) ($_POST['escrow_id'] ?? 0);
$result = mark_shipped_ctr($escrow_id, current_user_id());

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
} else {
    $_SESSION['success'] = 'Marked as sent. You will be paid when the buyer confirms, or automatically after '
        . ESCROW_AUTO_RELEASE_DAYS . ' days if they do not respond.';
}
redirect('views/order.php?id=' . $escrow_id);
