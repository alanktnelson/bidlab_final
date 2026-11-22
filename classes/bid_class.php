<?php
require_once __DIR__ . '/../core/db_class.php';

class Bid extends Database {
    /**
     * Locks the auction row, so simultaneous bids are checked one at a time against
     * the true highest bid. Returns null on success, or a message saying why it failed.
     */
    public function place_bid($user_id, $auction_id, $amount) {
        $this->conn->begin_transaction();
        try {
            $error = $this->check_and_insert($user_id, $auction_id, $amount);
            $error === null ? $this->conn->commit() : $this->conn->rollback();
            return $error;
        } catch (Throwable $failure) {
            $this->conn->rollback();
            error_log($failure->getMessage());
            return 'Your bid could not be placed. Please try again.';
        }
    }

    private function check_and_insert($user_id, $auction_id, $amount) {
        $auction = $this->fetch_one(
            'SELECT seller_id, starting_price_minor, removed_at, closes_at > UTC_TIMESTAMP() AS is_open FROM auctions WHERE auction_id = ? FOR UPDATE',
            'i', $auction_id
        );
        if ($auction === null || $auction['removed_at'] !== null) {
            return 'That auction does not exist.';
        }
        if (!$auction['is_open']) {
            return 'This auction has closed.';
        }
        if ((int) $auction['seller_id'] === $user_id) {
            return 'You cannot bid on your own item.';
        }

        $top = $this->get_top_bid($auction_id);
        if ($top !== null && (int) $top['bidder_id'] === $user_id) {
            return 'You are already the highest bidder.';
        }

        $minimum = $top === null ? (int) $auction['starting_price_minor'] : (int) $top['amount_minor'] + MIN_BID_INCREMENT_MINOR;
        if ($amount < $minimum) {
            return 'Your bid must be at least ' . money($minimum) . '.';
        }

        $this->run('INSERT INTO bids (auction_id, bidder_id, amount_minor) VALUES (?, ?, ?)', 'iii', $auction_id, $user_id, $amount);
        return null;
    }

    public function get_top_bid($auction_id) {
        return $this->fetch_one('SELECT bidder_id, amount_minor FROM bids WHERE auction_id = ? ORDER BY amount_minor DESC LIMIT 1', 'i', $auction_id);
    }

    public function get_bids($auction_id) {
        return $this->fetch_all(
            'SELECT b.*, u.user_name AS bidder FROM bids b JOIN users u ON u.user_id = b.bidder_id WHERE b.auction_id = ? ORDER BY b.amount_minor DESC',
            'i', $auction_id
        );
    }

    /** Returns [auction_id => this user's highest bid]. */
    public function get_user_top_bids($user_id) {
        $bids = [];
        foreach ($this->fetch_all('SELECT auction_id, MAX(amount_minor) AS amount FROM bids WHERE bidder_id = ? GROUP BY auction_id', 'i', $user_id) as $row) {
            $bids[(int) $row['auction_id']] = (int) $row['amount'];
        }
        return $bids;
    }
}
?>
