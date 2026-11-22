<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../../core/payment_gateway.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/orders.php');
}
verify_csrf();

$admin_id = current_user_id();
$escrow_id = admin_post_id('escrow_id');
$result = admin_refund_order_ctr($admin_id, $escrow_id, $_POST['reason'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = ['reason' => $_POST['reason'] ?? ''];
    redirect('views/admin/order.php?id=' . $escrow_id);
}

// The order is marked refunded; now send the money back through Paystack.
$refund = paystack_refund($result['payment_reference'], $result['refund_minor']);
if ($refund['ok']) {
    set_refund_reference_ctr($escrow_id, $refund['refund_reference']);
    $_SESSION['success'] = 'Order refunded. Paystack is returning the money to the buyer.';
} else {
    add_escrow_note_ctr($escrow_id, 'Refund failed, needs to be done manually: ' . $refund['error'], $admin_id);
    $_SESSION['errors'] = ['general' => 'The order is marked refunded, but Paystack did not take the refund: ' . $refund['error']
        . ' Refund it in the Paystack dashboard, then record the reference below.'];
}
redirect('views/admin/order.php?id=' . $escrow_id);
