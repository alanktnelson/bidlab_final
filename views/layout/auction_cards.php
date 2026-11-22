<?php // Expects $auctions. A lot may also set 'badge_label', 'badge_class' and 'card_note'. ?>
<div class="lot-grid">
    <?php foreach ($auctions as $lot): ?>
        <article class="lot-card">
            <div class="lot-media">
                <img src="<?php echo photo_url($lot['cover_photo']); ?>" alt="" loading="lazy">
                <span class="lot-badge"><?php echo icon('gavel'); ?><?php echo $lot['is_open'] ? 'Auction' : 'Closed'; ?></span>
            </div>
            <?php require __DIR__ . '/watch_button.php'; ?>
            <div class="lot-body">
                <h3 class="lot-title"><a href="<?php echo url('views/single_auction.php?id=' . $lot['auction_id']); ?>"><?php echo e($lot['title']); ?></a></h3>
                <p class="lot-meta"><?php echo e(condition_label($lot['item_condition'])); ?> · <?php echo e($lot['location']); ?></p>
                <?php if (isset($lot['badge_label'])): ?>
                    <span><span class="<?php echo e($lot['badge_class']); ?>"><?php echo e($lot['badge_label']); ?></span></span>
                <?php endif; ?>
                <div class="lot-footer">
                    <div>
                        <div class="lot-note"><?php echo e($lot['card_note'] ?? ($lot['bid_count'] ? plural($lot['bid_count'], 'bid') : 'Starting price')); ?></div>
                        <div class="lot-price"><?php echo e(money($lot['current_price_minor'])); ?></div>
                    </div>
                    <?php if ($lot['is_open']): ?>
                        <span class="lot-note" data-closes="<?php echo iso_time($lot['closes_at']); ?>"><?php echo time_left($lot['closes_at']); ?></span>
                    <?php else: ?>
                        <span class="lot-note"><?php echo $lot['result'] === 'sold' ? 'Sold' : 'Unsold'; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
