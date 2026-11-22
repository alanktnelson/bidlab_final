<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/auction_controller.php';
require_once __DIR__ . '/../controllers/dispute_controller.php';
require_once __DIR__ . '/layout/escrow_helpers.php';

require_login();

$viewer_id = current_user_id();
$escrow = get_escrow_ctr((int) ($_GET['id'] ?? 0), $viewer_id);

if ($escrow === null) {
    http_response_code(404);
    $page_title = 'Order not found | Bidlab';
    $active_tab = 'purchases';
    require __DIR__ . '/layout/header.php';
    ?>
    <div class="empty" role="alert">
        <h3>That order does not exist</h3>
        <p>It may belong to someone else, or the link may be wrong.</p>
        <a class="btn" href="<?php echo url('views/purchases.php'); ?>">Your purchases</a>
    </div>
    <?php
    require __DIR__ . '/layout/footer.php';
    exit;
}

$is_buyer = $escrow['role'] === 'buyer';
$auction = get_auction_details_ctr((int) $escrow['auction_id'], $viewer_id);
$title = $auction['title'] ?? 'Item #' . $escrow['auction_id'];
$contact = $auction['contact'] ?? null;          // the other person in the sale
$disputes = get_escrow_disputes_ctr((int) $escrow['escrow_id']);
$payout_account = $is_buyer ? null : get_payout_account_ctr($viewer_id);

$errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$status = $escrow['status'];
$is_pickup = $escrow['handover'] === 'pickup';
$paid = !in_array($status, ['awaiting_payment', 'cancelled'], true);

// Progress steps: [label, done?]
$reached = ['awaiting_payment' => 0, 'cancelled' => 0, 'funds_held' => 1, 'shipped' => 2, 'disputed' => 2, 'released' => 4, 'refunded' => 1];
$level = $reached[$status] ?? 0;
if ($status === 'disputed' && !$escrow['shipped_at']) {
    $level = 1;
}
$steps = [
    ['Paid', $level >= 1],
    [$is_pickup ? 'Ready for pickup' : 'Sent', $level >= 2],
    ['Received', $level >= 4],
    ['Seller paid', $level >= 4],
];

$page_title = 'Order #' . $escrow['escrow_id'] . ' | Bidlab';
$active_tab = $is_buyer ? 'purchases' : 'selling';
require __DIR__ . '/layout/header.php';
?>

<a class="back-link" href="<?php echo url($is_buyer ? 'views/purchases.php' : 'views/selling.php'); ?>"><?php echo icon('back'); ?><?php echo $is_buyer ? 'Back to purchases' : 'Back to selling'; ?></a>

<div class="page-head">
    <div>
        <h1 class="page-title">Order #<?php echo (int) $escrow['escrow_id']; ?></h1>
        <p class="muted"><?php echo e(escrow_next_step($escrow, $escrow['role'])); ?></p>
    </div>
    <span class="<?php echo escrow_badge_class($status); ?>"><?php echo e($escrow['status_label']); ?></span>
</div>

<?php if (isset($errors['general'])): ?>
    <p class="notice-bar bad" role="alert"><?php echo e($errors['general']); ?></p>
<?php endif; ?>

<?php if (!$is_buyer && !$payout_account && in_array($status, ['awaiting_payment', 'funds_held', 'shipped', 'disputed', 'released'], true)): ?>
    <p class="notice-bar bad" role="alert">Add your mobile money or bank details so we can pay you. <a href="<?php echo url('views/payout_settings.php'); ?>">Add payout details</a></p>
<?php endif; ?>

<div class="listing-grid">
    <div class="stack">
        <section class="panel">
            <div class="order-row plain">
                <img src="<?php echo photo_url($auction['photos'][0] ?? ''); ?>" alt="">
                <div class="order-meta">
                    <strong><a href="<?php echo url('views/single_auction.php?id=' . (int) $escrow['auction_id']); ?>"><?php echo e($title); ?></a></strong>
                    <span class="muted"><?php echo $is_pickup ? 'Pickup' : 'Delivery'; ?><?php echo isset($auction['location']) ? ' · ' . e($auction['location']) : ''; ?></span>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="section-head"><h2>Progress</h2></div>
            <?php if (in_array($status, ['cancelled', 'refunded', 'disputed'], true)): ?>
                <p class="muted"><?php echo e(escrow_next_step($escrow, $escrow['role'])); ?>.</p>
            <?php endif; ?>
            <ol class="order-steps">
                <?php foreach ($steps as [$label, $done]): ?>
                    <li class="<?php echo $done ? 'done' : ''; ?>"><?php echo $done ? '✓ ' : ''; ?><?php echo e($label); ?></li>
                <?php endforeach; ?>
            </ol>
        </section>

        <?php foreach ($disputes as $dispute): ?>
            <section class="panel">
                <div class="section-head">
                    <h2>Reported problem</h2>
                    <span class="<?php echo $dispute['status'] === 'open' ? 'status wait' : 'status'; ?>">
                        <?php echo $dispute['status'] === 'open' ? 'Under review' : ($dispute['status'] === 'resolved_refund' ? 'Buyer refunded' : 'Seller paid'); ?>
                    </span>
                </div>
                <p><strong><?php echo e(DISPUTE_REASONS[$dispute['reason']] ?? $dispute['reason']); ?></strong></p>
                <p class="description"><?php echo e($dispute['details']); ?></p>
                <p class="muted">Reported <?php echo escrow_time($dispute['created_at']); ?></p>
                <?php if ($dispute['resolution_note']): ?>
                    <p><strong>Bidlab's decision:</strong> <?php echo e($dispute['resolution_note']); ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <section class="panel">
            <div class="section-head"><h2>History</h2></div>
            <ul class="order-history">
                <?php foreach (array_reverse($escrow['events']) as $event): ?>
                    <li><span><?php echo e($event['note'] ?: escrow_status_label($event['to_status'])); ?></span><small><?php echo escrow_time($event['created_at']); ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <aside class="side-stack">
        <section class="panel">
            <div class="mono-label"><?php echo $is_buyer ? 'You pay' : 'You receive'; ?></div>
            <div class="price-big"><?php echo e(money($is_buyer ? $escrow['total_minor'] : $escrow['payout_minor'])); ?></div>
            <div class="money-rows">
                <div><span>Winning bid</span><span><?php echo e(money($escrow['item_amount_minor'])); ?></span></div>
                <?php if (!$is_pickup): ?>
                    <div><span>Delivery</span><span><?php echo e(money($escrow['delivery_fee_minor'])); ?></span></div>
                <?php endif; ?>
                <?php if ($is_buyer): ?>
                    <div class="total"><span>Total</span><span><?php echo e(money($escrow['total_minor'])); ?></span></div>
                <?php else: ?>
                    <div><span>Bidlab fee (<?php echo ESCROW_PLATFORM_FEE_PERCENT; ?>%)</span><span>−<?php echo e(money($escrow['platform_fee_minor'])); ?></span></div>
                    <div class="total"><span>You receive</span><span><?php echo e(money($escrow['payout_minor'])); ?></span></div>
                <?php endif; ?>
            </div>

            <div class="action-box">
                <?php if ($escrow['can']['pay']): ?>
                    <h3>Pay securely</h3>
                    <p>Bidlab holds your money and only pays the seller after you confirm you have the item. Pay by <?php echo escrow_time($escrow['pay_by']); ?> or the sale is cancelled.</p>
                    <form action="<?php echo url('actions/start_payment_action.php'); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="escrow_id" value="<?php echo (int) $escrow['escrow_id']; ?>">
                        <button class="btn btn-primary btn-block" type="submit">Pay <?php echo e(money($escrow['total_minor'])); ?></button>
                    </form>
                    <p class="muted">Mobile money or card, through Paystack.</p>

                <?php elseif ($status === 'awaiting_payment'): ?>
                    <h3><?php echo $is_buyer ? 'Payment time has passed' : 'Waiting for payment'; ?></h3>
                    <p>The buyer has until <?php echo escrow_time($escrow['pay_by']); ?> to pay. Do not hand over the item until this order says it is paid.</p>

                <?php elseif ($escrow['can']['mark_shipped']): ?>
                    <h3>The buyer has paid</h3>
                    <p>Bidlab is holding the money. <?php echo $is_pickup ? 'Arrange a pickup time with the buyer, then let them know it is ready.' : 'Send the item, then mark it as sent.'; ?></p>
                    <form action="<?php echo url('actions/mark_shipped_action.php'); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="escrow_id" value="<?php echo (int) $escrow['escrow_id']; ?>">
                        <button class="btn btn-primary btn-block" type="submit"><?php echo $is_pickup ? 'Mark ready for pickup' : 'Mark as sent'; ?></button>
                    </form>

                <?php elseif ($escrow['can']['confirm_receipt']): ?>
                    <h3><?php echo $status === 'shipped' ? ($is_pickup ? 'Ready for pickup' : 'On its way') : 'Paid – money held safely'; ?></h3>
                    <p>Only confirm once you have the item and it is as described. This pays the seller and cannot be undone.</p>
                    <?php if ($status === 'shipped' && $escrow['auto_release_at']): ?>
                        <p class="muted">If you do nothing, the seller is paid automatically on <?php echo escrow_time($escrow['auto_release_at']); ?>.</p>
                    <?php endif; ?>
                    <form action="<?php echo url('actions/confirm_receipt_action.php'); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="escrow_id" value="<?php echo (int) $escrow['escrow_id']; ?>">
                        <button class="btn btn-primary btn-block" type="submit">I have the item</button>
                    </form>

                <?php elseif ($status === 'shipped'): ?>
                    <h3>Waiting for the buyer</h3>
                    <p>You will be paid when the buyer confirms, or automatically on <?php echo escrow_time($escrow['auto_release_at']); ?>.</p>

                <?php elseif ($status === 'disputed'): ?>
                    <h3>On hold</h3>
                    <p>The buyer reported a problem. The money stays with Bidlab until we decide. We may email you both.</p>

                <?php elseif ($status === 'released'): ?>
                    <h3>Completed</h3>
                    <p><?php echo $is_buyer ? 'The seller has been paid. Enjoy your item.' : 'Your payout is on its way to your ' . ($payout_account && $payout_account['method'] === 'bank' ? 'bank account.' : 'mobile money wallet.'); ?></p>

                <?php elseif ($status === 'refunded'): ?>
                    <h3>Refunded</h3>
                    <p><?php echo $is_buyer ? 'Your money is being returned. Refunds can take a few days to show.' : 'The buyer was refunded after a review.'; ?></p>

                <?php else: ?>
                    <h3>Cancelled</h3>
                    <p>The buyer did not pay in time, so this sale was cancelled.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($paid && $contact): ?>
            <section class="panel">
                <div class="mono-label"><?php echo $is_buyer ? 'Seller' : 'Buyer'; ?></div>
                <p><?php echo e($contact['user_name']); ?><br><a href="mailto:<?php echo e($contact['user_email']); ?>"><?php echo e($contact['user_email']); ?></a></p>
                <p class="muted">Use this to arrange <?php echo $is_pickup ? 'pickup' : 'delivery'; ?>. Never pay outside Bidlab.</p>
            </section>
        <?php endif; ?>

        <?php if ($escrow['can']['open_dispute']): ?>
            <section class="panel" id="dispute">
                <div class="section-head"><h2>Something wrong?</h2></div>
                <p class="muted">Reporting a problem stops the seller being paid until Bidlab reviews it.</p>
                <form action="<?php echo url('actions/open_dispute_action.php'); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="escrow_id" value="<?php echo (int) $escrow['escrow_id']; ?>">
                    <label class="field"><span>What happened?</span>
                        <select class="input" name="reason" required>
                            <option value="">Choose one</option>
                            <?php foreach (DISPUTE_REASONS as $value => $label): ?>
                                <option value="<?php echo $value; ?>"<?php echo ($old['reason'] ?? '') === $value ? ' selected' : ''; ?>><?php echo e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['reason'])): ?><small class="field-error"><?php echo e($errors['reason']); ?></small><?php endif; ?>
                    </label>
                    <label class="field"><span>Details</span>
                        <textarea class="input" name="details" required minlength="10" maxlength="2000"><?php echo e($old['details'] ?? ''); ?></textarea>
                        <?php if (isset($errors['details'])): ?><small class="field-error"><?php echo e($errors['details']); ?></small><?php endif; ?>
                    </label>
                    <button class="btn btn-block" type="submit">Report a problem</button>
                </form>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
