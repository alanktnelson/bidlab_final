<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/escrow_controller.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/purchases.php');
}
verify_csrf();

$escrow_id = (int) ($_POST['escrow_id'] ?? 0);
$result = confirm_receipt_ctr($escrow_id, current_user_id());

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
} else {
    $_SESSION['success'] = 'Thanks for confirming. The seller is being paid.';
}
redirect('views/order.php?id=' . $escrow_id);
