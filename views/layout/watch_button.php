<?php // Expects $lot, the auction being watched. ?>
<form class="watch-form" action="<?php echo url('actions/toggle_watch_action.php'); ?>" method="POST">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="auction_id" value="<?php echo $lot['auction_id']; ?>">
    <input type="hidden" name="return" value="<?php echo e(safe_return(current_path())); ?>">
    <button class="icon-btn" type="submit" aria-label="<?php echo e(($lot['watched'] ? 'Stop watching ' : 'Watch ') . $lot['title']); ?>" aria-pressed="<?php echo $lot['watched'] ? 'true' : 'false'; ?>"><?php echo icon('heart'); ?></button>
</form>
