<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/payouts.php');
}
verify_csrf();

$admin_id = current_user_id();
// Only for payouts you have confirmed or sent yourself in the Paystack dashboard.
$payout_id = admin_post_id('payout_id');
$result = admin_mark_payout_paid_ctr($admin_id, $payout_id, $_POST['reference'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = ['payout_id' => $payout_id, 'reference' => $_POST['reference'] ?? ''];
    redirect('views/admin/payouts.php?status=' . urlencode($_POST['back_status'] ?? 'failed'));
}
$_SESSION['success'] = 'Payout marked as paid.';
redirect('views/admin/payouts.php?status=sent');
