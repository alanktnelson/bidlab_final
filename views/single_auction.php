<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/auction_controller.php';
require_once __DIR__ . '/../controllers/escrow_controller.php';
require_once __DIR__ . '/layout/escrow_helpers.php';
require_once __DIR__ . '/../core/admin.php';

$viewer_id = current_user_id();
$auction = get_auction_details_ctr((int) ($_GET['id'] ?? 0), $viewer_id);

$active_tab = 'market';

// Listings removed by an admin are hidden from everyone except admins.
if ($auction !== null && $auction['is_removed'] && !is_admin_ctr($viewer_id)) {
    $auction = null;
}

if ($auction === null) {
    http_response_code(404);
    $page_title = 'Lot not found | Bidlab';
    require __DIR__ . '/layout/header.php';
    ?>
    <div class="empty" role="alert">
        <h3>That auction does not exist</h3>
        <p>It may have been removed, or the link may be wrong.</p>
        <a class="btn" href="<?php echo url('index.php'); ?>">Back to the market</a>
    </div>
    <?php
    require __DIR__ . '/layout/footer.php';
    exit;
}

// A sold auction gets an escrow order the first time anyone opens it.
$escrow = ensure_escrow_for_auction_ctr($auction);

$bid_error = flash('bid_error');
$old_bid = flash('old_bid');
$page_title = $auction['title'] . ' | Bidlab';
$lot = $auction;
$return_here = urlencode('views/single_auction.php?id=' . $auction['auction_id']);

$handover = [];
if ($auction['offers_pickup']) {
    $handover[] = 'Pickup';
}
if ($auction['offers_delivery']) {
    $handover[] = $auction['delivery_fee_minor'] ? 'Delivery (' . money($auction['delivery_fee_minor']) . ')' : 'Delivery';
}

if (!$auction['is_open']) {
    $price_label = $auction['result'] === 'sold' ? 'Winning bid' : 'Starting price';
} else {
    $price_label = $auction['bid_count'] ? 'Current bid' : 'Starting price';
}

// The step is set once, in core/core.php (MIN_BID_INCREMENT_MINOR), and checked again by the server.
$min_increment_minor = MIN_BID_INCREMENT_MINOR;
$min_next_bid_minor = (int) $auction['min_next_bid_minor'];

require __DIR__ . '/layout/header.php';
?>

<a class="back-link" href="<?php echo url('index.php'); ?>"><?php echo icon('back'); ?>Back to the market</a>

<?php if ($auction['is_removed']): ?>
    <p class="notice-bar bad" role="alert">Removed by Bidlab: <?php echo e($auction['removed_reason']); ?>. Only admins can see this page, and no one can bid.</p>
<?php endif; ?>

<div class="listing-grid" id="auction" data-id="<?php echo $auction['auction_id']; ?>" data-open="<?php echo $auction['is_open'] ? '1' : '0'; ?>" data-bids="<?php echo $auction['bid_count']; ?>" data-highest="<?php echo $auction['you_are_highest'] ? '1' : '0'; ?>">
    <div class="stack">
        <div>
            <img class="gallery-main" id="gallery-main" src="<?php echo photo_url($auction['photos'][0] ?? ''); ?>" alt="<?php echo e($auction['title']); ?>">
            <?php if (count($auction['photos']) > 1): ?>
                <div class="thumbs">
                    <?php foreach ($auction['photos'] as $index => $photo): ?>
                        <button class="thumb" type="button" data-photo="<?php echo photo_url($photo); ?>" aria-label="Show photo <?php echo $index + 1; ?>" aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"><img src="<?php echo photo_url($photo); ?>" alt=""></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <section class="panel">
            <h1 class="listing-title"><?php echo e($auction['title']); ?></h1>
            <div class="meta-row">
                <span class="status"><?php echo e(condition_label($auction['item_condition'])); ?></span>
                <span><?php echo icon('pin'); ?><?php echo e($auction['location']); ?></span>
                <span><?php echo icon('box'); ?>Listed <?php echo e(time_ago($auction['created_at'])); ?></span>
                <span><?php echo icon('tag'); ?><?php echo e($auction['category']); ?></span>
            </div>
            <p class="description"><?php echo e($auction['description']); ?></p>
        </section>

        <section class="panel">
            <div class="section-head">
                <h2>Bid history</h2>
                <span class="mono-label"><?php echo plural($auction['bid_count'], 'bid'); ?></span>
            </div>
            <?php if (!$auction['bids']): ?>
                <p class="muted">No bids yet. Be the first.</p>
            <?php endif; ?>
            <?php foreach ($auction['bids'] as $bid): ?>
                <div class="bid-row">
                    <div>
                        <span><?php echo e($bid['bidder']); ?></span>
                        <?php if ($viewer_id === (int) $bid['bidder_id']): ?><span class="you-tag">You</span><?php endif; ?>
                        <small><?php echo e(time_ago($bid['created_at'])); ?></small>
                    </div>
                    <span class="amount"><?php echo e(money((int) $bid['amount_minor'])); ?></span>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

    <aside class="side-stack">
        <div class="time-card">
            <span class="mono-label">Time left</span>
            <span class="time-value"><span class="dot live"></span><span id="time-left" data-closes="<?php echo iso_time($auction['closes_at']); ?>"><?php echo time_left($auction['closes_at']); ?></span></span>
        </div>

        <section class="panel">
            <div class="price-head">
                <div>
                    <div class="mono-label"><?php echo $price_label; ?></div>
                    <div class="price-big" id="current-price" aria-live="polite"><?php echo e(money($auction['current_price_minor'])); ?></div>
                    <div class="price-sub"><?php echo plural($auction['bid_count'], 'bid'); ?><?php echo $auction['offers_delivery'] && $auction['delivery_fee_minor'] ? ' · ' . e(money($auction['delivery_fee_minor'])) . ' delivery' : ''; ?></div>
                </div>
                <?php require __DIR__ . '/layout/watch_button.php'; ?>
            </div>

            <div class="action-box">
                <?php if (!$auction['is_open'] && $auction['result'] === 'unsold'): ?>
                    <h3>Closed without a sale</h3>
                    <p>Nobody bid on this lot before it closed.</p>
                <?php elseif (!$auction['is_open'] && ($auction['you_are_highest'] || $auction['you_are_seller'])): ?>
                    <h3><?php echo $auction['you_are_highest'] ? 'You won this auction' : 'Your item sold'; ?></h3>
                    <?php if ($escrow): ?>
                        <p><span class="<?php echo escrow_badge_class($escrow['status']); ?>"><?php echo e(escrow_status_label($escrow['status'])); ?></span></p>
                        <p><?php echo e(escrow_next_step($escrow, $auction['you_are_highest'] ? 'buyer' : 'seller')); ?>.</p>
                        <a class="btn btn-primary btn-block" href="<?php echo url('views/order.php?id=' . (int) $escrow['escrow_id']); ?>"><?php echo $auction['you_are_highest'] && $escrow['status'] === 'awaiting_payment' ? 'Pay securely' : 'View order'; ?></a>
                    <?php else: ?>
                        <p>Your order is being set up. Check back in a moment.</p>
                    <?php endif; ?>
                <?php elseif (!$auction['is_open']): ?>
                    <h3>This auction has closed</h3>
                    <p>The highest bid was <?php echo e(money($auction['current_price_minor'])); ?>.</p>
                <?php elseif (!is_logged_in()): ?>
                    <h3><?php echo icon('lock'); ?>Sign in to bid</h3>
                    <p>Bidding and watching need an account. It takes a moment.</p>
                    <div class="row">
                        <a class="btn btn-primary" href="<?php echo url('views/login.php?return=' . $return_here); ?>">Log in</a>
                        <a class="btn" href="<?php echo url('views/register.php?return=' . $return_here); ?>">Sign up</a>
                    </div>
                <?php elseif ($auction['you_are_seller']): ?>
                    <h3>This is your lot</h3>
                    <p>You cannot bid on your own item.</p>
                <?php elseif ($auction['you_are_highest']): ?>
                    <h3>You are the highest bidder</h3>
                    <p>You can bid again if someone outbids you.</p>
                <?php else: ?>
                    <form action="<?php echo url('actions/place_bid_action.php'); ?>" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="auction_id" value="<?php echo $auction['auction_id']; ?>">
                        <h3>Place your bid</h3>
                        <p>Minimum bid: <span id="min-bid"><?php echo e(money($min_next_bid_minor)); ?></span></p>
                        <small class="hint">Bids go up in steps of at least <?php echo e(money($min_increment_minor)); ?>.</small>
                        <div class="bid-input">
                            <label class="visually-hidden" for="bid-amount">Bid amount in <?php echo CURRENCY; ?></label>
                            <input class="input" id="bid-amount" name="amount" type="number" inputmode="decimal" step="0.01" min="<?php echo money_input($min_next_bid_minor); ?>" required value="<?php echo e($old_bid ?: money_input($min_next_bid_minor)); ?>">
                        </div>
                        <button class="btn btn-primary btn-block" type="submit">Place bid</button>
                    </form>
                    <?php if ($bid_error): ?>
                        <p class="error-text" role="alert"><?php echo e($bid_error); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="detail-rows">
                <dl>
                    <div class="detail-row"><dt>Ends</dt><dd data-local-time="<?php echo iso_time($auction['closes_at']); ?>"><?php echo e($auction['closes_at']); ?> UTC</dd></div>
                    <div class="detail-row"><dt>Condition</dt><dd><?php echo e(condition_label($auction['item_condition'])); ?></dd></div>
                    <div class="detail-row"><dt>Item location</dt><dd><?php echo e($auction['location']); ?></dd></div>
                    <div class="detail-row"><dt>Handover</dt><dd><?php echo e(implode(' or ', $handover)); ?></dd></div>
                </dl>
            </div>
            <p class="notice"><?php echo icon('shield'); ?>Bidlab holds the winner's payment and only pays the seller once the winner confirms they have the item.</p>
        </section>

        <section class="panel">
            <div class="mono-label">Seller</div>
            <div class="seller-row">
                <span class="avatar" aria-hidden="true"><?php echo e(initials($auction['seller_name'])); ?></span>
                <span><?php echo e($auction['seller_name']); ?></span>
            </div>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
