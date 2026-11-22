<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../controllers/user_controller.php';

require_login();

$user = get_user_ctr(current_user_id());

$page_title = 'Account | Bidlab';
$active_tab = 'account';
require __DIR__ . '/../layout/header.php';
?>

<div class="page-head"><h1 class="page-title">Account</h1></div>

<section class="panel auth-card">
    <div class="seller-row">
        <span class="avatar" aria-hidden="true"><?php echo e(initials($user['user_name'])); ?></span>
        <strong><?php echo e($user['user_name']); ?></strong>
    </div>
    <div class="detail-rows account-details">
        <dl>
            <div class="detail-row"><dt>Email</dt><dd id="user-email"><?php echo e($user['user_email']); ?></dd></div>
            <div class="detail-row"><dt>Member since</dt><dd><?php echo e(date('j F Y', strtotime($user['created_at'] . ' UTC'))); ?></dd></div>
        </dl>
    </div>
    <form action="<?php echo url('logout.php'); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <button class="btn btn-block" id="logout" type="submit">Log out</button>
    </form>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
