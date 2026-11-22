<?php
require_once __DIR__ . '/../core/core.php';
require_once __DIR__ . '/../controllers/auction_controller.php';

require_login();

$errors = flash('errors') ?? [];
$old = flash('old') ?? [];
$categories = get_categories_ctr();
$conditions = ['new' => 'New', 'like_new' => 'Like new', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'];
$had_error = $errors !== [];

function field_error($errors, $key) {
    return isset($errors[$key]) ? '<small class="field-error">' . e($errors[$key]) . '</small>' : '';
}

$page_title = 'List an item | Bidlab';
$active_tab = 'selling';
require __DIR__ . '/layout/header.php';
?>

<a class="back-link" href="<?php echo url('views/selling.php'); ?>"><?php echo icon('back'); ?>Back to selling</a>

<section class="panel">
    <div class="section-head"><h1 class="page-title">List an item</h1></div>

    <?php if (isset($errors['general'])): ?>
        <p class="notice-bar bad" role="alert"><?php echo e($errors['general']); ?></p>
    <?php endif; ?>

    <form id="listing-form" action="<?php echo url('actions/add_auction_action.php'); ?>" method="POST" enctype="multipart/form-data" data-max-photos="<?php echo AUCTION_MAX_PHOTOS; ?>" data-max-bytes="<?php echo AUCTION_IMAGE_MAX_BYTES; ?>" data-min-minutes="<?php echo MIN_AUCTION_MINUTES; ?>" data-max-days="<?php echo MAX_AUCTION_DAYS; ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="closes_at_utc" id="closes-at-utc">

        <label class="field"><span>Title</span>
            <input class="input" name="title" required minlength="3" maxlength="120" value="<?php echo e($old['title'] ?? ''); ?>">
            <?php echo field_error($errors, 'title'); ?>
        </label>
        <label class="field"><span>Description</span>
            <textarea class="input" name="description" required minlength="10" maxlength="5000"><?php echo e($old['description'] ?? ''); ?></textarea>
            <?php echo field_error($errors, 'description'); ?>
        </label>

        <div class="form-grid">
            <label class="field"><span>Category</span>
                <select class="input" name="cat_id" id="category" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['cat_id']; ?>"<?php echo (int) ($old['cat_id'] ?? 0) === (int) $category['cat_id'] ? ' selected' : ''; ?>><?php echo e($category['cat_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php echo field_error($errors, 'cat_id'); ?>
            </label>
            <label class="field"><span>Condition</span>
                <select class="input" name="condition" required>
                    <?php foreach ($conditions as $value => $label): ?>
                        <option value="<?php echo $value; ?>"<?php echo ($old['condition'] ?? 'good') === $value ? ' selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php echo field_error($errors, 'condition'); ?>
            </label>
            <label class="field"><span>Starting price</span>
                <input class="input" name="starting_price" type="number" step="0.01" min="0.01" required value="<?php echo e($old['starting_price'] ?? ''); ?>">
                <small class="hint">In <?php echo CURRENCY; ?>. Bidding starts at this price.</small>
                <?php echo field_error($errors, 'starting_price'); ?>
            </label>
            <label class="field"><span>Closing time</span>
                <input class="input" id="closes-at" name="closes_at" type="datetime-local" required value="<?php echo e($old['closes_at'] ?? ''); ?>">
                <small class="hint">The auction ends at this time, in your local time zone.</small>
                <?php echo field_error($errors, 'closes_at'); ?>
            </label>
            <label class="field"><span>Item location</span>
                <input class="input" name="location" required minlength="2" maxlength="120" placeholder="City, country" value="<?php echo e($old['location'] ?? ''); ?>">
                <small class="hint">Helps buyers judge whether handover is practical.</small>
                <?php echo field_error($errors, 'location'); ?>
            </label>
            <label class="field"><span>Delivery fee (optional)</span>
                <input class="input" name="delivery_fee" type="number" step="0.01" min="0" value="<?php echo e($old['delivery_fee'] ?? ''); ?>">
                <?php echo field_error($errors, 'delivery_fee'); ?>
            </label>
        </div>

        <fieldset>
            <legend>Handover options</legend>
            <?php $handover = $old['handover'] ?? 'pickup'; ?>
            <label class="check"><input type="radio" name="handover" value="pickup" required<?php echo $handover === 'pickup' ? ' checked' : ''; ?>> Pickup</label>
            <label class="check"><input type="radio" name="handover" value="delivery"<?php echo $handover === 'delivery' ? ' checked' : ''; ?>> Delivery</label>
            <?php echo field_error($errors, 'handover'); ?>
        </fieldset>

        <label class="field"><span>Photos</span>
            <input class="input" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
            <small class="hint">1 to <?php echo AUCTION_MAX_PHOTOS; ?> JPEG, PNG or WebP photos, up to 2 MB each.</small>
            <small class="field-error" id="photos-error"<?php echo isset($errors['photos']) ? '' : ' hidden'; ?>><?php echo e($errors['photos'] ?? ''); ?></small>
        </label>
        <div class="photo-previews" id="photo-previews"></div>

        <div class="hero-actions form-actions">
            <button class="btn btn-primary btn-lg" type="submit">Publish auction</button>
            <a class="btn btn-lg" href="<?php echo url('views/selling.php'); ?>">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/layout/footer.php'; ?>
