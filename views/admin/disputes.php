<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../../controllers/auction_controller.php';
require_once __DIR__ . '/../layout/escrow_helpers.php';

require_admin();

$errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$disputes = get_open_disputes_ctr();
$stuck_payouts = get_stuck_payouts_ctr();

$page_title = 'Disputes | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'disputes';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Disputes</h1>
        <p class="muted"><?php echo plural(count($disputes), 'open dispute'); ?>, oldest first</p>
    </div>
</div>

<?php if (isset($errors['general'])): ?>
    <p class="notice-bar bad" role="alert"><?php echo e($errors['general']); ?></p>
<?php endif; ?>

<?php if (!$disputes): ?>
    <div class="empty">
        <h3>No open disputes</h3>
        <p>When a buyer reports a problem, it appears here and the money stays on hold.</p>
    </div>
<?php endif; ?>

<?php foreach ($disputes as $dispute):
    $auction = get_auction_details_ctr((int) $dispute['auction_id'], null);
    $is_this_form = (int) ($old['dispute_id'] ?? 0) === (int) $dispute['dispute_id'];
    ?>
    <section class="panel dispute-card">
        <div class="section-head">
            <h2><a href="<?php echo url('views/admin/order.php?id=' . (int) $dispute['escrow_id']); ?>">Order #<?php echo (int) $dispute['escrow_id']; ?></a>: <?php echo e($auction['title'] ?? 'Item #' . $dispute['auction_id']); ?></h2>
            <span class="status wait"><?php echo e(DISPUTE_REASONS[$dispute['reason']] ?? $dispute['reason']); ?></span>
        </div>

        <div class="detail-rows">
            <dl>
                <div class="detail-row"><dt>Amount held</dt><dd><?php echo e(money($dispute['total_minor'])); ?></dd></div>
                <div class="detail-row"><dt>Buyer</dt><dd><?php echo e(get_user_email_ctr($dispute['buyer_id']) ?? '#' . $dispute['buyer_id']); ?></dd></div>
                <div class="detail-row"><dt>Seller</dt><dd><?php echo e(get_user_email_ctr($dispute['seller_id']) ?? '#' . $dispute['seller_id']); ?></dd></div>
                <div class="detail-row"><dt>Handover</dt><dd><?php echo $dispute['handover'] === 'pickup' ? 'Pickup' : 'Delivery'; ?></dd></div>
                <div class="detail-row"><dt>Paid</dt><dd><?php echo escrow_time($dispute['paid_at']); ?></dd></div>
                <div class="detail-row"><dt>Marked sent</dt><dd><?php echo $dispute['shipped_at'] ? escrow_time($dispute['shipped_at']) : 'Not yet'; ?></dd></div>
                <div class="detail-row"><dt>Reported</dt><dd><?php echo escrow_time($dispute['created_at']); ?></dd></div>
            </dl>
        </div>

        <p class="description"><?php echo e($dispute['details']); ?></p>
        <p><a href="<?php echo url('views/single_auction.php?id=' . (int) $dispute['auction_id']); ?>" target="_blank" rel="noopener">View the listing</a></p>

        <form action="<?php echo url('actions/resolve_dispute_action.php'); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="dispute_id" value="<?php echo (int) $dispute['dispute_id']; ?>">
            <fieldset>
                <legend>Decision</legend>
                <label class="check"><input type="radio" name="outcome" value="refund" required<?php echo $is_this_form && ($old['outcome'] ?? '') === 'refund' ? ' checked' : ''; ?>> Refund the buyer <?php echo e(money($dispute['total_minor'])); ?></label>
                <label class="check"><input type="radio" name="outcome" value="release"<?php echo $is_this_form && ($old['outcome'] ?? '') === 'release' ? ' checked' : ''; ?>> Pay the seller</label>
            </fieldset>
            <label class="field"><span>Reason (both people see this)</span>
                <textarea class="input" name="note" required minlength="5" maxlength="1000"><?php echo $is_this_form ? e($old['note'] ?? '') : ''; ?></textarea>
                <?php if ($is_this_form && isset($errors['note'])): ?><small class="field-error"><?php echo e($errors['note']); ?></small><?php endif; ?>
            </label>
            <button class="btn btn-primary" type="submit">Save decision</button>
        </form>
    </section>
<?php endforeach; ?>

<?php if ($stuck_payouts): ?>
    <section class="panel">
        <div class="section-head"><h2>Payouts waiting on Paystack</h2></div>
        <p class="muted">Paystack has not confirmed these yet. If one has waited more than a day, look up its reference in the Paystack dashboard.</p>
        <?php foreach ($stuck_payouts as $payout): ?>
            <div class="bid-row">
                <div><span>Order #<?php echo (int) $payout['escrow_id']; ?></span><small><?php echo e($payout['transfer_reference']); ?></small></div>
                <span class="amount"><?php echo e(money((int) $payout['amount_minor'])); ?></span>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
