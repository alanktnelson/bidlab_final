<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/categories.php');
}
verify_csrf();

$admin_id = current_user_id();
$result = admin_delete_category_ctr($admin_id, admin_post_id('cat_id'));

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
} else {
    $_SESSION['success'] = 'Category deleted.';
}
redirect('views/admin/categories.php');
