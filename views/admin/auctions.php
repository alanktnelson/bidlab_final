<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../layout/escrow_helpers.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$filter = $_GET['filter'] ?? 'live';
$query = trim($_GET['q'] ?? '');
$page = admin_page();
$result = admin_search_auctions_ctr($query, $filter, $page);
$chips = ['live' => 'Live', 'closed' => 'Closed', 'removed' => 'Removed', 'all' => 'All'];

$page_title = 'Listings | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'auctions';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Listings</h1>
        <p class="muted"><?php echo plural($result['total'], 'listing'); ?>. Removing hides a listing and stops bidding; it can be restored.</p>
    </div>
</div>

<div class="admin-chips">
    <?php foreach ($chips as $value => $label): ?>
        <a href="?<?php echo e(http_build_query(['filter' => $value, 'q' => $query])); ?>"<?php echo $filter === $value ? ' aria-current="true"' : ''; ?>><?php echo e($label); ?></a>
    <?php endforeach; ?>
</div>

<form class="admin-filters" method="GET">
    <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
    <input class="input" type="search" name="q" value="<?php echo e($query); ?>" placeholder="Title or listing number">
    <button class="btn" type="submit">Search</button>
    <?php if ($query !== ''): ?><a href="?<?php echo e(http_build_query(['filter' => $filter])); ?>">Clear</a><?php endif; ?>
</form>

<section class="panel">
    <?php if (!$result['rows']): ?>
        <p class="muted">No listings match.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>#</th><th>Title</th><th>Seller</th><th class="num">Bids</th><th class="num">Top bid</th><th>Closes</th><th>Order</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($result['rows'] as $listing):
                    $aid = (int) $listing['auction_id'];
                    $has_active_order = $listing['escrow_id'] && !in_array($listing['escrow_status'], ['cancelled', 'refunded'], true);
                    ?>
                    <tr>
                        <td><?php echo $aid; ?></td>
                        <td>
                            <a href="<?php echo url('views/single_auction.php?id=' . $aid); ?>" target="_blank" rel="noopener"><?php echo e($listing['title']); ?></a>
                            <?php if ($listing['removed_at']): ?><br><small class="field-error">Removed: <?php echo e($listing['removed_reason']); ?></small><?php endif; ?>
                        </td>
                        <td><a href="<?php echo url('views/admin/user.php?id=' . (int) $listing['seller_id']); ?>"><?php echo e($listing['seller_name'] ?? '#' . $listing['seller_id']); ?></a></td>
                        <td class="num"><?php echo (int) $listing['bid_count']; ?></td>
                        <td class="num"><?php echo $listing['top_bid_minor'] !== null ? e(money((int) $listing['top_bid_minor'])) : '—'; ?></td>
                        <td><?php echo escrow_time($listing['closes_at']); ?></td>
                        <td>
                            <?php if ($listing['escrow_id']): ?>
                                <a href="<?php echo url('views/admin/order.php?id=' . (int) $listing['escrow_id']); ?>">#<?php echo (int) $listing['escrow_id']; ?></a>
                                <br><span class="<?php echo escrow_badge_class($listing['escrow_status']); ?>"><?php echo e(escrow_status_label($listing['escrow_status'])); ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($listing['removed_at']): ?>
                                <form action="<?php echo url('actions/admin/remove_auction_action.php'); ?>" method="POST">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="auction_id" value="<?php echo $aid; ?>">
                                    <input type="hidden" name="do" value="restore">
                                    <button class="btn" type="submit">Restore</button>
                                </form>
                            <?php elseif ($has_active_order): ?>
                                <small class="muted">Sold: handle the order</small>
                            <?php else: ?>
                                <details>
                                    <summary>Remove</summary>
                                    <form class="admin-inline-form" action="<?php echo url('actions/admin/remove_auction_action.php'); ?>" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="auction_id" value="<?php echo $aid; ?>">
                                        <input type="hidden" name="do" value="remove">
                                        <input class="input" name="reason" required minlength="5" maxlength="255" placeholder="Reason"
                                               value="<?php echo (int) ($old['auction_id'] ?? 0) === $aid ? e($old['reason'] ?? '') : ''; ?>">
                                        <button class="btn admin-danger" type="submit">Remove</button>
                                    </form>
                                    <?php if ((int) ($old['auction_id'] ?? 0) === $aid && isset($admin_errors['reason'])): ?><small class="field-error"><?php echo e($admin_errors['reason']); ?></small><?php endif; ?>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php echo admin_pager($page, $result['total'], ADMIN_PER_PAGE); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
