<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../layout/escrow_helpers.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$order = admin_get_order_ctr((int) ($_GET['id'] ?? 0));

$page_title = ($order ? 'Order #' . $order['escrow_id'] : 'Order not found') . ' | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'orders';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';

if ($order === null): ?>
    <div class="empty" role="alert">
        <h3>That order does not exist</h3>
        <a class="btn" href="<?php echo url('views/admin/orders.php'); ?>">All orders</a>
    </div>
    <?php require __DIR__ . '/../layout/footer.php';
    exit;
endif;

$id = (int) $order['escrow_id'];
$person = function ($user, $fallback_id) {
    if (!$user) {
        return 'User #' . (int) $fallback_id;
    }
    $html = '<a href="' . url('views/admin/user.php?id=' . (int) $user['user_id']) . '">' . e($user['user_name']) . '</a><br>'
        . '<a href="mailto:' . e($user['user_email']) . '">' . e($user['user_email']) . '</a>';
    if ((int) $user['is_suspended'] === 1) {
        $html .= '<br><span class="status bad">Suspended</span>';
    }
    return $html;
};
$payout_labels = ['pending' => 'Queued', 'processing' => 'Sending', 'sent' => 'Sent', 'failed' => 'Failed'];
?>

<a class="back-link" href="<?php echo url('views/admin/orders.php'); ?>"><?php echo icon('back'); ?>All orders</a>

<div class="page-head">
    <div>
        <h1 class="page-title">Order #<?php echo $id; ?></h1>
        <p class="muted"><a href="<?php echo url('views/single_auction.php?id=' . (int) $order['auction_id']); ?>"><?php echo e($order['title'] ?? 'Listing #' . $order['auction_id']); ?></a></p>
    </div>
    <span class="<?php echo escrow_badge_class($order['status']); ?>"><?php echo e($order['status_label']); ?></span>
</div>

<div class="listing-grid">
    <div class="stack">
        <section class="panel">
            <div class="detail-rows">
                <dl>
                    <div class="detail-row"><dt>Buyer</dt><dd><?php echo $person($order['buyer'], $order['buyer_id']); ?></dd></div>
                    <div class="detail-row"><dt>Seller</dt><dd><?php echo $person($order['seller'], $order['seller_id']); ?></dd></div>
                    <div class="detail-row"><dt>Handover</dt><dd><?php echo $order['handover'] === 'pickup' ? 'Pickup' : 'Delivery'; ?></dd></div>
                    <div class="detail-row"><dt>Winning bid</dt><dd><?php echo e(money($order['item_amount_minor'])); ?></dd></div>
                    <div class="detail-row"><dt>Delivery fee</dt><dd><?php echo e(money($order['delivery_fee_minor'])); ?></dd></div>
                    <div class="detail-row"><dt>Buyer pays</dt><dd><strong><?php echo e(money($order['total_minor'])); ?></strong></dd></div>
                    <div class="detail-row"><dt>Bidlab fee</dt><dd><?php echo e(money($order['platform_fee_minor'])); ?></dd></div>
                    <div class="detail-row"><dt>Seller receives</dt><dd><?php echo e(money($order['payout_minor'])); ?></dd></div>
                    <div class="detail-row"><dt>Payment reference</dt><dd><?php echo e($order['payment_reference'] ?: '—'); ?></dd></div>
                    <div class="detail-row"><dt>Refund reference</dt><dd><?php echo e($order['refund_reference'] ?: '—'); ?></dd></div>
                    <div class="detail-row"><dt>Pay by</dt><dd><?php echo escrow_time($order['pay_by']); ?></dd></div>
                    <?php if ($order['auto_release_at']): ?>
                        <div class="detail-row"><dt>Auto-release</dt><dd><?php echo escrow_time($order['auto_release_at']); ?></dd></div>
                    <?php endif; ?>
                </dl>
            </div>
        </section>

        <?php foreach ($order['disputes'] as $dispute): ?>
            <section class="panel">
                <div class="section-head">
                    <h2>Dispute #<?php echo (int) $dispute['dispute_id']; ?></h2>
                    <?php if ($dispute['status'] === 'open'): ?>
                        <a class="btn" href="<?php echo url('views/admin/disputes.php'); ?>">Decide</a>
                    <?php else: ?>
                        <span class="status"><?php echo $dispute['status'] === 'resolved_refund' ? 'Buyer refunded' : 'Seller paid'; ?></span>
                    <?php endif; ?>
                </div>
                <p><strong><?php echo e(DISPUTE_REASONS[$dispute['reason']] ?? $dispute['reason']); ?></strong> · <?php echo escrow_time($dispute['created_at']); ?></p>
                <p class="description"><?php echo e($dispute['details']); ?></p>
                <?php if ($dispute['resolution_note']): ?><p><strong>Decision:</strong> <?php echo e($dispute['resolution_note']); ?></p><?php endif; ?>
            </section>
        <?php endforeach; ?>

        <section class="panel">
            <div class="section-head"><h2>History</h2></div>
            <ul class="order-history">
                <?php foreach (array_reverse($order['events']) as $event): ?>
                    <li>
                        <span><?php echo e($event['note'] ?: escrow_status_label($event['to_status'])); ?>
                            <small>(<?php echo $event['actor_id'] === null ? 'system' : 'user #' . (int) $event['actor_id']; ?>)</small></span>
                        <small><?php echo escrow_time($event['created_at']); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <aside class="side-stack">
        <?php if ($order['payout']): $payout = $order['payout']; ?>
            <section class="panel">
                <div class="mono-label">Payout to seller</div>
                <div class="price-big"><?php echo e(money($payout['amount_minor'])); ?></div>
                <p><span class="<?php echo $payout['status'] === 'sent' ? 'status good' : ($payout['status'] === 'failed' ? 'status bad' : 'status wait'); ?>"><?php echo $payout_labels[$payout['status']]; ?></span></p>
                <?php if ($payout['failure_reason']): ?><p class="field-error"><?php echo e($payout['failure_reason']); ?></p><?php endif; ?>
                <?php if ($payout['transfer_reference']): ?><p class="muted"><?php echo e($payout['transfer_reference']); ?></p><?php endif; ?>
                <?php if (in_array($payout['status'], ['failed', 'processing'], true)): ?>
                    <a class="btn btn-block" href="<?php echo url('views/admin/payouts.php?status=' . $payout['status']); ?>">Handle payout</a>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($order['can']['cancel']): ?>
            <section class="panel">
                <h3>Cancel this order</h3>
                <p class="muted">The buyer has not paid. If a payment arrives after cancelling, it is refunded automatically.</p>
                <form action="<?php echo url('actions/admin/cancel_order_action.php'); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="escrow_id" value="<?php echo $id; ?>">
                    <label class="field"><span>Reason</span>
                        <input class="input" name="reason" required minlength="5" maxlength="255" value="<?php echo e($old['reason'] ?? ''); ?>">
                        <?php if (isset($admin_errors['reason'])): ?><small class="field-error"><?php echo e($admin_errors['reason']); ?></small><?php endif; ?>
                    </label>
                    <button class="btn btn-block admin-danger" type="submit">Cancel order</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($order['can']['refund']): ?>
            <section class="panel">
                <h3>Refund the buyer</h3>
                <p class="muted">Sends <?php echo e(money($order['total_minor'])); ?> back through Paystack. The seller is not paid. <?php echo $order['status'] === 'disputed' ? 'This also closes the open dispute.' : ''; ?></p>
                <form action="<?php echo url('actions/admin/refund_order_action.php'); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="escrow_id" value="<?php echo $id; ?>">
                    <label class="field"><span>Reason (both people see this)</span>
                        <input class="input" name="reason" required minlength="5" maxlength="255" value="<?php echo e($old['reason'] ?? ''); ?>">
                        <?php if (isset($admin_errors['reason'])): ?><small class="field-error"><?php echo e($admin_errors['reason']); ?></small><?php endif; ?>
                    </label>
                    <button class="btn btn-block admin-danger" type="submit">Refund <?php echo e(money($order['total_minor'])); ?></button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($order['can']['record_refund']): ?>
            <section class="panel">
                <h3>Refund not finished</h3>
                <p class="muted">This order was refunded but Paystack did not confirm it. Refund payment <strong><?php echo e($order['payment_reference']); ?></strong> in the Paystack dashboard, then enter the refund reference here.</p>
                <form action="<?php echo url('actions/admin/mark_refund_done_action.php'); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="escrow_id" value="<?php echo $id; ?>">
                    <label class="field"><span>Paystack refund reference</span>
                        <input class="input" name="refund_reference" required minlength="3" maxlength="100">
                        <?php if (isset($admin_errors['refund_reference'])): ?><small class="field-error"><?php echo e($admin_errors['refund_reference']); ?></small><?php endif; ?>
                    </label>
                    <button class="btn btn-primary btn-block" type="submit">Mark refund done</button>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($order['log']): ?>
            <section class="panel">
                <div class="mono-label">Admin actions on this order</div>
                <ul class="order-history">
                    <?php foreach ($order['log'] as $entry): ?>
                        <li><span><?php echo e($entry['admin_name'] ?? '#' . $entry['admin_id']); ?>: <?php echo e($entry['action']); ?> – <?php echo e($entry['details']); ?></span><small><?php echo escrow_time($entry['created_at']); ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
