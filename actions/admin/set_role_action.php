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
$role = $_POST['role'] ?? '';
$result = admin_set_role_ctr($admin_id, $user_id, $role);

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
} else {
    $_SESSION['success'] = $role === 'admin' ? 'This user is now an admin.' : 'Admin role removed.';
}
redirect('views/admin/user.php?id=' . $user_id);
