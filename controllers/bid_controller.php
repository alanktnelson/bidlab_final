<?php
require_once __DIR__ . '/../classes/bid_class.php';

/** Returns null on success, or a message saying why the bid failed. */
function place_bid_ctr($user_id, $auction_id, $amount_text) {
    $amount = parse_money($amount_text);
    if ($amount === null || $amount < 1) {
        return 'Enter a valid bid amount.';
    }
    return (new Bid())->place_bid($user_id, $auction_id, $amount);
}
?>
