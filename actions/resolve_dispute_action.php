<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../core/payment_gateway.php';
require_once __DIR__ . '/../core/admin.php';

require_admin();

$admin_id = current_user_id();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/disputes.php');
}
verify_csrf();

$dispute_id = (int) ($_POST['dispute_id'] ?? 0);
$result = resolve_dispute_ctr($dispute_id, $admin_id, $_POST['outcome'] ?? '', $_POST['note'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = array_intersect_key($_POST, array_flip(['dispute_id', 'outcome', 'note']));
    redirect('views/admin/disputes.php');
}

admin_log_ctr($admin_id, 'resolve_dispute', 'order', $result['escrow_id'] ?? (get_dispute_ctr($dispute_id)['escrow_id'] ?? 0),
    ($result['outcome'] === 'refund' ? 'Refunded buyer: ' : 'Paid seller: ') . trim(strip_tags($_POST['note'] ?? '')));

if ($result['outcome'] === 'release') {
    $_SESSION['success'] = 'Dispute closed. The seller will be paid.';
    redirect('views/admin/disputes.php');
}

// Refund: the decision is saved; now send the money back through Paystack.
$refund = paystack_refund($result['payment_reference'], $result['refund_minor']);
if ($refund['ok']) {
    set_refund_reference_ctr($result['escrow_id'], $refund['refund_reference']);
    $_SESSION['success'] = 'Dispute closed. The buyer is being refunded.';
} else {
    add_escrow_note_ctr($result['escrow_id'], 'Refund failed, needs to be done manually: ' . $refund['error'], $admin_id);
    $_SESSION['errors'] = ['general' => 'Decision saved, but the refund did not go through: ' . $refund['error']
        . ' Refund payment ' . $result['payment_reference'] . ' from the Paystack dashboard.'];
}
redirect('views/admin/disputes.php');
