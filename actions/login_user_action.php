<?php
require_once '../core/core.php';
require_once '../controllers/user_controller.php';
require_once '../core/admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/login.php');
}
verify_csrf();

$return = safe_return($_POST['return'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));

$user = login_user_ctr($email, $_POST['password'] ?? '');

if ($user === null) {
    $_SESSION['errors'] = ['general' => 'That email and password do not match.'];
    $_SESSION['old'] = ['email' => $email];
    redirect('views/login.php?return=' . urlencode($return));
}

if (is_suspended_ctr($user['user_id'])) {
    $_SESSION['errors'] = ['general' => 'Your account is suspended. Contact Bidlab if you think this is a mistake.'];
    $_SESSION['old'] = ['email' => $email];
    redirect('views/login.php?return=' . urlencode($return));
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['user_name'] = $user['user_name'];
redirect($return);
?>
