<?php
require_once __DIR__ . '/escrow_controller.php';
require_once __DIR__ . '/../classes/dispute_class.php';

const DISPUTE_REASONS = [
    'not_received' => 'I did not receive the item',
    'not_as_described' => 'The item is not as described',
    'damaged' => 'The item arrived damaged',
    'other' => 'Something else',
];

/**
 * Buyer reports a problem. The money is frozen until an admin decides.
 * Only possible after paying and before the money is released.
 */
function open_dispute_ctr($escrow_id, $buyer_id, $reason, $details)
{
    $details = trim(strip_tags($details ?? ''));
    $errors = [];
    if (!array_key_exists($reason, DISPUTE_REASONS)) {
        $errors['reason'] = 'Choose what went wrong.';
    }
    if (mb_strlen($details) < 10 || mb_strlen($details) > 2000) {
        $errors['details'] = 'Describe the problem in 10 to 2000 characters.';
    }
    if ($errors) {
        return ['errors' => $errors];
    }

    $escrows = new escrow_class();
    $disputes = new dispute_class();

    return escrow_db::transaction(function () use ($escrows, $disputes, $escrow_id, $buyer_id, $reason, $details) {
        $escrow = $escrows->find_for_update((int) $escrow_id);
        if (!$escrow || (int) $escrow['buyer_id'] !== (int) $buyer_id) {
            return escrow_error('That order does not exist.');
        }
        if (!in_array($escrow['status'], ['funds_held', 'shipped'], true)) {
            return escrow_error('You can only report a problem after paying and before the order is completed.');
        }
        if ($disputes->find_open_by_escrow((int) $escrow_id)) {
            return escrow_error('There is already an open dispute for this order.');
        }

        $dispute_id = $disputes->create((int) $escrow_id, (int) $buyer_id, $reason, $details);
        $escrows->change_status((int) $escrow_id, ['funds_held', 'shipped'], 'disputed', [], (int) $buyer_id,
            'Buyer opened a dispute: ' . DISPUTE_REASONS[$reason]);

        return ['ok' => true, 'id' => $dispute_id];
    });
}

function get_open_disputes_ctr()
{
    return (new dispute_class())->list_open();
}

function get_dispute_ctr($dispute_id)
{
    return (new dispute_class())->find((int) $dispute_id);
}

function get_escrow_disputes_ctr($escrow_id)
{
    return (new dispute_class())->for_escrow((int) $escrow_id);
}

/**
 * Admin decision on a dispute. $outcome is 'refund' (money back to buyer) or 'release' (money to seller).
 * The caller MUST check the user is an admin first.
 *
 * For a refund, the result includes payment_reference and refund_minor: the action must then
 * ask the payment provider to refund that amount and save the provider's reference with
 * set_refund_reference_ctr().
 */
function resolve_dispute_ctr($dispute_id, $admin_id, $outcome, $note)
{
    $note = trim(strip_tags($note ?? ''));
    if (!in_array($outcome, ['refund', 'release'], true)) {
        return escrow_error('Choose refund or release.');
    }
    if (mb_strlen($note) < 5 || mb_strlen($note) > 1000) {
        return ['errors' => ['note' => 'Explain the decision in 5 to 1000 characters.']];
    }

    $escrows = new escrow_class();
    $disputes = new dispute_class();

    return escrow_db::transaction(function () use ($escrows, $disputes, $dispute_id, $admin_id, $outcome, $note) {
        $dispute = $disputes->find_for_update((int) $dispute_id);
        if (!$dispute || $dispute['status'] !== 'open') {
            return escrow_error('This dispute is already closed.');
        }
        $escrow = $escrows->find_for_update((int) $dispute['escrow_id']);
        if (!$escrow || $escrow['status'] !== 'disputed') {
            return escrow_error('The order is not in dispute.');
        }

        if ($outcome === 'release') {
            $disputes->resolve((int) $dispute_id, 'resolved_release', $note, (int) $admin_id);
            release_escrow($escrows, $escrow, ['disputed'], (int) $admin_id, 'Dispute closed in the seller\'s favour: ' . $note);
            return ['ok' => true, 'outcome' => 'release'];
        }

        $disputes->resolve((int) $dispute_id, 'resolved_refund', $note, (int) $admin_id);
        $escrows->change_status((int) $escrow['escrow_id'], ['disputed'], 'refunded',
            ['refunded_at' => gmdate('Y-m-d H:i:s')], (int) $admin_id, 'Dispute closed in the buyer\'s favour: ' . $note);

        return [
            'ok' => true,
            'outcome' => 'refund',
            'escrow_id' => (int) $escrow['escrow_id'],
            'payment_reference' => $escrow['payment_reference'],
            'refund_minor' => (int) $escrow['total_minor'],
        ];
    });
}

/** Save the payment provider's refund reference once the refund has gone through. */
function set_refund_reference_ctr($escrow_id, $refund_reference)
{
    $escrows = new escrow_class();
    $escrow = $escrows->find((int) $escrow_id);
    if (!$escrow || $escrow['status'] !== 'refunded') {
        return false;
    }
    // Stay in 'refunded'; only the reference is added.
    return $escrows->change_status((int) $escrow_id, ['refunded'], 'refunded',
        ['refund_reference' => $refund_reference], null, "Refund sent ($refund_reference)");
}
