<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/users.php');
}
verify_csrf();

$admin_id = current_user_id();
$user_id = admin_post_id('user_id');
$suspend = ($_POST['suspend'] ?? '') === '1';
$result = admin_set_suspended_ctr($admin_id, $user_id, $suspend, $_POST['reason'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = ['reason' => $_POST['reason'] ?? ''];
} else {
    $_SESSION['success'] = $suspend ? 'User suspended.' : 'User unsuspended.';
}
redirect('views/admin/user.php?id=' . $user_id);
