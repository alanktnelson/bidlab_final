<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../core/payment_gateway.php';

require_login();

$seller_id = current_user_id();
$errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$saved = get_payout_account_ctr($seller_id);
$payouts = get_seller_payouts_ctr($seller_id);

// Form values: what was just typed (after an error), else what is saved.
$value = function ($key, $default = '') use ($old, $saved) {
    return $old[$key] ?? $saved[$key] ?? $default;
};
$method = $value('method', 'mobile_money');
$provider = $value('provider');

$banks = paystack_list_banks();
$bank_list = $banks['ok'] ? $banks['banks'] : [];

$payout_labels = ['pending' => 'Queued', 'processing' => 'Sending', 'sent' => 'Sent', 'failed' => 'Failed'];
$payout_classes = ['pending' => 'status wait', 'processing' => 'status wait', 'sent' => 'status good', 'failed' => 'status bad'];

$page_title = 'Payout details | Bidlab';
$active_tab = 'selling';
require __DIR__ . '/layout/header.php';
require_once __DIR__ . '/layout/escrow_helpers.php';
?>

<a class="back-link" href="<?php echo url('views/selling.php'); ?>"><?php echo icon('back'); ?>Back to selling</a>

<section class="panel">
    <div class="section-head"><h1 class="page-title">Payout details</h1></div>
    <p class="muted">Where Bidlab sends your money after a buyer confirms they have the item. Bidlab keeps <?php echo ESCROW_PLATFORM_FEE_PERCENT; ?>% of the winning bid.</p>

    <?php if (isset($errors['general'])): ?>
        <p class="notice-bar bad" role="alert"><?php echo e($errors['general']); ?></p>
    <?php endif; ?>
    <?php if ($saved && !$saved['recipient_code'] && !isset($errors['general'])): ?>
        <p class="notice-bar bad" role="alert">These details have not been verified yet. Check them and save again.</p>
    <?php endif; ?>

    <form id="payout-form" action="<?php echo url('actions/save_payout_account_action.php'); ?>" method="POST">
        <?php echo csrf_field(); ?>

        <fieldset>
            <legend>Get paid by</legend>
            <label class="check"><input type="radio" name="method" value="mobile_money" required<?php echo $method === 'mobile_money' ? ' checked' : ''; ?>> Mobile money</label>
            <label class="check"><input type="radio" name="method" value="bank"<?php echo $method === 'bank' ? ' checked' : ''; ?>> Bank account</label>
            <?php if (isset($errors['method'])): ?><small class="field-error"><?php echo e($errors['method']); ?></small><?php endif; ?>
        </fieldset>

        <div class="form-grid">
            <label class="field" data-for="mobile_money"<?php echo $method === 'bank' ? ' hidden' : ''; ?>><span>Network</span>
                <select class="input" name="provider"<?php echo $method === 'bank' ? ' disabled' : ''; ?>>
                    <?php foreach (MOBILE_MONEY_PROVIDERS as $network): ?>
                        <option value="<?php echo e($network); ?>"<?php echo $provider === $network ? ' selected' : ''; ?>><?php echo e($network); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field" data-for="bank"<?php echo $method === 'bank' ? '' : ' hidden'; ?>><span>Bank</span>
                <select class="input" name="provider"<?php echo $method === 'bank' ? '' : ' disabled'; ?>>
                    <option value="">Choose your bank</option>
                    <?php foreach ($bank_list as $code => $name): ?>
                        <option value="<?php echo e($code); ?>"<?php echo (string) $provider === (string) $code ? ' selected' : ''; ?>><?php echo e($name); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$banks['ok']): ?><small class="field-error">Could not load the bank list. Use mobile money or try again later.</small><?php endif; ?>
            </label>
            <?php if (isset($errors['provider'])): ?><small class="field-error"><?php echo e($errors['provider']); ?></small><?php endif; ?>

            <label class="field"><span>Name on the account</span>
                <input class="input" name="account_name" required minlength="2" maxlength="120" value="<?php echo e($value('account_name')); ?>">
                <?php if (isset($errors['account_name'])): ?><small class="field-error"><?php echo e($errors['account_name']); ?></small><?php endif; ?>
            </label>

            <label class="field"><span id="number-label"><?php echo $method === 'bank' ? 'Account number' : 'Mobile money number'; ?></span>
                <input class="input" name="account_number" required inputmode="numeric" maxlength="30" placeholder="<?php echo $method === 'bank' ? '' : '0241234567'; ?>" value="<?php echo e($value('account_number')); ?>">
                <?php if (isset($errors['account_number'])): ?><small class="field-error"><?php echo e($errors['account_number']); ?></small><?php endif; ?>
            </label>
        </div>

        <div class="hero-actions form-actions">
            <button class="btn btn-primary btn-lg" type="submit">Save payout details</button>
        </div>
    </form>
</section>

<?php if ($payouts): ?>
    <section class="panel">
        <div class="section-head"><h2>Payouts</h2><span class="mono-label"><?php echo plural(count($payouts), 'payout'); ?></span></div>
        <?php foreach ($payouts as $payout): ?>
            <div class="bid-row">
                <div>
                    <a href="<?php echo url('views/order.php?id=' . (int) $payout['escrow_id']); ?>">Order #<?php echo (int) $payout['escrow_id']; ?></a>
                    <small><?php echo escrow_time($payout['sent_at'] ?: $payout['created_at']); ?></small>
                    <?php if ($payout['status'] === 'failed'): ?><small class="field-error"><?php echo e($payout['failure_reason']); ?></small><?php endif; ?>
                </div>
                <div>
                    <span class="<?php echo $payout_classes[$payout['status']]; ?>"><?php echo $payout_labels[$payout['status']]; ?></span>
                    <span class="amount"><?php echo e(money((int) $payout['amount_minor'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>


<?php require __DIR__ . '/layout/footer.php'; ?>
