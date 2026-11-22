<?php
require_once __DIR__ . '/../../core/core.php';
require_once __DIR__ . '/../../core/admin.php';

require_admin();

$admin_errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$categories = admin_get_categories_ctr();
$old_id = (int) ($old['cat_id'] ?? 0);

$page_title = 'Categories | Bidlab admin';
$active_tab = 'admin';
$admin_section = 'categories';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/layout/admin_nav.php';
?>

<div class="page-head">
    <div>
        <h1 class="page-title">Categories</h1>
        <p class="muted">Sellers pick one of these when listing. A category can only be deleted when no listing uses it.</p>
    </div>
</div>

<section class="panel">
    <h3>Add a category</h3>
    <form class="admin-inline-form" action="<?php echo url('actions/admin/save_category_action.php'); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <input class="input" name="cat_name" required minlength="2" maxlength="60" placeholder="e.g. Phones & tablets"
               value="<?php echo $old_id === 0 ? e($old['cat_name'] ?? '') : ''; ?>">
        <button class="btn btn-primary" type="submit">Add</button>
    </form>
    <?php if ($old_id === 0 && isset($admin_errors['cat_name'])): ?><small class="field-error"><?php echo e($admin_errors['cat_name']); ?></small><?php endif; ?>
</section>

<section class="panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Name</th><th class="num">Listings</th><th>Rename</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($categories as $category): $cid = (int) $category['cat_id']; ?>
                <tr>
                    <td><?php echo e($category['cat_name']); ?></td>
                    <td class="num"><?php echo (int) $category['listing_count']; ?></td>
                    <td>
                        <form class="admin-inline-form" action="<?php echo url('actions/admin/save_category_action.php'); ?>" method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="cat_id" value="<?php echo $cid; ?>">
                            <input class="input" name="cat_name" required minlength="2" maxlength="60"
                                   value="<?php echo $old_id === $cid ? e($old['cat_name'] ?? '') : e($category['cat_name']); ?>" aria-label="New name for <?php echo e($category['cat_name']); ?>">
                            <button class="btn" type="submit">Save</button>
                        </form>
                        <?php if ($old_id === $cid && isset($admin_errors['cat_name'])): ?><small class="field-error"><?php echo e($admin_errors['cat_name']); ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int) $category['listing_count'] === 0): ?>
                            <form action="<?php echo url('actions/admin/delete_category_action.php'); ?>" method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="cat_id" value="<?php echo $cid; ?>">
                                <button class="btn admin-danger" type="submit">Delete</button>
                            </form>
                        <?php else: ?>
                            <small class="muted">In use</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../layout/footer.php'; ?>
