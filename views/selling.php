<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/auction_controller.php';
require_once __DIR__ . '/../controllers/escrow_controller.php';
require_once __DIR__ . '/layout/escrow_helpers.php';

require_login();

$seller_id = current_user_id();
$auctions = get_seller_auctions_ctr($seller_id);
$sales = [];   // escrow orders that need the seller's attention
foreach ($auctions as &$lot) {
    if ($lot['is_open']) {
        $lot['badge_label'] = 'Live';
        $lot['badge_class'] = 'status good';
    } elseif ($lot['result'] === 'sold') {
        // Create the order if this sale doesn't have one yet, then show its status.
        $escrow = get_escrow_by_auction_ctr($lot['auction_id'])
            ?: ensure_escrow_for_auction_ctr(get_auction_details_ctr((int) $lot['auction_id'], $seller_id) ?? []);
        $lot['badge_label'] = $escrow ? escrow_status_label($escrow['status']) : 'Sold';
        $lot['badge_class'] = $escrow ? escrow_badge_class($escrow['status']) : 'status good';
        if ($escrow && in_array($escrow['status'], ['awaiting_payment', 'funds_held', 'shipped', 'disputed'], true)) {
            $sales[] = $escrow + ['title' => $lot['title'] ?? 'Item #' . $lot['auction_id']];
        }
    } else {
        $lot['badge_label'] = 'Unsold';
        $lot['badge_class'] = 'status';
    }
}
unset($lot);
$has_payout_account = (bool) get_payout_account_ctr($seller_id);

$page_title = 'Selling | Bidlab';
$active_tab = 'selling';
require __DIR__ . '/layout/header.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Selling</h1>
        <p class="muted"><?php echo plural(count($auctions), 'lot'); ?></p>
    </div>
    <div class="row">
        <a class="btn" href="<?php echo url('views/payout_settings.php'); ?>">Payout details</a>
        <a class="btn btn-primary" href="<?php echo url('views/add_auction.php'); ?>"><?php echo icon('plus'); ?>List an item</a>
    </div>
</div>

<?php if ($sales && !$has_payout_account): ?>
    <p class="notice-bar bad" role="alert">You have sales in progress but no payout details. <a href="<?php echo url('views/payout_settings.php'); ?>">Add them</a> so we can pay you.</p>
<?php endif; ?>

<?php if ($sales): ?>
    <section class="panel">
        <div class="section-head"><h2>Sales in progress</h2><span class="mono-label"><?php echo plural(count($sales), 'order'); ?></span></div>
        <div class="order-list">
            <?php foreach ($sales as $sale): ?>
                <a class="order-row no-photo" href="<?php echo url('views/order.php?id=' . (int) $sale['escrow_id']); ?>">
                    <div class="order-meta">
                        <strong><?php echo e($sale['title']); ?></strong>
                        <span class="muted"><?php echo e(escrow_next_step($sale, 'seller')); ?></span>
                    </div>
                    <div class="order-side">
                        <span class="amount"><?php echo e(money($sale['payout_minor'])); ?></span>
                        <span class="<?php echo escrow_badge_class($sale['status']); ?>"><?php echo e(escrow_status_label($sale['status'])); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($auctions): ?>
    <div id="my-lots"><?php require __DIR__ . '/layout/auction_cards.php'; ?></div>
<?php else: ?>
    <div class="empty" id="my-lots">
        <h3>Nothing listed yet</h3>
        <p>Items you list will appear here with their bids and results.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/layout/footer.php'; ?>
