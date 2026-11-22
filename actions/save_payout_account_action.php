<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../core/payment_gateway.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/payout_settings.php');
}
verify_csrf();

$seller_id = current_user_id();
$fields = ['method', 'provider', 'account_name', 'account_number'];
$result = save_payout_account_ctr($seller_id, $_POST);

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = array_intersect_key($_POST, array_flip($fields));
    redirect('views/payout_settings.php');
}

// Register the account with Paystack now, so a wrong number is caught here and not when a payout is due.
$account = get_payout_account_ctr($seller_id);
$recipient = paystack_create_recipient($account);

if ($recipient['ok']) {
    save_recipient_code_ctr($seller_id, $recipient['recipient_code']);
    $_SESSION['success'] = 'Payout details saved.';
} elseif ($recipient['network']) {
    // Saved; the scheduled job will register it with Paystack before the first payout.
    $_SESSION['success'] = 'Payout details saved.';
} else {
    $_SESSION['errors'] = ['general' => 'Saved, but Paystack could not verify this account: ' . $recipient['error']
        . ' Please check the details.'];
    $_SESSION['old'] = array_intersect_key($_POST, array_flip($fields));
}
redirect('views/payout_settings.php');
