<?php
require_once __DIR__ . '/../core/core.php';

$return = safe_return($_GET['return'] ?? '');

if (is_logged_in()) {
    redirect($return);
}

$errors = flash('errors') ?? [];
$old = flash('old') ?? [];

$page_title = 'Log in | Bidlab';
require __DIR__ . '/layout/header.php';
?>

<div class="auth-wrap">
    <section class="auth-card panel">
        <h1>Welcome back</h1>
        <p>Log in to your account</p>

        <?php if (isset($errors['general'])): ?>
            <p class="error-text" id="auth-error" role="alert"><?php echo e($errors['general']); ?></p>
        <?php endif; ?>

        <form id="auth-form" action="<?php echo url('actions/login_user_action.php'); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="return" value="<?php echo e($return); ?>">
            <label class="field"><span>Email</span><input class="input" name="email" type="email" autocomplete="email" required value="<?php echo e($old['email'] ?? ''); ?>"></label>
            <label class="field"><span>Password</span><input class="input" name="password" type="password" autocomplete="current-password" required></label>
            <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>
        <p class="auth-switch">Don't have an account? <a href="<?php echo url('views/register.php?return=' . urlencode($return)); ?>">Create one</a></p>
    </section>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
