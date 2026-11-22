<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/auction_controller.php';

require_login();

$activity = get_activity_ctr(current_user_id());
$your_bids = $activity['bids'];
foreach ($your_bids as &$lot) {
    if ($lot['is_removed']) {
        $lot['badge_label'] = 'Removed by Bidlab';
        $lot['badge_class'] = 'status bad';
    } elseif ($lot['is_open']) {
        $lot['badge_label'] = $lot['you_are_highest'] ? 'Winning' : 'Outbid';
        $lot['badge_class'] = $lot['you_are_highest'] ? 'status good' : 'status bad';
    } else {
        $lot['badge_label'] = $lot['you_are_highest'] ? 'Won' : 'Lost';
        $lot['badge_class'] = $lot['you_are_highest'] ? 'status good' : 'status';
    }
    $lot['card_note'] = 'Your bid ' . money($lot['your_bid_minor']);
}
unset($lot);

$page_title = 'Activity | Bidlab';
$active_tab = 'activity';
require __DIR__ . '/layout/header.php';
?>

<div class="stack">
    <h1 class="page-title">Activity</h1>

    <section id="my-bids">
        <div class="section-head"><h2>Your bids</h2></div>
        <?php if ($your_bids): ?>
            <?php $auctions = $your_bids; require __DIR__ . '/layout/auction_cards.php'; ?>
        <?php else: ?>
            <div class="empty">
                <h3>No bids yet</h3>
                <p>Lots you bid on will appear here.</p>
                <a class="btn" href="<?php echo url('index.php'); ?>">Browse the market</a>
            </div>
        <?php endif; ?>
    </section>

    <section id="watching">
        <div class="section-head"><h2>Watching</h2></div>
        <?php if ($activity['watching']): ?>
            <?php $auctions = $activity['watching']; require __DIR__ . '/layout/auction_cards.php'; ?>
        <?php else: ?>
            <div class="empty">
                <h3>Nothing on your watchlist</h3>
                <p>Tap the heart on a lot to keep an eye on it.</p>
                <a class="btn" href="<?php echo url('index.php'); ?>">Browse the market</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
