<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../core/payment_gateway.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/purchases.php');
}
verify_csrf();

$escrow_id = (int) ($_POST['escrow_id'] ?? 0);
$buyer_id = current_user_id();
$back = 'views/order.php?id=' . $escrow_id;

$payment = start_payment_ctr($escrow_id, $buyer_id);
if (isset($payment['errors'])) {
    $_SESSION['errors'] = $payment['errors'];
    redirect($back);
}

$email = get_user_email_ctr($buyer_id);
if (!$email) {
    $_SESSION['errors'] = ['general' => 'Add an email address to your account before paying.'];
    redirect($back);
}

$checkout = paystack_initialize(
    $email,
    $payment['amount_minor'],
    $payment['reference'],
    SITE_BASE_URL . '/actions/payment_callback.php',
    ['escrow_id' => $payment['escrow_id'], 'buyer_id' => $buyer_id]
);

if (!$checkout['ok']) {
    $_SESSION['errors'] = ['general' => $checkout['error']];
    redirect($back);
}

// Off to Paystack's payment page. Paystack sends the buyer back to payment_callback.php.
header('Location: ' . $checkout['authorization_url'], true, 303);
exit;
