<?php
require_once __DIR__ . '/../classes/auction_class.php';
require_once __DIR__ . '/../classes/bid_class.php';
require_once __DIR__ . '/../classes/user_class.php';

/** Adds everything the views need to know about an auction row. */
function prepare_auction_ctr($row, $viewer_id) {
    $top = $row['top_amount'] === null ? null : (int) $row['top_amount'];
    $starting = (int) $row['starting_price_minor'];
    $bid_count = (int) $row['bid_count'];
    $is_open = (bool) $row['is_open'];

    $row['auction_id'] = (int) $row['auction_id'];
    $row['bid_count'] = $bid_count;
    $row['is_open'] = $is_open;
    $row['watched'] = (bool) $row['watched'];
    $row['delivery_fee_minor'] = (int) $row['delivery_fee_minor'];
    $row['current_price_minor'] = $top ?? $starting;
    $row['min_next_bid_minor'] = $top === null ? $starting : $top + MIN_BID_INCREMENT_MINOR;
    $row['result'] = $is_open ? null : ($bid_count > 0 ? 'sold' : 'unsold');
    $row['you_are_seller'] = $viewer_id !== null && $viewer_id === (int) $row['seller_id'];
    $row['you_are_highest'] = $viewer_id !== null && $row['top_bidder_id'] !== null && $viewer_id === (int) $row['top_bidder_id'];
    $row['is_removed'] = !empty($row['removed_at']);
    return $row;
}

function get_categories_ctr() {
    return (new Auction())->get_categories();
}

function search_auctions_ctr($filters, $viewer_id) {
    $rows = (new Auction())->search_auctions($filters, $viewer_id);
    return array_map(fn ($row) => prepare_auction_ctr($row, $viewer_id), $rows);
}

function get_market_stats_ctr() {
    $stats = (new Auction())->get_market_stats();
    return ['open_lots' => (int) $stats['open_lots'], 'closing_soon' => (int) $stats['closing_soon']];
}

function get_auction_ctr($id, $viewer_id) {
    $row = (new Auction())->get_auction($id, $viewer_id);
    return $row === null ? null : prepare_auction_ctr($row, $viewer_id);
}

function get_auction_details_ctr($id, $viewer_id) {
    $auction = get_auction_ctr($id, $viewer_id);
    if ($auction === null) {
        return null;
    }
    $auction['photos'] = (new Auction())->get_photos($id);
    $auction['bids'] = (new Bid())->get_bids($id);
    $auction['contact'] = get_handover_contact_ctr($auction);
    return $auction;
}

/** Emails are only shared between the seller and the winner, once the auction has closed. */
function get_handover_contact_ctr($auction) {
    if ($auction['result'] !== 'sold' || !($auction['you_are_seller'] || $auction['you_are_highest'])) {
        return null;
    }
    $other_id = $auction['you_are_seller'] ? (int) $auction['top_bidder_id'] : (int) $auction['seller_id'];
    $user = (new User())->get_user_by_id($other_id);
    return $user === null ? null : ['user_name' => $user['user_name'], 'user_email' => $user['user_email']];
}

function get_seller_auctions_ctr($user_id) {
    return array_map(fn ($row) => prepare_auction_ctr($row, $user_id), (new Auction())->get_seller_auctions($user_id));
}

function get_activity_ctr($user_id) {
    $auction = new Auction();
    $your_bids = (new Bid())->get_user_top_bids($user_id);
    $bids = array_map(function ($row) use ($user_id, $your_bids) {
        $lot = prepare_auction_ctr($row, $user_id);
        $lot['your_bid_minor'] = $your_bids[$lot['auction_id']];
        return $lot;
    }, $auction->get_bid_auctions($user_id));
    $watching = array_map(fn ($row) => prepare_auction_ctr($row, $user_id), $auction->get_watched_auctions($user_id));
    return ['bids' => $bids, 'watching' => $watching];
}

function validate_auction_ctr($data) {
    $errors = [];
    $conditions = ['new', 'like_new', 'good', 'fair', 'poor'];

    if (mb_strlen($data['title']) < 3 || mb_strlen($data['title']) > 120) {
        $errors['title'] = 'Give the item a title of 3 to 120 characters.';
    }
    if (mb_strlen($data['description']) < 10 || mb_strlen($data['description']) > 5000) {
        $errors['description'] = 'Describe the item in 10 to 5000 characters.';
    }
    if (mb_strlen($data['location']) < 2 || mb_strlen($data['location']) > 120) {
        $errors['location'] = 'Say where the item is located.';
    }
    if (!in_array($data['condition'], $conditions, true)) {
        $errors['condition'] = "Choose the item's condition.";
    }
    if (!(new Auction())->category_exists($data['cat_id'])) {
        $errors['cat_id'] = 'Choose a category.';
    }
    if ((int) $data['offers_pickup'] + (int) $data['offers_delivery'] !== 1) {
        $errors['handover'] = 'Choose pickup or delivery.';
    }
    if ($data['starting_price_minor'] === null || $data['starting_price_minor'] < 1) {
        $errors['starting_price'] = 'Enter a starting price above zero.';
    }
    if ($data['delivery_fee_minor'] === null) {
        $errors['delivery_fee'] = 'Enter a valid delivery fee.';
    }

    $now = time();
    if ($data['closes_timestamp'] === false || $data['closes_timestamp'] < $now + MIN_AUCTION_MINUTES * 60) {
        $errors['closes_at'] = 'The closing time must be at least ' . MIN_AUCTION_MINUTES . ' minutes from now.';
    } elseif ($data['closes_timestamp'] > $now + MAX_AUCTION_DAYS * 86400) {
        $errors['closes_at'] = 'The closing time must be within ' . MAX_AUCTION_DAYS . ' days.';
    }

    return $errors;
}

/** PHP's $_FILES holds multiple uploads as separate lists: this turns them into one list of files. */
function normalise_uploads_ctr($files) {
    $list = [];
    if ($files === null || !is_array($files['tmp_name'])) {
        return $list;
    }
    foreach ($files['tmp_name'] as $index => $tmp_name) {
        if ($files['error'][$index] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $list[] = ['tmp_name' => $tmp_name, 'error' => $files['error'][$index], 'size' => $files['size'][$index]];
    }
    return $list;
}

/** Returns ['id' => new auction id] or ['errors' => [...]]. */
function add_auction_ctr($data, $files) {
    $errors = validate_auction_ctr($data);

    $uploads = normalise_uploads_ctr($files);
    if (count($uploads) < 1) {
        $errors['photos'] = 'Add at least one photo of the item.';
    } elseif (count($uploads) > AUCTION_MAX_PHOTOS) {
        $errors['photos'] = 'Add no more than ' . AUCTION_MAX_PHOTOS . ' photos.';
    }
    if ($errors) {
        return ['errors' => $errors];
    }

    $saved = [];
    foreach ($uploads as $file) {
        [$filename, $error] = save_auction_image($file);
        if ($error !== null) {
            delete_auction_images($saved);
            return ['errors' => ['photos' => $error]];
        }
        $saved[] = $filename;
    }

    $data['closes_at'] = gmdate('Y-m-d H:i:s', $data['closes_timestamp']);
    if (!$data['offers_delivery']) {
        $data['delivery_fee_minor'] = 0;
    }
    $id = (new Auction())->add_auction($data, $saved);
    if ($id === null) {
        delete_auction_images($saved);
        return ['errors' => ['general' => 'Your item could not be listed. Please try again.']];
    }
    return ['id' => $id];
}

function toggle_watch_ctr($user_id, $auction_id) {
    $auction = new Auction();
    $row = $auction->get_auction($auction_id, $user_id);
    if ($row === null || $row['removed_at'] !== null) {
        return false;
    }
    return $auction->is_watched($user_id, $auction_id)
        ? $auction->remove_watch($user_id, $auction_id)
        : $auction->add_watch($user_id, $auction_id);
}
?>
