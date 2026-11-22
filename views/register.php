<?php
require_once __DIR__ . '/../core/core.php';

$return = safe_return($_GET['return'] ?? '');

if (is_logged_in()) {
    redirect($return);
}

$errors = flash('errors') ?? [];
$old = flash('old') ?? [];

function field_error($errors, $key) {
    return isset($errors[$key]) ? '<small class="field-error">' . e($errors[$key]) . '</small>' : '';
}

$page_title = 'Sign up | Bidlab';
require __DIR__ . '/layout/header.php';
?>

<div class="auth-wrap">
    <section class="auth-card panel">
        <h1>Create your account</h1>
        <p>Sign up to get started</p>

        <?php if (isset($errors['general'])): ?>
            <p class="error-text" role="alert"><?php echo e($errors['general']); ?></p>
        <?php endif; ?>

        <form id="auth-form" action="<?php echo url('actions/register_user_action.php'); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="return" value="<?php echo e($return); ?>">
            <label class="field"><span>Display name</span>
                <input class="input" name="name" autocomplete="nickname" required minlength="2" maxlength="80" value="<?php echo e($old['name'] ?? ''); ?>">
                <small class="hint">Shown publicly on your bids and listings.</small>
                <?php echo field_error($errors, 'name'); ?>
            </label>
            <label class="field"><span>Email</span>
                <input class="input" name="email" type="email" autocomplete="email" required value="<?php echo e($old['email'] ?? ''); ?>">
                <?php echo field_error($errors, 'email'); ?>
            </label>
            <label class="field"><span>Password</span>
                <input class="input" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="72">
                <?php echo field_error($errors, 'password'); ?>
            </label>
            <label class="field"><span>Confirm password</span>
                <input class="input" name="confirm_password" type="password" autocomplete="new-password" required>
                <?php echo field_error($errors, 'confirm_password'); ?>
            </label>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>
        <p class="auth-switch">Already have an account? <a href="<?php echo url('views/login.php?return=' . urlencode($return)); ?>">Log in</a></p>
    </section>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
