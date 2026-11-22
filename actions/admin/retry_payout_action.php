<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/payouts.php');
}
verify_csrf();

$admin_id = current_user_id();
$result = admin_retry_payout_ctr($admin_id, admin_post_id('payout_id'));

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
} else {
    $_SESSION['success'] = 'Payout queued again. It goes out on the next run of the scheduled job.';
}
redirect('views/admin/payouts.php?status=failed');
