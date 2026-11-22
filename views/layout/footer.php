
</div>
</main>

<footer class="site-footer">
    <div class="container">Bidlab holds the winner's payment and only pays the seller once the winner confirms they have the item.</div>
</footer>

<nav class="bottom-nav" aria-label="Main">
    <?php foreach ($nav_items as $key => [$label, $path, $icon_name]): ?>
        <a href="<?php echo url($path); ?>"<?php echo $key === $active_tab ? ' aria-current="page"' : ''; ?>><?php echo icon($icon_name); ?><?php echo e($label); ?></a>
    <?php endforeach; ?>
</nav>

<script src="<?php echo url('js/main.js?v=' . filemtime(__DIR__ . '/../../js/main.js')); ?>"></script>
<script src="<?php echo url('js/validate.js?v=' . filemtime(__DIR__ . '/../../js/validate.js')); ?>"></script>
</body>
</html>
