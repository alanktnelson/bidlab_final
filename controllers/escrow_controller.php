<?php
require_once __DIR__ . '/../classes/escrow_class.php';
require_once __DIR__ . '/../classes/payout_class.php';
require_once __DIR__ . '/../core/escrow_config.php';   // fee, time limits, currency, admins

const ESCROW_STATUS_LABELS = [
    'awaiting_payment' => 'Awaiting payment',
    'funds_held' => 'Paid – money held by Bidlab',
    'shipped' => 'Shipped – waiting for buyer',
    'disputed' => 'In dispute',
    'released' => 'Completed',
    'refunded' => 'Refunded',
    'cancelled' => 'Cancelled',
];

const MOBILE_MONEY_PROVIDERS = ['MTN', 'Telecel', 'AirtelTigo'];

function escrow_status_label($status)
{
    return ESCROW_STATUS_LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function escrow_error($message)
{
    return ['errors' => ['general' => $message]];
}

/* =====================================================================
 * Creating an escrow
 * ===================================================================== */

/**
 * Call this when an auction closes with a winner.
 *
 * $auction needs: auction_id, seller_id, winner_id, winning_bid_minor,
 * offers_delivery, delivery_fee_minor.
 * Safe to call twice: the second call returns the existing escrow.
 */
function create_escrow_for_auction_ctr(array $auction)
{
    $escrows = new escrow_class();

    $existing = $escrows->find_by_auction((int) $auction['auction_id']);
    if ($existing) {
        return ['id' => (int) $existing['escrow_id']];
    }

    $item = (int) $auction['winning_bid_minor'];
    $is_delivery = !empty($auction['offers_delivery']);
    $delivery_fee = $is_delivery ? (int) ($auction['delivery_fee_minor'] ?? 0) : 0;
    $platform_fee = (int) round($item * ESCROW_PLATFORM_FEE_PERCENT / 100);

    if ($item <= 0) {
        return escrow_error('The winning bid is missing.');
    }
    if ((int) $auction['winner_id'] === (int) $auction['seller_id']) {
        return escrow_error('The seller cannot be the buyer.');
    }

    try {
        $escrow_id = escrow_db::transaction(function () use ($escrows, $auction, $item, $delivery_fee, $platform_fee, $is_delivery) {
            return $escrows->create([
                'auction_id' => (int) $auction['auction_id'],
                'buyer_id' => (int) $auction['winner_id'],
                'seller_id' => (int) $auction['seller_id'],
                'item_amount_minor' => $item,
                'delivery_fee_minor' => $delivery_fee,
                'platform_fee_minor' => $platform_fee,
                'total_minor' => $item + $delivery_fee,
                'payout_minor' => $item + $delivery_fee - $platform_fee,
                'currency' => ESCROW_CURRENCY,
                'handover' => $is_delivery ? 'delivery' : 'pickup',
                'pay_by' => gmdate('Y-m-d H:i:s', time() + ESCROW_PAYMENT_WINDOW_HOURS * 3600),
            ]);
        });
    } catch (PDOException $error) {
        // Another request created it at the same moment (unique auction_id).
        $existing = $escrows->find_by_auction((int) $auction['auction_id']);
        if ($existing) {
            return ['id' => (int) $existing['escrow_id']];
        }
        throw $error;
    }

    return ['id' => $escrow_id];
}

/**
 * Make sure a closed, sold auction has an escrow, and return it.
 * $auction is the array from get_auction_details_ctr(). Returns null if the auction is open, unsold,
 * or missing seller_id. Safe to call on every page view.
 */
function ensure_escrow_for_auction_ctr(array $auction)
{
    if (!empty($auction['is_open']) || ($auction['result'] ?? '') !== 'sold' || empty($auction['bids'])) {
        return null;
    }
    $existing = get_escrow_by_auction_ctr($auction['auction_id']);
    if (!$existing && !empty($auction['removed_at'])) {
        return null; // Removed by an admin before an order existed.
    }
    if ($existing) {
        return $existing;
    }
    if (!isset($auction['seller_id'])) {
        error_log('Escrow: get_auction_details_ctr() did not return seller_id, so no escrow was created.');
        return null;
    }

    // The winner is the highest bid; on a tie, the earlier one.
    $winner = null;
    foreach ($auction['bids'] as $bid) {
        $better = $winner === null
            || (int) $bid['amount_minor'] > (int) $winner['amount_minor']
            || ((int) $bid['amount_minor'] === (int) $winner['amount_minor'] && $bid['created_at'] < $winner['created_at']);
        if ($better) {
            $winner = $bid;
        }
    }

    $result = create_escrow_for_auction_ctr([
        'auction_id' => (int) $auction['auction_id'],
        'seller_id' => (int) $auction['seller_id'],
        'winner_id' => (int) $winner['bidder_id'],
        'winning_bid_minor' => (int) $winner['amount_minor'],
        'offers_delivery' => !empty($auction['offers_delivery']),
        'delivery_fee_minor' => (int) ($auction['delivery_fee_minor'] ?? 0),
    ]);
    return isset($result['id']) ? get_escrow_by_auction_ctr($auction['auction_id']) : null;
}

/* =====================================================================
 * Reading escrows
 * ===================================================================== */

/**
 * One escrow, only if the viewer is its buyer or seller.
 * Adds: role ('buyer' or 'seller'), status_label, and can[] – which buttons to show.
 */
function get_escrow_ctr($escrow_id, $viewer_id)
{
    $escrows = new escrow_class();
    $escrow = $escrows->find((int) $escrow_id);
    if (!$escrow || !$viewer_id) {
        return null;
    }

    $viewer_id = (int) $viewer_id;
    if ($viewer_id === (int) $escrow['buyer_id']) {
        $role = 'buyer';
    } elseif ($viewer_id === (int) $escrow['seller_id']) {
        $role = 'seller';
    } else {
        return null;
    }

    $status = $escrow['status'];
    $escrow['role'] = $role;
    $escrow['status_label'] = escrow_status_label($status);
    $escrow['events'] = $escrows->events((int) $escrow_id);
    $escrow['can'] = [
        'pay' => $role === 'buyer' && $status === 'awaiting_payment' && strtotime($escrow['pay_by'] . ' UTC') > time(),
        'mark_shipped' => $role === 'seller' && $status === 'funds_held',
        'confirm_receipt' => $role === 'buyer' && in_array($status, ['funds_held', 'shipped'], true),
        'open_dispute' => $role === 'buyer' && in_array($status, ['funds_held', 'shipped'], true),
    ];
    return $escrow;
}

function get_escrow_by_auction_ctr($auction_id)
{
    return (new escrow_class())->find_by_auction((int) $auction_id);
}

function get_buyer_escrows_ctr($buyer_id)
{
    return add_status_labels((new escrow_class())->for_buyer((int) $buyer_id));
}

function get_seller_escrows_ctr($seller_id)
{
    return add_status_labels((new escrow_class())->for_seller((int) $seller_id));
}

function add_status_labels(array $rows)
{
    foreach ($rows as &$row) {
        $row['status_label'] = escrow_status_label($row['status']);
    }
    unset($row);
    return $rows;
}

/* =====================================================================
 * Payment
 * ===================================================================== */

/**
 * Prepare a payment for the buyer. Returns the reference and amount to send to the payment provider.
 * A new reference is made each time, so a buyer who abandons a payment can try again.
 */
function start_payment_ctr($escrow_id, $buyer_id)
{
    $escrows = new escrow_class();
    $escrow = $escrows->find((int) $escrow_id);

    if (!$escrow || (int) $escrow['buyer_id'] !== (int) $buyer_id) {
        return escrow_error('That order does not exist.');
    }
    if ($escrow['status'] !== 'awaiting_payment') {
        return escrow_error('This order has already been paid or closed.');
    }
    if (strtotime($escrow['pay_by'] . ' UTC') <= time()) {
        return escrow_error('The time to pay for this item has passed.');
    }

    $reference = 'BLB-' . $escrow['escrow_id'] . '-' . bin2hex(random_bytes(6));
    if (!$escrows->set_payment_reference((int) $escrow_id, $reference)) {
        return escrow_error('This order can no longer be paid.');
    }

    return [
        'reference' => $reference,
        'amount_minor' => (int) $escrow['total_minor'],
        'currency' => $escrow['currency'],
        'escrow_id' => (int) $escrow['escrow_id'],
    ];
}

/**
 * Record a payment AFTER the payment provider has confirmed it.
 * Called from both the callback page and the webhook, so it must be safe to call twice.
 *
 * Returns ['ok' => true, 'escrow_id' => ...] when the money is held.
 * Returns errors plus 'refund_needed' => true if money arrived for an order that can't take it.
 */
function record_payment_ctr($reference, $amount_paid_minor, $currency)
{
    $escrows = new escrow_class();

    return escrow_db::transaction(function () use ($escrows, $reference, $amount_paid_minor, $currency) {
        $escrow = $escrows->find_by_payment_reference($reference);
        if (!$escrow) {
            return escrow_error('Unknown payment reference.');
        }
        $escrow = $escrows->find_for_update((int) $escrow['escrow_id']);
        $escrow_id = (int) $escrow['escrow_id'];

        // Already recorded by the other route (callback or webhook).
        if (in_array($escrow['status'], ['funds_held', 'shipped', 'disputed', 'released'], true)) {
            return ['ok' => true, 'escrow_id' => $escrow_id];
        }

        if ($escrow['status'] !== 'awaiting_payment') {
            // Paid after it was cancelled. The money must go back.
            $escrows->log_event($escrow_id, $escrow['status'], $escrow['status'], null, "Late payment received ($reference)");
            return escrow_error('This order was closed before the payment arrived.') + ['refund_needed' => true, 'escrow_id' => $escrow_id];
        }

        if ((int) $amount_paid_minor !== (int) $escrow['total_minor'] || strtoupper($currency) !== $escrow['currency']) {
            $escrows->log_event($escrow_id, 'awaiting_payment', 'awaiting_payment', null,
                "Wrong amount paid: $amount_paid_minor $currency ($reference)");
            return escrow_error('The amount paid does not match the order total.') + ['refund_needed' => true, 'escrow_id' => $escrow_id];
        }

        $escrows->change_status($escrow_id, ['awaiting_payment'], 'funds_held',
            ['paid_at' => gmdate('Y-m-d H:i:s')], (int) $escrow['buyer_id'], "Payment confirmed ($reference)");

        return ['ok' => true, 'escrow_id' => $escrow_id];
    });
}

/* =====================================================================
 * Handover and release
 * ===================================================================== */

function mark_shipped_ctr($escrow_id, $seller_id)
{
    $escrows = new escrow_class();

    return escrow_db::transaction(function () use ($escrows, $escrow_id, $seller_id) {
        $escrow = $escrows->find_for_update((int) $escrow_id);
        if (!$escrow || (int) $escrow['seller_id'] !== (int) $seller_id) {
            return escrow_error('That order does not exist.');
        }
        if ($escrow['status'] !== 'funds_held') {
            return escrow_error('You can only mark an item as sent after the buyer has paid.');
        }

        $note = $escrow['handover'] === 'delivery' ? 'Seller sent the item' : 'Seller says the item is ready for pickup';
        $escrows->change_status((int) $escrow_id, ['funds_held'], 'shipped', [
            'shipped_at' => gmdate('Y-m-d H:i:s'),
            'auto_release_at' => gmdate('Y-m-d H:i:s', time() + ESCROW_AUTO_RELEASE_DAYS * 86400),
        ], (int) $seller_id, $note);

        return ['ok' => true];
    });
}

/** Buyer says they have the item. Works before or after the seller marks it sent (e.g. at pickup). */
function confirm_receipt_ctr($escrow_id, $buyer_id)
{
    $escrows = new escrow_class();

    return escrow_db::transaction(function () use ($escrows, $escrow_id, $buyer_id) {
        $escrow = $escrows->find_for_update((int) $escrow_id);
        if (!$escrow || (int) $escrow['buyer_id'] !== (int) $buyer_id) {
            return escrow_error('That order does not exist.');
        }
        if (!in_array($escrow['status'], ['funds_held', 'shipped'], true)) {
            return escrow_error('This order cannot be confirmed right now.');
        }

        release_escrow($escrows, $escrow, ['funds_held', 'shipped'], (int) $buyer_id, 'Buyer confirmed they received the item');
        return ['ok' => true];
    });
}

/**
 * Move money to the seller: mark the escrow released and queue a payout.
 * Must be called inside a transaction. Also used by the dispute controller.
 */
function release_escrow(escrow_class $escrows, array $escrow, array $from, $actor_id, $note)
{
    $changed = $escrows->change_status((int) $escrow['escrow_id'], $from, 'released',
        ['released_at' => gmdate('Y-m-d H:i:s')], $actor_id, $note);
    if ($changed) {
        (new payout_class())->create_payout((int) $escrow['escrow_id'], (int) $escrow['seller_id'], (int) $escrow['payout_minor']);
    }
    return $changed;
}

/* =====================================================================
 * Scheduled jobs (call from cron/escrow_jobs.php)
 * ===================================================================== */

/** Cancel orders the buyer did not pay for in time. Returns the cancelled escrows so you can notify people or relist. */
function cancel_unpaid_escrows_ctr()
{
    $escrows = new escrow_class();
    $cancelled = [];
    foreach ($escrows->due_for_cancel() as $escrow) {
        $done = escrow_db::transaction(function () use ($escrows, $escrow) {
            return $escrows->change_status((int) $escrow['escrow_id'], ['awaiting_payment'], 'cancelled',
                ['cancelled_at' => gmdate('Y-m-d H:i:s')], null, 'Buyer did not pay in time');
        });
        if ($done) {
            $cancelled[] = $escrow;
        }
    }
    return $cancelled;
}

/** Release money for shipped orders the buyer never confirmed or disputed. */
function release_due_escrows_ctr()
{
    $escrows = new escrow_class();
    $released = [];
    foreach ($escrows->due_for_release() as $escrow) {
        $done = escrow_db::transaction(function () use ($escrows, $escrow) {
            return release_escrow($escrows, $escrow, ['shipped'], null,
                'Released automatically after ' . ESCROW_AUTO_RELEASE_DAYS . ' days with no response from the buyer');
        });
        if ($done) {
            $released[] = $escrow;
        }
    }
    return $released;
}

/* =====================================================================
 * Seller payout accounts and payouts
 * ===================================================================== */

function get_payout_account_ctr($seller_id)
{
    return (new payout_class())->get_account((int) $seller_id);
}

function save_payout_account_ctr($seller_id, array $input)
{
    $method = $input['method'] ?? '';
    $provider = trim(strip_tags($input['provider'] ?? ''));
    $account_name = trim(strip_tags($input['account_name'] ?? ''));
    $account_number = preg_replace('/\s+/', '', $input['account_number'] ?? '');
    $errors = [];

    if (!in_array($method, ['mobile_money', 'bank'], true)) {
        $errors['method'] = 'Choose mobile money or bank.';
    }
    if ($method === 'mobile_money' && !in_array($provider, MOBILE_MONEY_PROVIDERS, true)) {
        $errors['provider'] = 'Choose your mobile money network.';
    }
    if ($method === 'bank' && (mb_strlen($provider) < 2 || mb_strlen($provider) > 60)) {
        $errors['provider'] = 'Enter your bank name.';
    }
    if (mb_strlen($account_name) < 2 || mb_strlen($account_name) > 120) {
        $errors['account_name'] = 'Enter the name on the account.';
    }
    if ($method === 'mobile_money' && !preg_match('/^0\d{9}$/', $account_number)) {
        $errors['account_number'] = 'Enter a 10-digit mobile money number, e.g. 0241234567.';
    }
    if ($method === 'bank' && !preg_match('/^\d{8,20}$/', $account_number)) {
        $errors['account_number'] = 'Enter a bank account number of 8 to 20 digits.';
    }

    if ($errors) {
        return ['errors' => $errors];
    }

    (new payout_class())->save_account((int) $seller_id, [
        'method' => $method,
        'provider' => $provider,
        'account_name' => $account_name,
        'account_number' => $account_number,
    ]);
    return ['ok' => true];
}

function get_seller_payouts_ctr($seller_id)
{
    return (new payout_class())->for_seller((int) $seller_id);
}

/** Payouts ready to send through the payment provider (used by the scheduled job). */
function get_pending_payouts_ctr()
{
    return (new payout_class())->pending_with_accounts();
}

function get_payout_ctr($payout_id)
{
    return (new payout_class())->find((int) $payout_id);
}

/** Returns the transfer reference to use, or null if another process already took this payout. */
function claim_payout_ctr($payout_id)
{
    return (new payout_class())->claim((int) $payout_id);
}

function mark_payout_sent_ctr($payout_id)
{
    return (new payout_class())->mark_sent((int) $payout_id);
}

function mark_payout_failed_ctr($payout_id, $reason)
{
    return (new payout_class())->mark_failed((int) $payout_id, $reason);
}

function retry_payout_ctr($payout_id)
{
    (new payout_class())->retry((int) $payout_id);
}

function get_stuck_payouts_ctr()
{
    return (new payout_class())->processing();
}

function save_recipient_code_ctr($seller_id, $recipient_code)
{
    (new payout_class())->set_recipient_code((int) $seller_id, $recipient_code);
}

/** Turns 'bidlab-payout-000042-1' back into payout ID 42. */
function payout_id_from_reference($reference)
{
    return preg_match('/^bidlab-payout-(\d+)-\d+$/', (string) $reference, $match) ? (int) $match[1] : null;
}

/* =====================================================================
 * Small helpers for actions
 * ===================================================================== */

function get_user_email_ctr($user_id)
{
    return (new escrow_class())->user_email((int) $user_id);
}

/** Add a note to an escrow's history without changing its status. */
function add_escrow_note_ctr($escrow_id, $note, $actor_id = null)
{
    $escrows = new escrow_class();
    $escrow = $escrows->find((int) $escrow_id);
    if ($escrow) {
        $escrows->log_event((int) $escrow_id, $escrow['status'], $escrow['status'], $actor_id, mb_substr($note, 0, 255));
    }
}

/** True for users with user_role = 'admin'. */
function escrow_is_admin($user_id)
{
    require_once __DIR__ . '/../classes/admin_class.php';
    return $user_id && (new admin_class())->is_admin((int) $user_id);
}
