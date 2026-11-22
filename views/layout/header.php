<?php
// Pages set $page_title and $active_tab before including this file.
$page_title = $page_title ?? 'Bidlab | Online auction marketplace';
$active_tab = $active_tab ?? '';
require_once __DIR__ . '/../../core/admin.php';
$nav_items = [
    'market' => ['Market', 'index.php', 'store'],
    'selling' => ['Selling', 'views/selling.php', 'tag'],
    'purchases' => ['Purchases', 'views/purchases.php', 'bag'],
    'activity' => ['Activity', 'views/activity.php', 'pulse'],
    'account' => ['Account', 'views/account/my_account.php', 'user'],
];
if (is_logged_in() && is_admin_ctr(current_user_id())) {
    $nav_items['admin'] = ['Admin', 'views/admin/index.php', 'shield'];
}
$here = urlencode(safe_return(current_path()));
$page_success = flash('success');
$page_error = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self' https://checkout.paystack.com">
    <title><?php echo e($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=Fraunces:opsz,wght@9..144,500;9..144,600&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?php echo url('css/style.css?v=' . filemtime(__DIR__ . '/../../css/style.css')); ?>">
</head>
<body data-server-time="<?php echo gmdate('Y-m-d\TH:i:s\Z'); ?>">

<div class="announcement">
    <div class="container"><span class="dot"></span>English auction: the highest bid at closing wins.</div>
</div>

<header class="site-header">
    <div class="container header-top">
        <a class="brand" href="<?php echo url('index.php'); ?>">
            <span class="brand-name">Bidlab</span>
            <span class="brand-tag">Online auction</span>
        </a>
        <form class="search" role="search" action="<?php echo url('index.php'); ?>" method="GET">
            <?php echo icon('search'); ?>
            <input type="search" name="q" maxlength="100" placeholder="Search lots, categories, sellers" aria-label="Search lots" value="<?php echo e(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''); ?>">
        </form>
        <div class="header-actions">
            <?php if (is_logged_in()): ?>
                <a class="btn" href="<?php echo url('views/add_auction.php'); ?>"><?php echo icon('plus'); ?>List an item</a>
            <?php else: ?>
                <a class="btn" href="<?php echo url('views/login.php?return=' . $here); ?>">Log in</a>
                <a class="btn btn-primary" href="<?php echo url('views/register.php?return=' . $here); ?>">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
    <nav class="container main-nav" aria-label="Main">
        <?php foreach ($nav_items as $key => [$label, $path]): ?>
            <a href="<?php echo url($path); ?>"<?php echo $key === $active_tab ? ' aria-current="page"' : ''; ?>><?php echo e($label); ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<main>
<div class="container">
    <?php if ($page_success): ?>
        <p class="notice-bar good" role="status"><?php echo e($page_success); ?></p>
    <?php endif; ?>
    <?php if ($page_error): ?>
        <p class="notice-bar bad" role="alert"><?php echo e($page_error); ?></p>
    <?php endif; ?>
