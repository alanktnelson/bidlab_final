<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/auction_controller.php';
require_once __DIR__ . '/../controllers/escrow_controller.php';
require_once __DIR__ . '/layout/escrow_helpers.php';

require_login();

$viewer_id = current_user_id();
$orders = get_buyer_escrows_ctr($viewer_id);

// Item title and photo for each order.
foreach ($orders as &$order) {
    $auction = get_auction_details_ctr((int) $order['auction_id'], $viewer_id);
    $order['title'] = $auction['title'] ?? 'Item #' . $order['auction_id'];
    $order['photo'] = $auction['photos'][0] ?? '';
}
unset($order);

$to_do = array_filter($orders, function ($order) {
    return in_array($order['status'], ['awaiting_payment', 'funds_held', 'shipped', 'disputed'], true);
});
$done = array_filter($orders, function ($order) {
    return !in_array($order['status'], ['awaiting_payment', 'funds_held', 'shipped', 'disputed'], true);
});
$needs_payment = count(array_filter($orders, function ($order) {
    return $order['status'] === 'awaiting_payment' && strtotime($order['pay_by'] . ' UTC') > time();
}));

$page_title = 'Purchases | Bidlab';
$active_tab = 'purchases';
require __DIR__ . '/layout/header.php';

function purchase_rows(array $orders)
{
    foreach ($orders as $order): ?>
        <a class="order-row" href="<?php echo url('views/order.php?id=' . (int) $order['escrow_id']); ?>">
            <img src="<?php echo photo_url($order['photo']); ?>" alt="">
            <div class="order-meta">
                <strong><?php echo e($order['title']); ?></strong>
                <span class="muted"><?php echo e(escrow_next_step($order, 'buyer')); ?></span>
                <?php if ($order['status'] === 'awaiting_payment'): ?>
                    <small class="muted">Pay by <?php echo escrow_time($order['pay_by']); ?></small>
                <?php endif; ?>
            </div>
            <div class="order-side">
                <span class="amount"><?php echo e(money($order['total_minor'])); ?></span>
                <span class="<?php echo escrow_badge_class($order['status']); ?>"><?php echo e($order['status_label']); ?></span>
            </div>
        </a>
    <?php endforeach;
}
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Purchases</h1>
        <p class="muted"><?php echo plural(count($orders), 'order'); ?><?php echo $needs_payment ? ' · ' . $needs_payment . ' to pay' : ''; ?></p>
    </div>
</div>

<?php if (!$orders): ?>
    <div class="empty">
        <h3>No purchases yet</h3>
        <p>Auctions you win appear here. Bidlab holds your payment until you confirm you have the item.</p>
        <a class="btn" href="<?php echo url('index.php'); ?>">Browse the market</a>
    </div>
<?php else: ?>
    <?php if ($to_do): ?>
        <section class="panel">
            <div class="section-head"><h2>In progress</h2></div>
            <div class="order-list"><?php purchase_rows($to_do); ?></div>
        </section>
    <?php endif; ?>
    <?php if ($done): ?>
        <section class="panel">
            <div class="section-head"><h2>Finished</h2></div>
            <div class="order-list"><?php purchase_rows($done); ?></div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/layout/footer.php'; ?>
