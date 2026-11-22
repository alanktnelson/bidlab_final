<?php
require_once __DIR__ . '/../core/escrow_db.php';
require_once __DIR__ . '/../core/escrow_config.php';

/**
 * Database queries for the admin section.
 * Table names come from core/escrow_config.php; column names are listed there too.
 */
class admin_class extends escrow_db
{
    /* =================================================================
     * Users
     * ================================================================= */

    public function is_admin($user_id)
    {
        return $this->value('SELECT user_role FROM ' . USERS_TABLE . ' WHERE user_id = ?', [$user_id]) === 'admin';
    }

    public function is_suspended($user_id)
    {
        return (int) $this->value('SELECT is_suspended FROM ' . USERS_TABLE . ' WHERE user_id = ?', [$user_id]) === 1;
    }

    public function get_user($user_id)
    {
        return $this->one(
            'SELECT user_id, user_name, user_email, user_role, is_suspended, suspended_reason, suspended_at
               FROM ' . USERS_TABLE . ' WHERE user_id = ?',
            [$user_id]
        );
    }

    /** $filter: all, admins, suspended. $query matches name, email or exact ID. */
    public function search_users($query, $filter, $limit, $offset)
    {
        [$where, $params] = $this->user_filter($query, $filter);
        return $this->all(
            'SELECT user_id, user_name, user_email, user_role, is_suspended
               FROM ' . USERS_TABLE . " WHERE $where ORDER BY user_id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function count_users($query = '', $filter = 'all')
    {
        [$where, $params] = $this->user_filter($query, $filter);
        return (int) $this->value('SELECT COUNT(*) FROM ' . USERS_TABLE . " WHERE $where", $params);
    }

    private function user_filter($query, $filter)
    {
        $where = ['1 = 1'];
        $params = [];
        if ($query !== '') {
            $where[] = '(user_name LIKE ? OR user_email LIKE ? OR user_id = ?)';
            $like = '%' . $this->escape_like($query) . '%';
            array_push($params, $like, $like, ctype_digit($query) ? (int) $query : 0);
        }
        if ($filter === 'admins') {
            $where[] = "user_role = 'admin'";
        } elseif ($filter === 'suspended') {
            $where[] = 'is_suspended = 1';
        }
        return [implode(' AND ', $where), $params];
    }

    public function count_admins()
    {
        return (int) $this->value('SELECT COUNT(*) FROM ' . USERS_TABLE . " WHERE user_role = 'admin'", []);
    }

    public function set_role($user_id, $role)
    {
        $this->run('UPDATE ' . USERS_TABLE . ' SET user_role = ? WHERE user_id = ?', [$role, $user_id]);
    }

    public function set_suspended($user_id, $suspended, $reason)
    {
        $this->run(
            'UPDATE ' . USERS_TABLE . ' SET is_suspended = ?, suspended_reason = ?, suspended_at = ? WHERE user_id = ?',
            [$suspended ? 1 : 0, $suspended ? $reason : null, $suspended ? self::now() : null, $user_id]
        );
    }

    /** Counts shown on a user's admin page. */
    public function user_activity($user_id)
    {
        return [
            'listings' => (int) $this->value('SELECT COUNT(*) FROM ' . AUCTIONS_TABLE . ' WHERE seller_id = ?', [$user_id]),
            'live_listings' => (int) $this->value(
                'SELECT COUNT(*) FROM ' . AUCTIONS_TABLE . ' WHERE seller_id = ? AND removed_at IS NULL AND closes_at > ?',
                [$user_id, self::now()]
            ),
            'bids' => (int) $this->value('SELECT COUNT(*) FROM ' . BIDS_TABLE . ' WHERE bidder_id = ?', [$user_id]),
            'purchases' => (int) $this->value('SELECT COUNT(*) FROM escrows WHERE buyer_id = ?', [$user_id]),
            'sales' => (int) $this->value('SELECT COUNT(*) FROM escrows WHERE seller_id = ?', [$user_id]),
            'open_disputes' => (int) $this->value(
                "SELECT COUNT(*) FROM disputes d JOIN escrows e ON e.escrow_id = d.escrow_id
                  WHERE d.status = 'open' AND (e.buyer_id = ? OR e.seller_id = ?)",
                [$user_id, $user_id]
            ),
        ];
    }

    /* =================================================================
     * Auctions
     * ================================================================= */

    /** $filter: all, live, closed, removed. $query matches title or exact auction ID. */
    public function search_auctions($query, $filter, $limit, $offset, $seller_id = null)
    {
        [$where, $params] = $this->auction_filter($query, $filter, $seller_id);
        return $this->all(
            'SELECT a.auction_id, a.title, a.seller_id, a.closes_at, a.created_at, a.removed_at, a.removed_reason,
                    u.user_name AS seller_name, u.user_email AS seller_email,
                    (SELECT COUNT(*) FROM ' . BIDS_TABLE . ' b WHERE b.auction_id = a.auction_id) AS bid_count,
                    (SELECT MAX(b.amount_minor) FROM ' . BIDS_TABLE . ' b WHERE b.auction_id = a.auction_id) AS top_bid_minor,
                    e.escrow_id, e.status AS escrow_status
               FROM ' . AUCTIONS_TABLE . ' a
               LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = a.seller_id
               LEFT JOIN escrows e ON e.auction_id = a.auction_id
              WHERE ' . $where . '
              ORDER BY a.auction_id DESC
              LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function count_auctions($query = '', $filter = 'all', $seller_id = null)
    {
        [$where, $params] = $this->auction_filter($query, $filter, $seller_id);
        return (int) $this->value('SELECT COUNT(*) FROM ' . AUCTIONS_TABLE . " a WHERE $where", $params);
    }

    private function auction_filter($query, $filter, $seller_id)
    {
        $where = ['1 = 1'];
        $params = [];
        if ($query !== '') {
            $where[] = '(a.title LIKE ? OR a.auction_id = ?)';
            array_push($params, '%' . $this->escape_like($query) . '%', ctype_digit($query) ? (int) $query : 0);
        }
        if ($seller_id !== null) {
            $where[] = 'a.seller_id = ?';
            $params[] = (int) $seller_id;
        }
        if ($filter === 'live') {
            $where[] = 'a.removed_at IS NULL AND a.closes_at > ?';
            $params[] = self::now();
        } elseif ($filter === 'closed') {
            $where[] = 'a.removed_at IS NULL AND a.closes_at <= ?';
            $params[] = self::now();
        } elseif ($filter === 'removed') {
            $where[] = 'a.removed_at IS NOT NULL';
        }
        return [implode(' AND ', $where), $params];
    }

    public function get_auction($auction_id)
    {
        return $this->one(
            'SELECT auction_id, title, seller_id, closes_at, removed_at, removed_reason FROM ' . AUCTIONS_TABLE . ' WHERE auction_id = ?',
            [$auction_id]
        );
    }

    public function auction_title($auction_id)
    {
        return $this->value('SELECT title FROM ' . AUCTIONS_TABLE . ' WHERE auction_id = ?', [$auction_id]);
    }

    public function remove_auction($auction_id, $reason, $admin_id)
    {
        return $this->run(
            'UPDATE ' . AUCTIONS_TABLE . ' SET removed_at = ?, removed_reason = ?, removed_by = ? WHERE auction_id = ? AND removed_at IS NULL',
            [self::now(), $reason, $admin_id, $auction_id]
        ) === 1;
    }

    public function restore_auction($auction_id)
    {
        return $this->run(
            'UPDATE ' . AUCTIONS_TABLE . ' SET removed_at = NULL, removed_reason = NULL, removed_by = NULL WHERE auction_id = ? AND removed_at IS NOT NULL',
            [$auction_id]
        ) === 1;
    }

    /* =================================================================
     * Categories
     * ================================================================= */

    public function categories_with_counts()
    {
        return $this->all(
            'SELECT c.cat_id, c.cat_name, COUNT(a.auction_id) AS listing_count
               FROM ' . CATEGORIES_TABLE . ' c
               LEFT JOIN ' . AUCTIONS_TABLE . ' a ON a.cat_id = c.cat_id
              GROUP BY c.cat_id, c.cat_name
              ORDER BY c.cat_name',
            []
        );
    }

    public function get_category($cat_id)
    {
        return $this->one('SELECT cat_id, cat_name FROM ' . CATEGORIES_TABLE . ' WHERE cat_id = ?', [$cat_id]);
    }

    public function category_name_taken($name, $except_id = 0)
    {
        return (int) $this->value(
            'SELECT COUNT(*) FROM ' . CATEGORIES_TABLE . ' WHERE LOWER(cat_name) = LOWER(?) AND cat_id <> ?',
            [$name, $except_id]
        ) > 0;
    }

    public function count_categories()
    {
        return (int) $this->value('SELECT COUNT(*) FROM ' . CATEGORIES_TABLE, []);
    }

    public function category_listing_count($cat_id)
    {
        return (int) $this->value('SELECT COUNT(*) FROM ' . AUCTIONS_TABLE . ' WHERE cat_id = ?', [$cat_id]);
    }

    public function create_category($name)
    {
        $this->run('INSERT INTO ' . CATEGORIES_TABLE . ' (cat_name) VALUES (?)', [$name]);
        return (int) $this->db()->lastInsertId();
    }

    public function rename_category($cat_id, $name)
    {
        $this->run('UPDATE ' . CATEGORIES_TABLE . ' SET cat_name = ? WHERE cat_id = ?', [$name, $cat_id]);
    }

    /** Deletes only if no listing uses it. */
    public function delete_category($cat_id)
    {
        return $this->run(
            'DELETE FROM ' . CATEGORIES_TABLE . ' WHERE cat_id = ? AND NOT EXISTS (SELECT 1 FROM ' . AUCTIONS_TABLE . ' a WHERE a.cat_id = ?)',
            [$cat_id, $cat_id]
        ) === 1;
    }

    /* =================================================================
     * Orders (escrows)
     * ================================================================= */

    /** $status: '' for all, or an escrow status, or 'needs_refund'. $query: order/auction number or an email. */
    public function search_orders($status, $query, $limit, $offset, $user_id = null)
    {
        [$where, $params] = $this->order_filter($status, $query, $user_id);
        return $this->all(
            'SELECT e.*, a.title, buyer.user_email AS buyer_email, seller.user_email AS seller_email
               FROM escrows e
               LEFT JOIN ' . AUCTIONS_TABLE . ' a ON a.auction_id = e.auction_id
               LEFT JOIN ' . USERS_TABLE . ' buyer ON buyer.user_id = e.buyer_id
               LEFT JOIN ' . USERS_TABLE . ' seller ON seller.user_id = e.seller_id
              WHERE ' . $where . '
              ORDER BY e.escrow_id DESC
              LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    public function count_orders($status = '', $query = '', $user_id = null)
    {
        [$where, $params] = $this->order_filter($status, $query, $user_id);
        return (int) $this->value(
            'SELECT COUNT(*) FROM escrows e
               LEFT JOIN ' . USERS_TABLE . ' buyer ON buyer.user_id = e.buyer_id
               LEFT JOIN ' . USERS_TABLE . " seller ON seller.user_id = e.seller_id
              WHERE $where",
            $params
        );
    }

    private function order_filter($status, $query, $user_id)
    {
        $where = ['1 = 1'];
        $params = [];
        if ($status === 'needs_refund') {
            $where[] = "e.status = 'refunded' AND e.refund_reference IS NULL";
        } elseif ($status !== '') {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }
        if ($user_id !== null) {
            $where[] = '(e.buyer_id = ? OR e.seller_id = ?)';
            array_push($params, (int) $user_id, (int) $user_id);
        }
        if ($query !== '') {
            if (ctype_digit($query)) {
                $where[] = '(e.escrow_id = ? OR e.auction_id = ?)';
                array_push($params, (int) $query, (int) $query);
            } else {
                $where[] = '(buyer.user_email LIKE ? OR seller.user_email LIKE ? OR e.payment_reference = ?)';
                $like = '%' . $this->escape_like($query) . '%';
                array_push($params, $like, $like, $query);
            }
        }
        return [implode(' AND ', $where), $params];
    }

    public function order_counts_by_status()
    {
        $counts = [];
        foreach ($this->all('SELECT status, COUNT(*) AS n FROM escrows GROUP BY status', []) as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    /** Money Bidlab is holding right now (paid, not yet released or refunded). */
    public function money_held_minor()
    {
        return (int) $this->value("SELECT COALESCE(SUM(total_minor), 0) FROM escrows WHERE status IN ('funds_held', 'shipped', 'disputed')", []);
    }

    public function count_refunds_missing()
    {
        return (int) $this->value("SELECT COUNT(*) FROM escrows WHERE status = 'refunded' AND refund_reference IS NULL", []);
    }

    /* =================================================================
     * Payouts
     * ================================================================= */

    /** $status: '' for all, or pending / processing / sent / failed. */
    public function search_payouts($status, $limit, $offset)
    {
        $where = $status === '' ? '1 = 1' : 'p.status = ?';
        return $this->all(
            'SELECT p.*, u.user_name AS seller_name, u.user_email AS seller_email,
                    acc.method, acc.provider, acc.account_number, acc.recipient_code
               FROM payouts p
               LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.seller_id
               LEFT JOIN payout_accounts acc ON acc.seller_id = p.seller_id
              WHERE ' . $where . '
              ORDER BY p.payout_id DESC
              LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $status === '' ? [] : [$status]
        );
    }

    public function count_payouts($status = '')
    {
        return $status === ''
            ? (int) $this->value('SELECT COUNT(*) FROM payouts', [])
            : (int) $this->value('SELECT COUNT(*) FROM payouts WHERE status = ?', [$status]);
    }

    /** Pending payouts that cannot go out because the seller has no payout details. */
    public function count_payouts_without_account()
    {
        return (int) $this->value(
            "SELECT COUNT(*) FROM payouts p LEFT JOIN payout_accounts a ON a.seller_id = p.seller_id
              WHERE p.status = 'pending' AND a.seller_id IS NULL",
            []
        );
    }

    /** For a payout settled outside the scheduled job (e.g. in the Paystack dashboard). */
    public function mark_payout_paid_manually($payout_id, $reference)
    {
        return $this->run(
            "UPDATE payouts SET status = 'sent', transfer_reference = ?, failure_reason = NULL, sent_at = ?
              WHERE payout_id = ? AND status IN ('failed', 'processing')",
            [$reference, self::now(), $payout_id]
        ) === 1;
    }

    /* =================================================================
     * Admin log and dashboard
     * ================================================================= */

    public function log($admin_id, $action, $target_type, $target_id, $details = null)
    {
        $this->run(
            'INSERT INTO admin_log (admin_id, action, target_type, target_id, details, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$admin_id, $action, $target_type, $target_id, $details === null ? null : mb_substr($details, 0, 500), self::now()]
        );
    }

    public function recent_log($limit = 20, $target_type = null, $target_id = null)
    {
        $where = $target_type === null ? '1 = 1' : 'l.target_type = ? AND l.target_id = ?';
        return $this->all(
            'SELECT l.*, u.user_name AS admin_name
               FROM admin_log l LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = l.admin_id
              WHERE ' . $where . '
              ORDER BY l.log_id DESC LIMIT ' . (int) $limit,
            $target_type === null ? [] : [$target_type, $target_id]
        );
    }

    public function dashboard_counts()
    {
        return [
            'users' => (int) $this->value('SELECT COUNT(*) FROM ' . USERS_TABLE, []),
            'suspended' => (int) $this->value('SELECT COUNT(*) FROM ' . USERS_TABLE . ' WHERE is_suspended = 1', []),
            'live_auctions' => (int) $this->value(
                'SELECT COUNT(*) FROM ' . AUCTIONS_TABLE . ' WHERE removed_at IS NULL AND closes_at > ?',
                [self::now()]
            ),
            'open_disputes' => (int) $this->value("SELECT COUNT(*) FROM disputes WHERE status = 'open'", []),
            'failed_payouts' => $this->count_payouts('failed'),
            'stuck_payouts' => $this->count_payouts('processing'),
            'payouts_without_account' => $this->count_payouts_without_account(),
            'refunds_missing' => $this->count_refunds_missing(),
            'money_held_minor' => $this->money_held_minor(),
            'orders' => $this->order_counts_by_status(),
        ];
    }

    /* =================================================================
     * Query helpers
     * ================================================================= */

    private function escape_like($text)
    {
        return addcslashes($text, '%_\\');
    }

    private function run($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    private function value($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();
        return $value === false ? null : $value;
    }

    private function one($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    private function all($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
