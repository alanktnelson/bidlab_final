<?php
require_once __DIR__ . '/../classes/admin_class.php';
require_once __DIR__ . '/dispute_controller.php';   // also loads escrow_controller.php

const ADMIN_PER_PAGE = 50;

function admin_error($message)
{
    return ['errors' => ['general' => $message]];
}

/** Trim, strip tags, and check a required reason / note. Returns [clean text, error or null]. */
function admin_clean_reason($text, $min = 5, $max = 255)
{
    $text = trim(strip_tags($text ?? ''));
    if (mb_strlen($text) < $min || mb_strlen($text) > $max) {
        return [$text, "Give a reason of $min to $max characters."];
    }
    return [$text, null];
}

/* =====================================================================
 * Access
 * ===================================================================== */

function is_admin_ctr($user_id)
{
    return $user_id ? (new admin_class())->is_admin((int) $user_id) : false;
}

function is_suspended_ctr($user_id)
{
    return $user_id ? (new admin_class())->is_suspended((int) $user_id) : false;
}

/* =====================================================================
 * Dashboard and log
 * ===================================================================== */

function admin_dashboard_ctr()
{
    $admin = new admin_class();
    return $admin->dashboard_counts() + ['recent' => $admin->recent_log(15)];
}

function admin_log_for_ctr($target_type, $target_id)
{
    return (new admin_class())->recent_log(50, $target_type, (int) $target_id);
}

/* =====================================================================
 * Users
 * ===================================================================== */

function admin_search_users_ctr($query, $filter, $page)
{
    $admin = new admin_class();
    $query = trim($query);
    $filter = in_array($filter, ['all', 'admins', 'suspended'], true) ? $filter : 'all';
    return [
        'rows' => $admin->search_users($query, $filter, ADMIN_PER_PAGE, ($page - 1) * ADMIN_PER_PAGE),
        'total' => $admin->count_users($query, $filter),
    ];
}

function admin_get_user_ctr($user_id)
{
    $admin = new admin_class();
    $user = $admin->get_user((int) $user_id);
    if (!$user) {
        return null;
    }
    $user['activity'] = $admin->user_activity((int) $user_id);
    $user['orders'] = add_status_labels($admin->search_orders('', '', 20, 0, (int) $user_id));
    $user['listings'] = $admin->search_auctions('', 'all', 20, 0, (int) $user_id);
    $user['log'] = $admin->recent_log(20, 'user', (int) $user_id);
    return $user;
}

/** Make someone an admin or a normal user. You cannot change your own role, and the last admin stays. */
function admin_set_role_ctr($admin_id, $user_id, $role)
{
    $admin = new admin_class();
    $user = $admin->get_user((int) $user_id);
    if (!$user) {
        return admin_error('That user does not exist.');
    }
    if (!in_array($role, ['user', 'admin'], true)) {
        return admin_error('Unknown role.');
    }
    if ((int) $user_id === (int) $admin_id) {
        return admin_error('You cannot change your own role.');
    }
    if ($user['user_role'] === $role) {
        return ['ok' => true];
    }
    if ($role === 'admin' && (int) $user['is_suspended'] === 1) {
        return admin_error('Unsuspend this user before making them an admin.');
    }
    if ($role === 'user' && $admin->count_admins() <= 1) {
        return admin_error('Bidlab needs at least one admin.');
    }

    $admin->set_role((int) $user_id, $role);
    $admin->log((int) $admin_id, $role === 'admin' ? 'make_admin' : 'remove_admin', 'user', (int) $user_id, $user['user_email']);
    return ['ok' => true];
}

/** Suspend or unsuspend. Admins must be made normal users first, and you cannot suspend yourself. */
function admin_set_suspended_ctr($admin_id, $user_id, $suspend, $reason)
{
    $admin = new admin_class();
    $user = $admin->get_user((int) $user_id);
    if (!$user) {
        return admin_error('That user does not exist.');
    }
    if ((int) $user_id === (int) $admin_id) {
        return admin_error('You cannot suspend yourself.');
    }

    if ($suspend) {
        if ($user['user_role'] === 'admin') {
            return admin_error('Remove this user\'s admin role before suspending them.');
        }
        [$reason, $error] = admin_clean_reason($reason);
        if ($error) {
            return ['errors' => ['reason' => $error]];
        }
        $admin->set_suspended((int) $user_id, true, $reason);
        $admin->log((int) $admin_id, 'suspend', 'user', (int) $user_id, $reason);
    } else {
        $admin->set_suspended((int) $user_id, false, null);
        $admin->log((int) $admin_id, 'unsuspend', 'user', (int) $user_id, trim(strip_tags($reason ?? '')) ?: null);
    }
    return ['ok' => true];
}

/* =====================================================================
 * Auctions
 * ===================================================================== */

function admin_search_auctions_ctr($query, $filter, $page)
{
    $admin = new admin_class();
    $query = trim($query);
    $filter = in_array($filter, ['all', 'live', 'closed', 'removed'], true) ? $filter : 'all';
    return [
        'rows' => $admin->search_auctions($query, $filter, ADMIN_PER_PAGE, ($page - 1) * ADMIN_PER_PAGE),
        'total' => $admin->count_auctions($query, $filter),
    ];
}

/**
 * Hide a listing. Not allowed once it has an active order: deal with the order instead
 * (cancel or refund it on the order page), then remove the listing.
 */
function admin_remove_auction_ctr($admin_id, $auction_id, $reason)
{
    $admin = new admin_class();
    $auction = $admin->get_auction((int) $auction_id);
    if (!$auction) {
        return admin_error('That listing does not exist.');
    }
    if ($auction['removed_at']) {
        return admin_error('That listing is already removed.');
    }
    [$reason, $error] = admin_clean_reason($reason);
    if ($error) {
        return ['errors' => ['reason' => $error]];
    }
    $escrow = get_escrow_by_auction_ctr((int) $auction_id);
    if ($escrow && !in_array($escrow['status'], ['cancelled', 'refunded'], true)) {
        return admin_error('This item has an active order (#' . $escrow['escrow_id'] . '). Cancel or refund the order first.');
    }

    $admin->remove_auction((int) $auction_id, $reason, (int) $admin_id);
    $admin->log((int) $admin_id, 'remove_listing', 'auction', (int) $auction_id, $auction['title'] . ': ' . $reason);
    return ['ok' => true];
}

function admin_restore_auction_ctr($admin_id, $auction_id)
{
    $admin = new admin_class();
    if (!$admin->restore_auction((int) $auction_id)) {
        return admin_error('That listing is not removed.');
    }
    $admin->log((int) $admin_id, 'restore_listing', 'auction', (int) $auction_id, $admin->auction_title((int) $auction_id));
    return ['ok' => true];
}

/* =====================================================================
 * Categories
 * ===================================================================== */

function admin_get_categories_ctr()
{
    return (new admin_class())->categories_with_counts();
}

/** Create (no $cat_id) or rename (with $cat_id) a category. */
function admin_save_category_ctr($admin_id, $cat_id, $name)
{
    $admin = new admin_class();
    $name = trim(preg_replace('/\s+/', ' ', strip_tags($name ?? '')));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        return ['errors' => ['cat_name' => 'Use 2 to 60 characters.']];
    }
    if ($admin->category_name_taken($name, (int) $cat_id)) {
        return ['errors' => ['cat_name' => 'A category with that name already exists.']];
    }

    if ($cat_id) {
        $category = $admin->get_category((int) $cat_id);
        if (!$category) {
            return admin_error('That category does not exist.');
        }
        $admin->rename_category((int) $cat_id, $name);
        $admin->log((int) $admin_id, 'rename_category', 'category', (int) $cat_id, $category['cat_name'] . ' → ' . $name);
        return ['ok' => true, 'id' => (int) $cat_id];
    }

    $new_id = $admin->create_category($name);
    $admin->log((int) $admin_id, 'create_category', 'category', $new_id, $name);
    return ['ok' => true, 'id' => $new_id];
}

/** Only empty categories can be deleted, and at least one must remain. */
function admin_delete_category_ctr($admin_id, $cat_id)
{
    $admin = new admin_class();
    $category = $admin->get_category((int) $cat_id);
    if (!$category) {
        return admin_error('That category does not exist.');
    }
    if ($admin->count_categories() <= 1) {
        return admin_error('Keep at least one category.');
    }
    $count = $admin->category_listing_count((int) $cat_id);
    if ($count > 0) {
        return admin_error('"' . $category['cat_name'] . '" has ' . $count . ' listing(s). Rename it instead, or wait until it is empty.');
    }
    if (!$admin->delete_category((int) $cat_id)) {
        return admin_error('That category could not be deleted.');
    }
    $admin->log((int) $admin_id, 'delete_category', 'category', (int) $cat_id, $category['cat_name']);
    return ['ok' => true];
}

/* =====================================================================
 * Orders
 * ===================================================================== */

function admin_search_orders_ctr($status, $query, $page)
{
    $admin = new admin_class();
    $allowed = array_merge([''], array_keys(ESCROW_STATUS_LABELS), ['needs_refund']);
    $status = in_array($status, $allowed, true) ? $status : '';
    $query = trim($query);
    return [
        'rows' => add_status_labels($admin->search_orders($status, $query, ADMIN_PER_PAGE, ($page - 1) * ADMIN_PER_PAGE)),
        'total' => $admin->count_orders($status, $query),
        'counts' => $admin->order_counts_by_status(),
        'needs_refund' => $admin->count_refunds_missing(),
    ];
}

/** Everything about one order, for the admin order page. */
function admin_get_order_ctr($escrow_id)
{
    $escrows = new escrow_class();
    $escrow = $escrows->find((int) $escrow_id);
    if (!$escrow) {
        return null;
    }
    $admin = new admin_class();
    $escrow['status_label'] = escrow_status_label($escrow['status']);
    $escrow['title'] = $admin->auction_title((int) $escrow['auction_id']);
    $escrow['buyer'] = $admin->get_user((int) $escrow['buyer_id']);
    $escrow['seller'] = $admin->get_user((int) $escrow['seller_id']);
    $escrow['events'] = $escrows->events((int) $escrow_id);
    $escrow['disputes'] = get_escrow_disputes_ctr((int) $escrow_id);
    $escrow['payout'] = (new payout_class())->find_by_escrow((int) $escrow_id);
    $escrow['log'] = $admin->recent_log(20, 'order', (int) $escrow_id);
    $escrow['can'] = [
        'cancel' => $escrow['status'] === 'awaiting_payment',
        'refund' => in_array($escrow['status'], ['funds_held', 'shipped', 'disputed'], true),
        'record_refund' => $escrow['status'] === 'refunded' && !$escrow['refund_reference'],
    ];
    return $escrow;
}

/** Cancel an unpaid order. A late payment for it is refunded automatically. */
function admin_cancel_order_ctr($admin_id, $escrow_id, $reason)
{
    [$reason, $error] = admin_clean_reason($reason);
    if ($error) {
        return ['errors' => ['reason' => $error]];
    }
    $escrows = new escrow_class();
    $done = escrow_db::transaction(function () use ($escrows, $escrow_id, $admin_id, $reason) {
        return $escrows->change_status((int) $escrow_id, ['awaiting_payment'], 'cancelled',
            ['cancelled_at' => gmdate('Y-m-d H:i:s')], (int) $admin_id, 'Cancelled by Bidlab: ' . $reason);
    });
    if (!$done) {
        return admin_error('Only unpaid orders can be cancelled. Use refund for paid orders.');
    }
    (new admin_class())->log((int) $admin_id, 'cancel_order', 'order', (int) $escrow_id, $reason);
    return ['ok' => true];
}

/**
 * Refund a paid order that has not been released. Closes any open dispute in the buyer's favour.
 * The action must then send the refund through Paystack, like resolve_dispute_action.php.
 */
function admin_refund_order_ctr($admin_id, $escrow_id, $reason)
{
    [$reason, $error] = admin_clean_reason($reason);
    if ($error) {
        return ['errors' => ['reason' => $error]];
    }
    $escrows = new escrow_class();
    $disputes = new dispute_class();

    $result = escrow_db::transaction(function () use ($escrows, $disputes, $escrow_id, $admin_id, $reason) {
        $escrow = $escrows->find_for_update((int) $escrow_id);
        if (!$escrow || !in_array($escrow['status'], ['funds_held', 'shipped', 'disputed'], true)) {
            return admin_error('Only paid orders that have not been completed can be refunded.');
        }
        $open = $disputes->find_open_by_escrow((int) $escrow_id);
        if ($open) {
            $disputes->resolve((int) $open['dispute_id'], 'resolved_refund', $reason, (int) $admin_id);
        }
        $escrows->change_status((int) $escrow_id, [$escrow['status']], 'refunded',
            ['refunded_at' => gmdate('Y-m-d H:i:s')], (int) $admin_id, 'Refunded by Bidlab: ' . $reason);
        return [
            'ok' => true,
            'escrow_id' => (int) $escrow_id,
            'payment_reference' => $escrow['payment_reference'],
            'refund_minor' => (int) $escrow['total_minor'],
        ];
    });

    if (!empty($result['ok'])) {
        (new admin_class())->log((int) $admin_id, 'refund_order', 'order', (int) $escrow_id, $reason);
    }
    return $result;
}

/** Record a refund done by hand in the Paystack dashboard. */
function admin_record_refund_ctr($admin_id, $escrow_id, $refund_reference)
{
    $refund_reference = trim(strip_tags($refund_reference ?? ''));
    if (mb_strlen($refund_reference) < 3 || mb_strlen($refund_reference) > 100) {
        return ['errors' => ['refund_reference' => 'Enter the refund reference from Paystack.']];
    }
    $escrow = (new escrow_class())->find((int) $escrow_id);
    if (!$escrow || $escrow['status'] !== 'refunded' || $escrow['refund_reference']) {
        return admin_error('This order is not waiting for a refund reference.');
    }
    set_refund_reference_ctr((int) $escrow_id, $refund_reference);
    (new admin_class())->log((int) $admin_id, 'record_refund', 'order', (int) $escrow_id, $refund_reference);
    return ['ok' => true];
}

/* =====================================================================
 * Payouts
 * ===================================================================== */

function admin_search_payouts_ctr($status, $page)
{
    $admin = new admin_class();
    $status = in_array($status, ['', 'pending', 'processing', 'sent', 'failed'], true) ? $status : '';
    return [
        'rows' => $admin->search_payouts($status, ADMIN_PER_PAGE, ($page - 1) * ADMIN_PER_PAGE),
        'total' => $admin->count_payouts($status),
        'counts' => [
            'pending' => $admin->count_payouts('pending'),
            'processing' => $admin->count_payouts('processing'),
            'failed' => $admin->count_payouts('failed'),
            'sent' => $admin->count_payouts('sent'),
        ],
    ];
}

/** Put a failed payout back in the queue. The scheduled job sends it on its next run. */
function admin_retry_payout_ctr($admin_id, $payout_id)
{
    $payout = get_payout_ctr((int) $payout_id);
    if (!$payout || $payout['status'] !== 'failed') {
        return admin_error('Only failed payouts can be retried.');
    }
    retry_payout_ctr((int) $payout_id);
    (new admin_class())->log((int) $admin_id, 'retry_payout', 'payout', (int) $payout_id, $payout['failure_reason']);
    return ['ok' => true];
}

/**
 * Mark a failed or stuck payout as paid, when you confirmed or sent it yourself in the Paystack dashboard.
 * Never use this unless the seller really has the money.
 */
function admin_mark_payout_paid_ctr($admin_id, $payout_id, $reference)
{
    $reference = trim(strip_tags($reference ?? ''));
    if (mb_strlen($reference) < 3 || mb_strlen($reference) > 100) {
        return ['errors' => ['reference' => 'Enter the transfer reference from Paystack.']];
    }
    $admin = new admin_class();
    if (!$admin->mark_payout_paid_manually((int) $payout_id, $reference)) {
        return admin_error('Only failed or stuck payouts can be marked as paid.');
    }
    $admin->log((int) $admin_id, 'mark_payout_paid', 'payout', (int) $payout_id, $reference);
    return ['ok' => true];
}

/** Used by resolve_dispute_action.php to record dispute decisions in the admin log. */
function admin_log_ctr($admin_id, $action, $target_type, $target_id, $details = null)
{
    (new admin_class())->log((int) $admin_id, $action, $target_type, (int) $target_id, $details);
}
