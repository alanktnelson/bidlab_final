<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../layout/escrow_helpers.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$stats = admin_dashboard_ctr();
$orders = $stats['orders'];

// Things that need a person. Each: [count, label, link]
$attention = array_filter([
    [$stats['open_disputes'], 'open disputes', 'views/admin/disputes.php'],
    [$stats['refunds_missing'], 'refunds to finish by hand', 'views/admin/orders.php?status=needs_refund'],
    [$stats['failed_payouts'], 'failed payouts', 'views/admin/payouts.php?status=failed'],
    [$stats['stuck_payouts'], 'payouts waiting on Paystack', 'views/admin/payouts.php?status=processing'],
    [$stats['payouts_without_account'], 'payouts waiting for seller bank/MoMo details', 'views/admin/payouts.php?status=pending'],
], function ($item) {
    return $item[0] > 0;
});

$action_labels = [
    'make_admin' => 'Made admin', 'remove_admin' => 'Removed admin', 'suspend' => 'Suspended', 'unsuspend' => 'Unsuspended',
    'remove_listing' => 'Removed listing', 'restore_listing' => 'Restored listing',
    'create_category' => 'Created category', 'rename_category' => 'Renamed category', 'delete_category' => 'Deleted category',
    'cancel_order' => 'Cancelled order', 'refund_order' => 'Refunded order', 'record_refund' => 'Recorded refund',
    'retry_payout' => 'Retried payout', 'mark_payout_paid' => 'Marked payout paid', 'resolve_dispute' => 'Resolved dispute',
];
$target_links = [
    'user' => 'views/admin/user.php?id=', 'order' => 'views/admin/order.php?id=', 'auction' => 'views/single_auction.php?id=',
    'payout' => 'views/admin/payouts.php?id=', 'category' => 'views/admin/categories.php?id=', 'dispute' => 'views/admin/disputes.php?id=',
];

$page_title = 'Admin | Bidlab';
$active_tab = 'admin';
$admin_section = 'dashboard';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="muted">Bidlab is holding <?php echo e(money($stats['money_held_minor'])); ?> for buyers right now.</p>
    </div>
</div>

<?php if ($attention): ?>
    <section class="panel">
        <div class="section-head"><h2>Needs attention</h2></div>
        <div class="admin-stats">
            <?php foreach ($attention as [$count, $label, $link]): ?>
                <a class="admin-stat alert" href="<?php echo url($link); ?>"><strong><?php echo (int) $count; ?></strong><?php echo e($label); ?></a>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <p class="notice-bar good">Nothing needs attention right now.</p>
<?php endif; ?>

<section class="panel">
    <div class="section-head"><h2>Orders</h2><a href="<?php echo url('views/admin/orders.php'); ?>">All orders</a></div>
    <div class="admin-stats">
        <?php foreach (ESCROW_STATUS_LABELS as $status => $label): ?>
            <a class="admin-stat" href="<?php echo url('views/admin/orders.php?status=' . $status); ?>"><strong><?php echo (int) ($orders[$status] ?? 0); ?></strong><?php echo e($label); ?></a>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel">
    <div class="section-head"><h2>Marketplace</h2></div>
    <div class="admin-stats">
        <a class="admin-stat" href="<?php echo url('views/admin/users.php'); ?>"><strong><?php echo (int) $stats['users']; ?></strong>users</a>
        <a class="admin-stat" href="<?php echo url('views/admin/users.php?filter=suspended'); ?>"><strong><?php echo (int) $stats['suspended']; ?></strong>suspended</a>
        <a class="admin-stat" href="<?php echo url('views/admin/auctions.php?filter=live'); ?>"><strong><?php echo (int) $stats['live_auctions']; ?></strong>live listings</a>
    </div>
</section>

<section class="panel">
    <div class="section-head"><h2>Recent admin actions</h2></div>
    <?php if (!$stats['recent']): ?>
        <p class="muted">No admin actions yet.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th></tr></thead>
                <tbody>
                <?php foreach ($stats['recent'] as $entry): ?>
                    <tr>
                        <td><?php echo escrow_time($entry['created_at']); ?></td>
                        <td><?php echo e($entry['admin_name'] ?? '#' . $entry['admin_id']); ?></td>
                        <td>
                            <?php echo e($action_labels[$entry['action']] ?? $entry['action']); ?>
                            <a href="<?php echo url(($target_links[$entry['target_type']] ?? '#') . (int) $entry['target_id']); ?>"><?php echo e($entry['target_type']); ?> #<?php echo (int) $entry['target_id']; ?></a>
                        </td>
                        <td><?php echo e($entry['details']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
