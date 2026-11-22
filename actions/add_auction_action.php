<?php
require_once '../core/core.php';
require_once '../controllers/auction_controller.php';
require_once '../core/admin.php';

require_active_user();   // logged in and not suspended

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/add_auction.php');
}

// PHP empties the whole form when an upload is larger than post_max_size.
if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $_SESSION['errors'] = ['photos' => 'Those photos are too large to upload together.'];
    redirect('views/add_auction.php');
}
verify_csrf();

// The browser sends the closing time in UTC. Without JavaScript, the local field is read as UTC.
$closes_text = trim($_POST['closes_at_utc'] ?? '') ?: trim($_POST['closes_at'] ?? '');
$delivery_fee = trim($_POST['delivery_fee'] ?? '');

// Sellers pick exactly one handover option. Anything else leaves both off, which fails validation.
$handover = $_POST['handover'] ?? '';
$offers_pickup = $handover === 'pickup' ? 1 : 0;
$offers_delivery = $handover === 'delivery' ? 1 : 0;

$data = [
    'seller_id' => current_user_id(),
    'cat_id' => (int) ($_POST['cat_id'] ?? 0),
    'title' => trim(strip_tags($_POST['title'] ?? '')),
    'description' => trim(strip_tags($_POST['description'] ?? '')),
    'condition' => $_POST['condition'] ?? '',
    'location' => trim(strip_tags($_POST['location'] ?? '')),
    'offers_pickup' => $offers_pickup,
    'offers_delivery' => $offers_delivery,
    'starting_price_minor' => parse_money($_POST['starting_price'] ?? ''),
    'delivery_fee_minor' => ($offers_delivery && $delivery_fee !== '') ? parse_money($delivery_fee) : 0,
    'closes_timestamp' => strtotime($closes_text),
];

$result = add_auction_ctr($data, $_FILES['photos'] ?? null);

if (isset($result['errors'])) {
    $_SESSION['errors'] = $result['errors'];
    $_SESSION['old'] = array_intersect_key($_POST, array_flip([
        'title', 'description', 'cat_id', 'condition', 'starting_price', 'closes_at', 'location', 'delivery_fee', 'handover',
    ]));
    redirect('views/add_auction.php');
}

$_SESSION['success'] = 'Your item is live.';
redirect('views/single_auction.php?id=' . $result['id']);
?>
