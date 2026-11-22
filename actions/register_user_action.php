<?php
require_once '../core/core.php';
require_once '../controllers/user_controller.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/register.php');
}
verify_csrf();

$return = safe_return($_POST['return'] ?? '');
$data = [
    'name' => trim(strip_tags($_POST['name'] ?? '')),
    'email' => strtolower(trim($_POST['email'] ?? '')),
    'password' => $_POST['password'] ?? '',
    'confirm_password' => $_POST['confirm_password'] ?? '',
];

$errors = register_user_ctr($data);

if ($errors) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old'] = ['name' => $data['name'], 'email' => $data['email']];
    redirect('views/register.php?return=' . urlencode($return));
}

$user = login_user_ctr($data['email'], $data['password']);
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['user_name'] = $user['user_name'];
redirect($return);
?>
