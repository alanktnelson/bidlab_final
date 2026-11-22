<?php
/*
 * Admin menu. Its styles are in css/style.css. Include right after layout/header.php on every admin page.
 * Set $admin_section to the current page: dashboard, orders, disputes, payouts, users, auctions, categories.
 */
$admin_links = [
    'dashboard' => ['Dashboard', 'views/admin/index.php'],
    'orders' => ['Orders', 'views/admin/orders.php'],
    'disputes' => ['Disputes', 'views/admin/disputes.php'],
    'payouts' => ['Payouts', 'views/admin/payouts.php'],
    'users' => ['Users', 'views/admin/users.php'],
    'auctions' => ['Listings', 'views/admin/auctions.php'],
    'categories' => ['Categories', 'views/admin/categories.php'],
];
$admin_section = $admin_section ?? '';
?>
<nav class="admin-nav" aria-label="Admin">
    <span class="admin-label">Admin</span>
    <?php foreach ($admin_links as $key => [$label, $path]): ?>
        <a href="<?php echo url($path); ?>"<?php echo $admin_section === $key ? ' aria-current="page"' : ''; ?>><?php echo e($label); ?></a>
    <?php endforeach; ?>
</nav>

<?php
$admin_errors = $admin_errors ?? [];
if (isset($admin_errors['general'])): ?>
    <p class="notice-bar bad" role="alert"><?php echo e($admin_errors['general']); ?></p>
<?php endif; ?>
