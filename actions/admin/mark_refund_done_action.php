<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/orders.php');
}
verify_csrf();

$admin_id = current_user_id();
// For refunds finished by hand in the Paystack dashboard.
$escrow_id = admin_post_id('escrow_id');
$result = admin_record_refund_ctr($admin_id, $escrow_id, $_POST['refund_reference'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
} else {
    $_SESSION['success'] = 'Refund recorded.';
}
redirect('views/admin/order.php?id=' . $escrow_id);
