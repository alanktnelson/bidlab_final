<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/dispute_controller.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/purchases.php');
}
verify_csrf();

$escrow_id = (int) ($_POST['escrow_id'] ?? 0);
$result = open_dispute_ctr($escrow_id, current_user_id(), $_POST['reason'] ?? '', $_POST['details'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = array_intersect_key($_POST, array_flip(['reason', 'details']));
    redirect('views/order.php?id=' . $escrow_id . '#dispute');
}

$_SESSION['success'] = 'Problem reported. The money is on hold while Bidlab looks into it.';
redirect('views/order.php?id=' . $escrow_id);
