<?php
require_once __DIR__ . '/../core/escrow_db.php';

class escrow_class extends escrow_db
{
    // Date columns a status change is allowed to set, so column names never come from user input.
    private const SETTABLE = [
        'paid_at', 'shipped_at', 'auto_release_at', 'released_at', 'refunded_at', 'cancelled_at', 'refund_reference',
    ];

    public function create(array $row)
    {
        $sql = 'INSERT INTO escrows
                    (auction_id, buyer_id, seller_id, item_amount_minor, delivery_fee_minor, platform_fee_minor,
                     total_minor, payout_minor, currency, handover, status, pay_by)
                VALUES
                    (:auction_id, :buyer_id, :seller_id, :item_amount_minor, :delivery_fee_minor, :platform_fee_minor,
                     :total_minor, :payout_minor, :currency, :handover, \'awaiting_payment\', :pay_by)';
        $this->db()->prepare($sql)->execute([
            ':auction_id' => $row['auction_id'],
            ':buyer_id' => $row['buyer_id'],
            ':seller_id' => $row['seller_id'],
            ':item_amount_minor' => $row['item_amount_minor'],
            ':delivery_fee_minor' => $row['delivery_fee_minor'],
            ':platform_fee_minor' => $row['platform_fee_minor'],
            ':total_minor' => $row['total_minor'],
            ':payout_minor' => $row['payout_minor'],
            ':currency' => $row['currency'],
            ':handover' => $row['handover'],
            ':pay_by' => $row['pay_by'],
        ]);
        $escrow_id = (int) $this->db()->lastInsertId();
        $this->log_event($escrow_id, null, 'awaiting_payment', null, 'Auction closed with a winner');
        return $escrow_id;
    }

    public function find($escrow_id)
    {
        return $this->fetch_one('SELECT * FROM escrows WHERE escrow_id = ?', [$escrow_id]);
    }

    /** Same as find(), but locks the row until the transaction ends. */
    public function find_for_update($escrow_id)
    {
        return $this->fetch_one('SELECT * FROM escrows WHERE escrow_id = ? FOR UPDATE', [$escrow_id]);
    }

    public function find_by_auction($auction_id)
    {
        return $this->fetch_one('SELECT * FROM escrows WHERE auction_id = ?', [$auction_id]);
    }

    public function find_by_payment_reference($reference)
    {
        return $this->fetch_one('SELECT * FROM escrows WHERE payment_reference = ?', [$reference]);
    }

    public function for_buyer($buyer_id)
    {
        return $this->fetch_all('SELECT * FROM escrows WHERE buyer_id = ? ORDER BY created_at DESC', [$buyer_id]);
    }

    public function for_seller($seller_id)
    {
        return $this->fetch_all('SELECT * FROM escrows WHERE seller_id = ? ORDER BY created_at DESC', [$seller_id]);
    }

    public function set_payment_reference($escrow_id, $reference)
    {
        $statement = $this->db()->prepare(
            "UPDATE escrows SET payment_reference = ? WHERE escrow_id = ? AND status = 'awaiting_payment'"
        );
        $statement->execute([$reference, $escrow_id]);
        return $statement->rowCount() === 1;
    }

    /**
     * Move an escrow to a new status, but only if it is currently in one of $from.
     * Returns false if another request changed it first, so money can never move twice.
     */
    public function change_status($escrow_id, array $from, $to, array $set = [], $actor_id = null, $note = null)
    {
        $columns = ['status = :to'];
        $params = [':to' => $to, ':id' => $escrow_id];
        foreach ($set as $column => $value) {
            if (!in_array($column, self::SETTABLE, true)) {
                throw new InvalidArgumentException("Column $column cannot be set here.");
            }
            $columns[] = "$column = :$column";
            $params[":$column"] = $value;
        }

        $placeholders = [];
        foreach (array_values($from) as $index => $status) {
            $placeholders[] = ":from$index";
            $params[":from$index"] = $status;
        }

        $current = $this->find($escrow_id);
        $sql = 'UPDATE escrows SET ' . implode(', ', $columns)
             . ' WHERE escrow_id = :id AND status IN (' . implode(', ', $placeholders) . ')';
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);

        if ($statement->rowCount() !== 1) {
            return false;
        }
        $this->log_event($escrow_id, $current['status'] ?? null, $to, $actor_id, $note);
        return true;
    }

    /** Unpaid escrows whose payment deadline has passed. */
    public function due_for_cancel($limit = 100)
    {
        return $this->fetch_all(
            "SELECT * FROM escrows WHERE status = 'awaiting_payment' AND pay_by <= ? ORDER BY pay_by LIMIT " . (int) $limit,
            [self::now()]
        );
    }

    /** Shipped escrows the buyer has not confirmed or disputed in time. */
    public function due_for_release($limit = 100)
    {
        return $this->fetch_all(
            "SELECT * FROM escrows WHERE status = 'shipped' AND auto_release_at <= ? ORDER BY auto_release_at LIMIT " . (int) $limit,
            [self::now()]
        );
    }

    /** Email of any user, for the payment page. Table and column names are set in core/escrow_config.php. */
    public function user_email($user_id)
    {
        $row = $this->fetch_one(
            'SELECT ' . USERS_EMAIL_COLUMN . ' AS email FROM ' . USERS_TABLE . ' WHERE ' . USERS_ID_COLUMN . ' = ?',
            [$user_id]
        );
        return $row['email'] ?? null;
    }

    public function events($escrow_id)
    {
        return $this->fetch_all('SELECT * FROM escrow_events WHERE escrow_id = ? ORDER BY event_id', [$escrow_id]);
    }

    public function log_event($escrow_id, $from, $to, $actor_id = null, $note = null)
    {
        $this->db()->prepare(
            'INSERT INTO escrow_events (escrow_id, from_status, to_status, actor_id, note, created_at) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$escrow_id, $from, $to, $actor_id, $note, self::now()]);
    }

    private function fetch_one($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    private function fetch_all($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
