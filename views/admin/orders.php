<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../layout/escrow_helpers.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$status = $_GET['status'] ?? '';
$query = trim($_GET['q'] ?? '');
$page = admin_page();
$result = admin_search_orders_ctr($status, $query, $page);

$chips = ['' => 'All'] + ESCROW_STATUS_LABELS + ['needs_refund' => 'Refund to finish'];
$chip_counts = $result['counts'] + ['needs_refund' => $result['needs_refund'], '' => array_sum($result['counts'])];

$page_title = 'Orders | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'orders';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Orders</h1>
        <p class="muted"><?php echo plural($result['total'], 'order'); ?></p>
    </div>
</div>

<div class="admin-chips">
    <?php foreach ($chips as $value => $label): ?>
        <a href="?<?php echo e(http_build_query(['status' => $value, 'q' => $query])); ?>"<?php echo $status === $value ? ' aria-current="true"' : ''; ?>>
            <?php echo e($label); ?> (<?php echo (int) ($chip_counts[$value] ?? 0); ?>)
        </a>
    <?php endforeach; ?>
</div>

<form class="admin-filters" method="GET">
    <input type="hidden" name="status" value="<?php echo e($status); ?>">
    <input class="input" type="search" name="q" value="<?php echo e($query); ?>" placeholder="Order or listing number, email, payment ref">
    <button class="btn" type="submit">Search</button>
    <?php if ($query !== ''): ?><a href="?<?php echo e(http_build_query(['status' => $status])); ?>">Clear</a><?php endif; ?>
</form>

<section class="panel">
    <?php if (!$result['rows']): ?>
        <p class="muted">No orders match.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Order</th><th>Item</th><th>Buyer</th><th>Seller</th><th class="num">Total</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>
                <?php foreach ($result['rows'] as $order): ?>
                    <tr>
                        <td><a href="<?php echo url('views/admin/order.php?id=' . (int) $order['escrow_id']); ?>">#<?php echo (int) $order['escrow_id']; ?></a></td>
                        <td><?php echo e($order['title'] ?? 'Listing #' . $order['auction_id']); ?></td>
                        <td><a href="<?php echo url('views/admin/user.php?id=' . (int) $order['buyer_id']); ?>"><?php echo e($order['buyer_email'] ?? '#' . $order['buyer_id']); ?></a></td>
                        <td><a href="<?php echo url('views/admin/user.php?id=' . (int) $order['seller_id']); ?>"><?php echo e($order['seller_email'] ?? '#' . $order['seller_id']); ?></a></td>
                        <td class="num"><?php echo e(money($order['total_minor'])); ?></td>
                        <td>
                            <span class="<?php echo escrow_badge_class($order['status']); ?>"><?php echo e($order['status_label']); ?></span>
                            <?php if ($order['status'] === 'refunded' && !$order['refund_reference']): ?><br><small class="field-error">Refund not finished</small><?php endif; ?>
                        </td>
                        <td><?php echo escrow_time($order['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php echo admin_pager($page, $result['total'], ADMIN_PER_PAGE); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
