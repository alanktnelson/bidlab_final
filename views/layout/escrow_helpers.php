<?php
// Display helpers shared by the order pages.

/** CSS class for an escrow status badge. */
function escrow_badge_class($status)
{
    switch ($status) {
        case 'released':
            return 'status good';
        case 'awaiting_payment':
        case 'shipped':
            return 'status wait';
        case 'disputed':
        case 'cancelled':
        case 'refunded':
            return 'status bad';
        default:
            return 'status';
    }
}

/** A UTC time from the database, shown in the viewer's local time by your footer script. */
function escrow_time($utc)
{
    if (!$utc) {
        return '';
    }
    return '<span data-local-time="' . iso_time($utc) . '">' . e($utc) . ' UTC</span>';
}

/** What the viewer needs to do next, in one line. */
function escrow_next_step(array $escrow, $role)
{
    $buyer = $role === 'buyer';
    switch ($escrow['status']) {
        case 'awaiting_payment':
            return $buyer ? 'Pay to secure your item' : 'Waiting for the buyer to pay';
        case 'funds_held':
            if ($escrow['handover'] === 'pickup') {
                return $buyer ? 'Arrange pickup with the seller' : 'Arrange pickup with the buyer';
            }
            return $buyer ? 'Waiting for the seller to send it' : 'Send the item';
        case 'shipped':
            return $buyer ? 'Confirm when you have the item' : 'Waiting for the buyer to confirm';
        case 'disputed':
            return 'Bidlab is reviewing a problem';
        case 'released':
            return $buyer ? 'Completed' : 'Completed – payout on its way';
        case 'refunded':
            return $buyer ? 'Refunded to you' : 'Refunded to the buyer';
        case 'cancelled':
            return 'Cancelled – not paid in time';
        default:
            return '';
    }
}
