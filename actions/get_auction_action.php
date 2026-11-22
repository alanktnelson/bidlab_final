<?php
// Called every few seconds by js/main.js so an open lot page shows rival bids and its close.
require_once '../core/core.php';
require_once '../controllers/auction_controller.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$auction = get_auction_ctr((int) ($_GET['id'] ?? 0), current_user_id());

if ($auction === null) {
    http_response_code(404);
    echo json_encode(['error' => 'That auction does not exist.']);
    exit;
}

echo json_encode([
    'is_open' => $auction['is_open'] && !$auction['is_removed'],   // removed listings reload as closed
    'bid_count' => $auction['bid_count'],
    'you_are_highest' => $auction['you_are_highest'],
    'current_price' => money($auction['current_price_minor']),
    'min_next_bid' => money($auction['min_next_bid_minor']),
    'min_next_bid_input' => money_input($auction['min_next_bid_minor']),
]);
?>
