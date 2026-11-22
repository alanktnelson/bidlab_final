<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/orders.php');
}
verify_csrf();

$admin_id = current_user_id();
$escrow_id = admin_post_id('escrow_id');
$result = admin_cancel_order_ctr($admin_id, $escrow_id, $_POST['reason'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = ['reason' => $_POST['reason'] ?? ''];
} else {
    $_SESSION['success'] = 'Order cancelled.';
}
redirect('views/admin/order.php?id=' . $escrow_id);
