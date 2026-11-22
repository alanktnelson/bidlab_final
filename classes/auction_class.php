<?php
require_once __DIR__ . '/../core/db_class.php';

class Auction extends Database {
    const SELECT = "
        SELECT a.*, c.cat_name AS category, u.user_name AS seller_name,
            a.closes_at > UTC_TIMESTAMP() AS is_open,
            COALESCE(stats.bid_count, 0) AS bid_count,
            stats.top_amount,
            (SELECT b.bidder_id FROM bids b WHERE b.auction_id = a.auction_id ORDER BY b.amount_minor DESC LIMIT 1) AS top_bidder_id,
            (SELECT p.filename FROM auction_photos p WHERE p.auction_id = a.auction_id ORDER BY p.position LIMIT 1) AS cover_photo,
            EXISTS (SELECT 1 FROM watchlist w WHERE w.auction_id = a.auction_id AND w.user_id = ?) AS watched
        FROM auctions a
        JOIN auction_categories c ON c.cat_id = a.cat_id
        JOIN users u ON u.user_id = a.seller_id
        LEFT JOIN (
            SELECT auction_id, COUNT(*) AS bid_count, MAX(amount_minor) AS top_amount FROM bids GROUP BY auction_id
        ) stats ON stats.auction_id = a.auction_id
    ";

    public function get_categories() {
        return $this->fetch_all('SELECT * FROM auction_categories ORDER BY cat_id');
    }

    public function category_exists($id) {
        return $this->fetch_one('SELECT cat_id FROM auction_categories WHERE cat_id = ?', 'i', $id) !== null;
    }

    /** Saves the auction and its photos together: both succeed or neither does. */
    public function add_auction($data, $photos) {
        $this->conn->begin_transaction();
        try {
            $this->run(
                'INSERT INTO auctions (seller_id, cat_id, title, description, item_condition, location, offers_pickup, offers_delivery, delivery_fee_minor, starting_price_minor, closes_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                'iissssiiiis',
                $data['seller_id'], $data['cat_id'], $data['title'], $data['description'], $data['condition'], $data['location'],
                $data['offers_pickup'], $data['offers_delivery'], $data['delivery_fee_minor'], $data['starting_price_minor'], $data['closes_at']
            );
            $id = $this->conn->insert_id;
            foreach ($photos as $position => $filename) {
                $this->run('INSERT INTO auction_photos (auction_id, filename, position) VALUES (?, ?, ?)', 'isi', $id, $filename, $position);
            }
            $this->conn->commit();
            return $id;
        } catch (Throwable $error) {
            $this->conn->rollback();
            error_log($error->getMessage());
            return null;
        }
    }

    public function get_auction($id, $viewer_id) {
        return $this->fetch_one(self::SELECT . ' WHERE a.auction_id = ?', 'ii', $viewer_id ?? 0, $id);
    }

    public function get_photos($id) {
        return array_column($this->fetch_all('SELECT filename FROM auction_photos WHERE auction_id = ? ORDER BY position', 'i', $id), 'filename');
    }

    public function search_auctions($filters, $viewer_id) {
        $where = ['a.closes_at > UTC_TIMESTAMP()', 'a.removed_at IS NULL'];
        $types = 'i';
        $params = [$viewer_id ?? 0];

        if (!empty($filters['category'])) {
            $where[] = 'a.cat_id = ?';
            $types .= 'i';
            $params[] = (int) $filters['category'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(a.title LIKE ? OR a.description LIKE ? OR c.cat_name LIKE ? OR u.user_name LIKE ?)';
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['ending_soon'])) {
            $where[] = 'a.closes_at <= UTC_TIMESTAMP() + INTERVAL 1 HOUR';
        }

        $sorts = [
            'ending' => 'a.closes_at ASC',
            'newest' => 'a.created_at DESC, a.auction_id DESC',
            'price_low' => 'COALESCE(stats.top_amount, a.starting_price_minor) ASC',
            'price_high' => 'COALESCE(stats.top_amount, a.starting_price_minor) DESC',
            'most_bids' => 'bid_count DESC',
        ];
        $order = $sorts[$filters['sort'] ?? 'ending'] ?? $sorts['ending'];

        return $this->fetch_all(self::SELECT . ' WHERE ' . implode(' AND ', $where) . " ORDER BY {$order} LIMIT 60", $types, ...$params);
    }

    public function get_market_stats() {
        return $this->fetch_one(
            'SELECT COUNT(*) AS open_lots, COALESCE(SUM(closes_at <= UTC_TIMESTAMP() + INTERVAL 1 HOUR), 0) AS closing_soon
             FROM auctions WHERE closes_at > UTC_TIMESTAMP() AND removed_at IS NULL'
        );
    }

    public function get_seller_auctions($seller_id) {
        return $this->fetch_all(self::SELECT . ' WHERE a.seller_id = ? ORDER BY a.created_at DESC, a.auction_id DESC', 'ii', $seller_id, $seller_id);
    }

    public function get_bid_auctions($user_id) {
        return $this->fetch_all(
            self::SELECT . ' WHERE a.auction_id IN (SELECT auction_id FROM bids WHERE bidder_id = ?) ORDER BY a.closes_at DESC',
            'ii', $user_id, $user_id
        );
    }

    public function get_watched_auctions($user_id) {
        return $this->fetch_all(
            self::SELECT . ' WHERE a.auction_id IN (SELECT auction_id FROM watchlist WHERE user_id = ?) AND a.removed_at IS NULL ORDER BY a.closes_at ASC',
            'ii', $user_id, $user_id
        );
    }

    public function is_watched($user_id, $auction_id) {
        return $this->fetch_one('SELECT user_id FROM watchlist WHERE user_id = ? AND auction_id = ?', 'ii', $user_id, $auction_id) !== null;
    }

    public function add_watch($user_id, $auction_id) {
        return $this->run('INSERT IGNORE INTO watchlist (user_id, auction_id) VALUES (?, ?)', 'ii', $user_id, $auction_id);
    }

    public function remove_watch($user_id, $auction_id) {
        return $this->run('DELETE FROM watchlist WHERE user_id = ? AND auction_id = ?', 'ii', $user_id, $auction_id);
    }
}
?>
