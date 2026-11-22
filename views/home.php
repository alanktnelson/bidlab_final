<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/auction_controller.php';

$filters = [
    'q' => is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '',
    'category' => (int) ($_GET['category'] ?? 0),
    'ending_soon' => ($_GET['ending'] ?? '') === 'hour',
    'sort' => is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'ending',
];
$viewer_id = current_user_id();
$categories = get_categories_ctr();
$stats = get_market_stats_ctr();
$closing_bell = array_slice(search_auctions_ctr(['sort' => 'ending'], $viewer_id), 0, 3);
$auctions = search_auctions_ctr($filters, $viewer_id);
$your_live_bids = $viewer_id === null ? '—' : count(array_filter(get_activity_ctr($viewer_id)['bids'], fn ($lot) => $lot['is_open']));
$is_filtered = $filters['q'] !== '' || $filters['category'] > 0 || $filters['ending_soon'];

$sort_options = [
    'ending' => 'Ending soonest',
    'newest' => 'Newest',
    'price_low' => 'Price: low to high',
    'price_high' => 'Price: high to low',
    'most_bids' => 'Most bids',
];

/** A link to this page with one filter changed and the others kept. */
function market_url($changes, $filters) {
    $query = array_merge(['q' => $filters['q'], 'category' => $filters['category'] ?: '', 'sort' => $filters['sort'], 'ending' => $filters['ending_soon'] ? 'hour' : ''], $changes);
    $query = http_build_query(array_filter($query, fn ($value) => $value !== ''));
    return url('index.php' . ($query === '' ? '' : '?' . $query));
}

$active_tab = 'market';
require __DIR__ . '/layout/header.php';
?>

<section class="hero">
    <div>
        <span class="pill-label mono-label"><span class="dot live"></span>Live auctions</span>
        <h1>Your Money. Your Bid. Your Win.</h1>
        <p class="hero-text">List an item with photos, a starting price and a closing time. Buyers place higher and higher bids, and the highest bid at closing wins.</p>
        <div class="stat-row">
            <div class="stat"><span class="mono-label">Open lots</span><strong id="stat-open"><?php echo $stats['open_lots']; ?></strong></div>
            <div class="stat"><span class="mono-label">Closing under 1h</span><strong id="stat-soon"><?php echo $stats['closing_soon']; ?></strong></div>
            <div class="stat"><span class="mono-label">Your live bids</span><strong id="stat-yours"><?php echo $your_live_bids; ?></strong></div>
        </div>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="<?php echo url('views/add_auction.php'); ?>"><?php echo icon('plus'); ?>List an item</a>
            <a class="btn btn-lg" href="#lots">Browse lots</a>
        </div>
    </div>
    <aside class="closing-bell" aria-label="Closing soonest">
        <header><span class="mono-label">Closing bell</span><span class="mono-label live-label">Live</span></header>
        <?php if (!$closing_bell): ?>
            <p class="muted">No lots are closing yet. Be the first to list one.</p>
        <?php endif; ?>
        <?php foreach ($closing_bell as $lot): ?>
            <a class="bell-row" href="<?php echo url('views/single_auction.php?id=' . $lot['auction_id']); ?>">
                <img src="<?php echo photo_url($lot['cover_photo']); ?>" alt="">
                <div class="grow">
                    <div class="bell-title"><?php echo e($lot['title']); ?></div>
                    <div class="bell-meta"><?php echo plural($lot['bid_count'], 'bid'); ?> · <?php echo e(money($lot['current_price_minor'])); ?></div>
                </div>
                <span class="bell-time" data-closes="<?php echo iso_time($lot['closes_at']); ?>"><?php echo time_left($lot['closes_at']); ?></span>
            </a>
        <?php endforeach; ?>
    </aside>
</section>

<nav class="chips" aria-label="Filter by category">
    <a class="chip" href="<?php echo e(market_url(['category' => ''], $filters)); ?>"<?php echo $filters['category'] === 0 ? ' aria-current="true"' : ''; ?>>All categories</a>
    <?php foreach ($categories as $category): ?>
        <a class="chip" href="<?php echo e(market_url(['category' => $category['cat_id']], $filters)); ?>"<?php echo $filters['category'] === (int) $category['cat_id'] ? ' aria-current="true"' : ''; ?>><?php echo e($category['cat_name']); ?></a>
    <?php endforeach; ?>
</nav>

<form class="filter-bar" action="<?php echo url('index.php'); ?>" method="GET" data-autosubmit>
    <?php if ($filters['q'] !== ''): ?><input type="hidden" name="q" value="<?php echo e($filters['q']); ?>"><?php endif; ?>
    <?php if ($filters['category'] > 0): ?><input type="hidden" name="category" value="<?php echo $filters['category']; ?>"><?php endif; ?>
    <label class="toggle"><input type="checkbox" id="ending-hour" name="ending" value="hour"<?php echo $filters['ending_soon'] ? ' checked' : ''; ?>> Ending within the hour</label>
    <div class="filter-end">
        <span class="mono-label" id="lot-count"><?php echo plural(count($auctions), 'lot'); ?></span>
        <label class="visually-hidden" for="sort">Sort lots</label>
        <select class="select" id="sort" name="sort">
            <?php foreach ($sort_options as $value => $label): ?>
                <option value="<?php echo $value; ?>"<?php echo $filters['sort'] === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn" type="submit">Apply</button></noscript>
    </div>
</form>

<div id="lots">
    <?php if ($auctions): ?>
        <?php require __DIR__ . '/layout/auction_cards.php'; ?>
    <?php elseif ($is_filtered): ?>
        <div class="empty"><h3>No lots match that</h3><p>Try a different search or category.</p></div>
    <?php else: ?>
        <div class="empty">
            <h3>No open auctions yet</h3>
            <p>List the first item and start the bidding.</p>
            <a class="btn btn-primary" href="<?php echo url('views/add_auction.php'); ?>"><?php echo icon('plus'); ?>List an item</a>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
