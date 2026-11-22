<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/admin/categories.php');
}
verify_csrf();

$admin_id = current_user_id();
// No cat_id = add a new category; with cat_id = rename it.
$cat_id = admin_post_id('cat_id');
$result = admin_save_category_ctr($admin_id, $cat_id ?: null, $_POST['cat_name'] ?? '');

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = ['cat_id' => $cat_id, 'cat_name' => $_POST['cat_name'] ?? ''];
} else {
    $_SESSION['success'] = $cat_id ? 'Category renamed.' : 'Category added.';
}
redirect('views/admin/categories.php');
