<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';
require_once __DIR__ . '/../layout/escrow_helpers.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$me = current_user_id();
$user = admin_get_user_ctr((int) ($_GET['id'] ?? 0));

$page_title = ($user ? $user['user_name'] : 'User not found') . ' | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'users';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';

if ($user === null): ?>
    <div class="empty" role="alert">
        <h3>That user does not exist</h3>
        <a class="btn" href="<?php echo url('views/admin/users.php'); ?>">All users</a>
    </div>
    <?php require __DIR__ . '/../layout/footer.php';
    exit;
endif;

$uid = (int) $user['user_id'];
$is_me = $uid === (int) $me;
$is_admin = $user['user_role'] === 'admin';
$is_suspended = (int) $user['is_suspended'] === 1;
$activity = $user['activity'];
?>

<a class="back-link" href="<?php echo url('views/admin/users.php'); ?>"><?php echo icon('back'); ?>All users</a>

<div class="page-head">
    <div>
        <h1 class="page-title"><?php echo e($user['user_name']); ?> <?php echo $is_me ? '<small class="muted">(you)</small>' : ''; ?></h1>
        <p class="muted">User #<?php echo $uid; ?> · <a href="mailto:<?php echo e($user['user_email']); ?>"><?php echo e($user['user_email']); ?></a></p>
    </div>
    <div class="row">
        <?php if ($is_admin): ?><span class="status good">Admin</span><?php endif; ?>
        <?php if ($is_suspended): ?><span class="status bad">Suspended</span><?php endif; ?>
    </div>
</div>

<?php if ($is_suspended): ?>
    <p class="notice-bar bad">Suspended <?php echo escrow_time($user['suspended_at']); ?>: <?php echo e($user['suspended_reason']); ?></p>
<?php endif; ?>

<div class="admin-stats">
    <div class="admin-stat"><strong><?php echo $activity['listings']; ?></strong>listings (<?php echo $activity['live_listings']; ?> live)</div>
    <div class="admin-stat"><strong><?php echo $activity['bids']; ?></strong>bids</div>
    <div class="admin-stat"><strong><?php echo $activity['purchases']; ?></strong>purchases</div>
    <div class="admin-stat"><strong><?php echo $activity['sales']; ?></strong>sales</div>
    <div class="admin-stat<?php echo $activity['open_disputes'] ? ' alert' : ''; ?>"><strong><?php echo $activity['open_disputes']; ?></strong>open disputes</div>
</div>

<?php if (!$is_me): ?>
    <div class="admin-two-col">
        <section class="panel">
            <h3><?php echo $is_suspended ? 'Unsuspend' : 'Suspend'; ?></h3>
            <?php if ($is_admin && !$is_suspended): ?>
                <p class="muted">Remove this user's admin role before suspending them.</p>
            <?php else: ?>
                <p class="muted"><?php echo $is_suspended
                    ? 'They will be able to bid and list again.'
                    : 'They will not be able to bid, list items or log in. Their existing orders carry on.'; ?></p>
                <form action="<?php echo url('actions/admin/suspend_user_action.php'); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                    <input type="hidden" name="suspend" value="<?php echo $is_suspended ? '0' : '1'; ?>">
                    <label class="field"><span><?php echo $is_suspended ? 'Note (optional)' : 'Reason'; ?></span>
                        <input class="input" name="reason" maxlength="255"<?php echo $is_suspended ? '' : ' required minlength="5"'; ?> value="<?php echo e($old['reason'] ?? ''); ?>">
                        <?php if (isset($admin_errors['reason'])): ?><small class="field-error"><?php echo e($admin_errors['reason']); ?></small><?php endif; ?>
                    </label>
                    <button class="btn<?php echo $is_suspended ? '' : ' admin-danger'; ?>" type="submit"><?php echo $is_suspended ? 'Unsuspend' : 'Suspend user'; ?></button>
                </form>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h3>Role</h3>
            <p class="muted"><?php echo $is_admin ? 'Admins can see every order, move money and suspend people.' : 'Give admin access only to people you trust with buyers\' money.'; ?></p>
            <form action="<?php echo url('actions/admin/set_role_action.php'); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                <input type="hidden" name="role" value="<?php echo $is_admin ? 'user' : 'admin'; ?>">
                <button class="btn" type="submit"<?php echo !$is_admin && $is_suspended ? ' disabled' : ''; ?>><?php echo $is_admin ? 'Remove admin role' : 'Make admin'; ?></button>
            </form>
        </section>
    </div>
<?php endif; ?>

<section class="panel">
    <div class="section-head"><h2>Orders</h2><a href="<?php echo url('views/admin/orders.php?q=' . urlencode($user['user_email'])); ?>">All</a></div>
    <?php if (!$user['orders']): ?>
        <p class="muted">No orders.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Order</th><th>Item</th><th>As</th><th class="num">Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($user['orders'] as $order): ?>
                    <tr>
                        <td><a href="<?php echo url('views/admin/order.php?id=' . (int) $order['escrow_id']); ?>">#<?php echo (int) $order['escrow_id']; ?></a></td>
                        <td><?php echo e($order['title'] ?? 'Listing #' . $order['auction_id']); ?></td>
                        <td><?php echo (int) $order['buyer_id'] === $uid ? 'Buyer' : 'Seller'; ?></td>
                        <td class="num"><?php echo e(money($order['total_minor'])); ?></td>
                        <td><span class="<?php echo escrow_badge_class($order['status']); ?>"><?php echo e($order['status_label']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="section-head"><h2>Listings</h2></div>
    <?php if (!$user['listings']): ?>
        <p class="muted">No listings.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>#</th><th>Title</th><th class="num">Bids</th><th>Closes</th><th>State</th></tr></thead>
                <tbody>
                <?php foreach ($user['listings'] as $listing): ?>
                    <tr>
                        <td><?php echo (int) $listing['auction_id']; ?></td>
                        <td><a href="<?php echo url('views/single_auction.php?id=' . (int) $listing['auction_id']); ?>"><?php echo e($listing['title']); ?></a></td>
                        <td class="num"><?php echo (int) $listing['bid_count']; ?></td>
                        <td><?php echo escrow_time($listing['closes_at']); ?></td>
                        <td><?php echo $listing['removed_at'] ? '<span class="status bad">Removed</span>' : (strtotime($listing['closes_at'] . ' UTC') > time() ? '<span class="status good">Live</span>' : 'Closed'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($user['log']): ?>
    <section class="panel">
        <div class="section-head"><h2>Admin actions on this user</h2></div>
        <ul class="order-history">
            <?php foreach ($user['log'] as $entry): ?>
                <li><span><?php echo e($entry['admin_name'] ?? '#' . $entry['admin_id']); ?>: <?php echo e($entry['action']); ?><?php echo $entry['details'] ? ' – ' . e($entry['details']) : ''; ?></span><small><?php echo escrow_time($entry['created_at']); ?></small></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
