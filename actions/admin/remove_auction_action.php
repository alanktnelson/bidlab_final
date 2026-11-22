<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/auctions.php');
}
verify_csrf();

$admin_id = current_user_id();
// One action for both: do=remove (needs a reason) or do=restore.
$auction_id = admin_post_id('auction_id');
$restore = ($_POST['do'] ?? '') === 'restore';
$result = $restore
    ? admin_restore_auction_ctr($admin_id, $auction_id)
    : admin_remove_auction_ctr($admin_id, $auction_id, $_POST['reason'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = ['auction_id' => $auction_id, 'reason' => $_POST['reason'] ?? ''];
} else {
    $_SESSION['success'] = $restore ? 'Listing restored.' : 'Listing removed.';
}
redirect('views/admin/auctions.php?filter=' . ($restore || isset($result['errors']) ? 'all' : 'removed') . '&q=' . $auction_id);
