<?php
/*
 * Paystack sends the buyer here after the payment page, as ?reference=...
 * The reference in the address could be faked, so the payment is checked with Paystack before anything changes.
 */
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../core/payment_gateway.php';

$reference = $_GET['reference'] ?? $_GET['trxref'] ?? '';

if (!preg_match('/^BLB-\d+-[a-f0-9]{12}$/', $reference)) {
    $_SESSION['errors'] = ['general' => 'We could not find that payment.'];
    redirect('views/purchases.php');
}

$result = confirm_escrow_payment($reference);

if ($result['ok']) {
    $_SESSION['success'] = 'Payment received. Bidlab is holding the money until you confirm you have the item.';
    redirect('views/order.php?id=' . $result['escrow_id']);
}

if (!empty($result['retry'])) {
    // Paystack could not be reached. The webhook will record the payment when it arrives.
    $_SESSION['errors'] = ['general' => 'We could not confirm your payment yet. If money left your account, this order will update in a few minutes.'];
} else {
    $_SESSION['errors'] = ['general' => $result['error']];
}

// Escrow ID is in the reference: BLB-<id>-<random>.
$escrow_id = $result['escrow_id'] ?? (int) explode('-', $reference)[1];
redirect('views/order.php?id=' . $escrow_id);
