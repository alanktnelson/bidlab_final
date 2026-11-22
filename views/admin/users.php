<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$filter = $_GET['filter'] ?? 'all';
$query = trim($_GET['q'] ?? '');
$page = admin_page();
$result = admin_search_users_ctr($query, $filter, $page);
$chips = ['all' => 'All', 'admins' => 'Admins', 'suspended' => 'Suspended'];

$page_title = 'Users | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'users';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Users</h1>
        <p class="muted"><?php echo plural($result['total'], 'user'); ?></p>
    </div>
</div>

<div class="admin-chips">
    <?php foreach ($chips as $value => $label): ?>
        <a href="?<?php echo e(http_build_query(['filter' => $value, 'q' => $query])); ?>"<?php echo $filter === $value ? ' aria-current="true"' : ''; ?>><?php echo e($label); ?></a>
    <?php endforeach; ?>
</div>

<form class="admin-filters" method="GET">
    <input type="hidden" name="filter" value="<?php echo e($filter); ?>">
    <input class="input" type="search" name="q" value="<?php echo e($query); ?>" placeholder="Name, email or user number">
    <button class="btn" type="submit">Search</button>
    <?php if ($query !== ''): ?><a href="?<?php echo e(http_build_query(['filter' => $filter])); ?>">Clear</a><?php endif; ?>
</form>

<section class="panel">
    <?php if (!$result['rows']): ?>
        <p class="muted">No users match.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($result['rows'] as $user): ?>
                    <tr>
                        <td><?php echo (int) $user['user_id']; ?></td>
                        <td><a href="<?php echo url('views/admin/user.php?id=' . (int) $user['user_id']); ?>"><?php echo e($user['user_name']); ?></a></td>
                        <td><?php echo e($user['user_email']); ?></td>
                        <td><?php echo $user['user_role'] === 'admin' ? '<span class="status good">Admin</span>' : 'User'; ?></td>
                        <td><?php echo (int) $user['is_suspended'] === 1 ? '<span class="status bad">Suspended</span>' : 'Active'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php echo admin_pager($page, $result['total'], ADMIN_PER_PAGE); ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
