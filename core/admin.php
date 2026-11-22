<?php
/*
 * Admin access checks and small helpers for the admin pages.
 * Include after core/core.php.
 */
require_once __DIR__ . '/../controllers/admin_controller.php';

/** Stop anyone who is not an admin. Use at the top of every admin page and admin action. */
function require_admin()
{
    require_login();
    if (!is_admin_ctr(current_user_id())) {
        redirect('index.php');
    }
}

/**
 * Stop suspended users. Use in actions that change things: placing bids, listing items, paying.
 * Your login action should also refuse suspended users (see is_suspended_ctr()).
 */
function require_active_user()
{
    require_login();
    if (is_suspended_ctr(current_user_id())) {
        $_SESSION['error'] = 'Your account is suspended. Contact Bidlab if you think this is a mistake.';
        redirect('index.php');
    }
}

/** Current page number from ?page=, at least 1. */
function admin_page()
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

/** Previous / next links that keep the current filters. */
function admin_pager($page, $total, $per_page)
{
    $pages = max(1, (int) ceil($total / $per_page));
    if ($pages <= 1) {
        return '';
    }
    $link = function ($number) {
        return '?' . http_build_query(array_merge($_GET, ['page' => $number]));
    };
    $html = '<nav class="admin-pager" aria-label="Pages">';
    $html .= $page > 1 ? '<a class="btn" href="' . e($link($page - 1)) . '">Previous</a>' : '<span></span>';
    $html .= '<span class="muted">Page ' . $page . ' of ' . $pages . '</span>';
    $html .= $page < $pages ? '<a class="btn" href="' . e($link($page + 1)) . '">Next</a>' : '<span></span>';
    return $html . '</nav>';
}

/** Read a POSTed id, or 0. */
function admin_post_id($key)
{
    return (int) ($_POST[$key] ?? 0);
}
